<?php

declare(strict_types=1);

require __DIR__.'/common.php';
require __DIR__.'/bot.php';
require __DIR__.'/app.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$db = database();
$lock = fopen(setting('BOT_DB_PATH').'.worker.lock', 'c');
if ($lock === false) {
    throw new RuntimeException('Cannot open worker lock');
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    exit;
}
foreach (rows(sql($db, "SELECT * FROM inbox WHERE state='pending' ORDER BY update_id LIMIT 100")) as $event) {
    atomic($db, function () use ($db, $event): void {
        $update = objectOrEmpty(json_decode((string) $event['payload'], true, 32, JSON_THROW_ON_ERROR));
        handle($db, $update, time());
        sql($db, "UPDATE inbox SET state='done' WHERE update_id=?", [$event['update_id']]);
    });
}
atomic($db, function () use ($db): void {
    publishReady($db, time());
});
// A claim is persisted before the call. A crash after sending leaves sending for manual review.
foreach (rows(sql($db, "SELECT * FROM outbox WHERE state='pending' AND next_attempt<=? ORDER BY id LIMIT 100", [time()])) as $job) {
    sql($db, "UPDATE outbox SET state='sending', attempts=attempts+1 WHERE id=?", [$job['id']]);
    $params = objectOrEmpty(json_decode((string) $job['payload'], true, 32, JSON_THROW_ON_ERROR));
    $result = telegram((string) $job['method'], $params);
    atomic($db, function () use ($db, $job, $result): void {
        if ($result['state'] === 'retry') {
            sql($db, 'UPDATE outbox SET state=?, next_attempt=? WHERE id=?',
                [(int) $job['attempts'] < 4 ? 'pending' : 'failed', time() + $result['delay'], $job['id']]);
            return;
        }
        sql($db, 'UPDATE outbox SET state=? WHERE id=?', [$result['state'], $job['id']]);
    });
    if ($result['state'] === 'retry') {
        break;
    }
}
