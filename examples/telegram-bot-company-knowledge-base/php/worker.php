<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/bot.php';

$lock = fopen(envRequired('APP_DB').'.worker.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(0);
}
$db = database();
$pending = $db->query("SELECT * FROM inbox WHERE state='pending' ORDER BY update_id LIMIT 20");
if ($pending === false) {
    throw new RuntimeException('Cannot read inbox');
}
$pending->setFetchMode(PDO::FETCH_ASSOC);
foreach ($pending->fetchAll() as $data) {
    $row = objectValue($data);
    $row['update_id'] = intValue($row['update_id']);
    $claim = $db->prepare("UPDATE inbox SET state='processing' WHERE update_id=? AND state='pending'");
    $claim->execute([$row['update_id']]);
    if ($claim->rowCount() !== 1) {
        continue;
    }
    try {
        $update = objectValue(json_decode(stringValue($row['payload']), true, 32, JSON_THROW_ON_ERROR));
        handleUpdate($db, $update);
        $db->prepare("UPDATE inbox SET state='done' WHERE update_id=?")->execute([$row['update_id']]);
    } catch (Throwable) {
        // Повтор после отправки может продублировать сообщение.
        $db->prepare("UPDATE inbox SET state='review',error_kind='handler_failed' WHERE update_id=?")
            ->execute([$row['update_id']]);
    }
}
flock($lock, LOCK_UN);
fclose($lock);
