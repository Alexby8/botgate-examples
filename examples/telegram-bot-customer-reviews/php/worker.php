<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/reviews.php';
require __DIR__.'/transport.php';
$lock = fopen(envRequired('APP_DB').'.worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$db = database();
$q = $db->prepare("SELECT * FROM inbox WHERE state='pending' ORDER BY update_id LIMIT 20");
$q->execute();
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $data) {
    $row = objectValue($data);
    $update = objectValue(json_decode(stringValue($row['payload']), true, 32, JSON_THROW_ON_ERROR));
    $callback = $update['callback_query'] ?? null;
    if (is_array($callback) && is_string($callback['id'] ?? null)) {
        try {
            sendTelegram('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
        } catch (Throwable) {
        }
    }
    $db->beginTransaction();
    try {
        reviewUpdate($db, $update);
        $db->prepare("UPDATE inbox SET state='done' WHERE update_id=?")->execute([$row['update_id']]);
        $db->commit();
    } catch (Throwable) {
        $db->rollBack();
        $db->prepare("UPDATE inbox SET state='review' WHERE update_id=?")->execute([$row['update_id']]);
    }
}
$q = $db->prepare("SELECT * FROM outbox WHERE state IN ('pending','retry') AND next_attempt_at<=? ORDER BY id LIMIT 20");
$q->execute([time()]);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $data) {
    $row = objectValue($data);
    $db->prepare("UPDATE outbox SET state='sending',attempts=attempts+1 WHERE id=?")->execute([$row['id']]);
    try {
        $r = sendTelegram('sendMessage', objectValue(json_decode(stringValue($row['payload']), true, 32, JSON_THROW_ON_ERROR)));
    } catch (Throwable) {
        $r = ['state' => 'unknown','retry_after' => 0,'message_id' => null,'code' => null];
    }
    if ($r['state'] === 'sent' && $r['message_id'] === null) {
        $r['state'] = 'unknown';
    }
    if ($r['state'] === 'retry' && intValue($row['attempts']) + 1 >= 5) {
        $r['state'] = 'failed';
    }
    $db->beginTransaction();
    try {
        $db->prepare('UPDATE outbox SET state=?,next_attempt_at=?,message_id=?,error_code=? WHERE id=?')
            ->execute([$r['state'],time() + $r['retry_after'],$r['message_id'],$r['code'],$row['id']]);
        if ($row['kind'] === 'prompt' && $r['state'] === 'sent') {
            $db->prepare('UPDATE reviews SET prompt_message_id=? WHERE id=?')->execute([$r['message_id'],$row['review_id']]);
        }
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
