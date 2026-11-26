<?php

declare(strict_types=1);
/** @param array<string,mixed> $payload */
function enqueue(PDO $db, string $key, string $method, array $payload, ?int $due = null): void
{
    run(
        $db,
        'INSERT OR IGNORE INTO outbox(event_key,method,payload,due) VALUES(?,?,?,?)',
        [$key, $method, json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $due ?? time()]
    );
}
function drain(PDO $db, int $limit = 20, int $interval = 0): void
{
    // Один sender на локальную БД. Сеть не держит транзакцию SQLite.
    $lock = fopen(envRequired('APP_DB').'.sender.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        return;
    }
    try {
        for ($i = 0; $i < $limit; $i++) {
            $job = atomic($db, function () use ($db, $interval): ?array {
                $clock = row($db, 'SELECT attempted_at FROM send_clock WHERE id=1');
                if (intValue($clock['attempted_at'] ?? 0) + $interval > time()) {
                    return null;
                }
                $job = row($db, "SELECT * FROM outbox WHERE state='pending' AND due<=? ORDER BY due,id LIMIT 1", [time()]);
                if ($job !== null) {
                    run($db, 'UPDATE send_clock SET attempted_at=? WHERE id=1', [time()]);
                    run($db, "UPDATE outbox SET state='sending',attempts=attempts+1 WHERE id=?", [intValue($job['id'])]);
                }
                return $job;
            });
            if ($job === null) {
                break;
            }
            $result = sendTelegram(stringValue($job['method']), objectValue(json_decode(stringValue($job['payload']), true, 512, JSON_THROW_ON_ERROR)));
            $state = $result['state'];
            if ($state === 'sent' && $result['message_id'] === null) {
                $state = 'unknown';
            }
            if ($state === 'retry') {
                $state = intValue($job['attempts']) < 4 ? 'pending' : 'failed';
            }
            run(
                $db,
                'UPDATE outbox SET state=?,due=?,message_id=?,error_code=? WHERE id=?',
                [$state, time() + $result['retry_after'], $result['message_id'], $result['code'], intValue($job['id'])]
            );
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}
