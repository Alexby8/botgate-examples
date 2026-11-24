<?php

declare(strict_types=1);

require_once __DIR__.'/catalog.php';
require_once __DIR__.'/outbox.php';

/**
 * @param  array<string,mixed>  $u
 * @return array<string,mixed>
 */
function handle(PDO $db, array $u): array
{
    $message = objectValue($u['message'] ?? []);
    $chat = objectValue($message['chat'] ?? []);
    if (($chat['type'] ?? '') !== 'private') {
        return [];
    }
    $user = intValue($chat['id']);
    if ($user !== intValue(objectValue($message['from'])['id'])) {
        return [];
    }
    $text = catalogText($db, stringValue($message['text'] ?? ''), time());
    enqueue($db, 'catalog:'.intValue($u['update_id']), 'sendMessage', [
        'chat_id' => $user, 'text' => $text,
    ]);

    return [];
}
