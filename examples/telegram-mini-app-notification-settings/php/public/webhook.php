<?php
declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/telegram.php';
$update = incomingUpdate();
$message = $update['message'] ?? null;
$from = is_array($message) ? ($message['from'] ?? null) : null;
$chat = is_array($message) ? ($message['chat'] ?? null) : null;
if (is_int($update['update_id'] ?? null) && is_array($message) && is_array($from) && is_array($chat)
    && is_int($from['id'] ?? null) && ($chat['id'] ?? null) === $from['id']
    && ($chat['type'] ?? '') === 'private'
    && (isset($message['write_access_allowed']) || ($message['text'] ?? '') === '/start')) {
    // Only new jobs are eligible again. Old blocked/skipped jobs stay terminal.
    $db = database();
    atomic($db, function () use ($db, $update, $from): void {
        if (sql($db, 'INSERT OR IGNORE INTO updates(id) VALUES(?)', [$update['update_id']])->rowCount() === 1) {
            sql($db, 'UPDATE preferences SET blocked=0 WHERE user_id=?', [$from['id']]);
        }
    });
}
reply(['ok' => true]);
