<?php

declare(strict_types=1);
/**
 * @param list<list<array{text:string,callback_data:string}>> $buttons
 */
function enqueue(PDO $db, int $chat, string $key, string $text, array $buttons = [], string $kind = '', ?int $id = null, ?int $version = null): void
{
    $payload = ['chat_id' => $chat,'text' => $text];
    if ($buttons !== []) {
        $payload['reply_markup'] = ['inline_keyboard' => $buttons];
    }
    run(
        $db,
        'INSERT OR IGNORE INTO outbox(event_key,chat_id,payload,guard_kind,guard_id,guard_version) VALUES(?,?,?,?,?,?)',
        [$key,$chat,json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),$kind,$id,$version]
    );
}
