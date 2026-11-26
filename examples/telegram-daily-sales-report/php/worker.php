<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/transport.php';
$lock = fopen(envRequired('APP_DB').'.send.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$db = database();
$q = $db->prepare("SELECT * FROM reports WHERE state IN ('pending','retry') AND next_attempt_at<=? ORDER BY id LIMIT 10");
$q->execute([time()]);
foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $r = objectValue($row);
    $id = intValue($r['id']);
    $db->prepare("UPDATE reports SET state='sending',attempts=attempts+1 WHERE id=?")->execute([$id]);
    try {
        $result = sendTelegram('sendMessage', ['chat_id' => $r['chat_id'],'text' => $r['text']]);
    } catch (Throwable) {
        $result = ['state' => 'unknown','retry_after' => 0,'message_id' => null,'code' => null];
    }
    if ($result['state'] === 'sent' && $result['message_id'] === null) {
        $result['state'] = 'unknown';
    }
    if ($result['state'] === 'retry' && intValue($r['attempts']) + 1 >= 5) {
        $result['state'] = 'failed';
    }
    $db->prepare('UPDATE reports SET state=?,next_attempt_at=?,message_id=?,error_code=? WHERE id=?')
        ->execute([$result['state'],time() + $result['retry_after'],$result['message_id'],$result['code'],$id]);
}
