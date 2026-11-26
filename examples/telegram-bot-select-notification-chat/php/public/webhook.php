<?php
declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/telegram.php';
$update = incomingUpdate();
if (!is_int($update['update_id'] ?? null)) {
    reply(['error' => 'Invalid update'], 422);
}
sql(database(), 'INSERT INTO inbox(update_id,payload,received_at) VALUES(?,?,?)
    ON CONFLICT(update_id) DO NOTHING',
    [$update['update_id'], json_encode($update, JSON_THROW_ON_ERROR), time()]);
reply(['ok' => true]);
