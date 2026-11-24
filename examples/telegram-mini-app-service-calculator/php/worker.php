<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/outbox.php';
require __DIR__.'/transport.php';
require __DIR__.'/domain.php';
$db = database();
$lock = fopen(envRequired('APP_DB').'.send.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
foreach (run($db, "SELECT id FROM outbox WHERE state IN ('pending','retry') AND next_at<=? ORDER BY id LIMIT 30", [time()])->fetchAll() as $candidate) {
    $id = intValue(objectValue($candidate)['id']);
    $message = atomic($db, function () use ($db, $id): ?array {
        $r = row($db, "SELECT * FROM outbox WHERE id=? AND state IN ('pending','retry') AND next_at<=?", [$id,time()]);
        if (!$r) {
            return null;
        }
        if (!canDeliver($db, $r, time())) {
            run($db, "UPDATE outbox SET state='cancelled' WHERE id=?", [$id]);
            return null;
        }
        run($db, "UPDATE outbox SET state='sending',attempts=attempts+1 WHERE id=?", [$id]);
        return $r;
    });
    if ($message === null) {
        continue;
    }
    try {
        $result = sendTelegram('sendMessage', objectValue(json_decode(stringValue($message['payload']), true, 512, JSON_THROW_ON_ERROR)));
    } catch (Throwable) {
        $result = ['state' => 'unknown','retry_after' => 0,'message_id' => null,'code' => null];
    }
    if ($result['state'] === 'sent' && $result['message_id'] === null) {
        $result['state'] = 'unknown';
    }
    if ($result['state'] === 'retry' && intValue($message['attempts']) + 1 >= 5) {
        $result['state'] = 'failed';
    }
    run($db, 'UPDATE outbox SET state=?,next_at=?,message_id=?,error_code=? WHERE id=?', [$result['state'],time() + $result['retry_after'],$result['message_id'],$result['code'],$id]);
}
