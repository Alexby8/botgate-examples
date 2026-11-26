<?php

declare(strict_types=1);

/** @param array<string, mixed> $update */
function handle(PDO $db, array $update, int $now): void
{
    $action = privateAction($update);
    if ($action !== null && $action['callback']) {
        if (preg_match('/^album:([0-9]+):([0-9]+)$/D', $action['text'], $match)) {
            submitAlbum($db, (int) $match[1], (int) $match[2], $action['user']);
        }
        return;
    }
    $message = objectOrEmpty($update['message'] ?? null);
    $chat = objectOrEmpty($message['chat'] ?? null);
    $from = objectOrEmpty($message['from'] ?? null);
    $user = $from['id'] ?? null;
    $messageId = $message['message_id'] ?? null;
    if (($chat['type'] ?? '') !== 'private' || !is_int($user) || $user <= 0
        || ($chat['id'] ?? null) !== $user || !is_int($messageId)) {
        return;
    }
    if (($message['text'] ?? null) === '/start') {
        say($db, $user, 'Отправьте несколько фотографий одним альбомом, затем подтвердите полученный список.');
        return;
    }

    $group = $message['media_group_id'] ?? null;
    $photos = $message['photo'] ?? null;
    if (!is_string($group) || $group === '' || !is_array($photos)) {
        return;
    }
    $file = bestFile($photos);
    if ($file !== null) {
        collectPhoto($db, $user, $messageId, $group, $file, $now);
    }
}

/** @param array<array-key, mixed> $photos */
function bestFile(array $photos): ?string
{
    $best = null;
    $area = 0;
    foreach ($photos as $entry) {
        $photo = objectOrEmpty($entry);
        $file = $photo['file_id'] ?? null;
        $width = $photo['width'] ?? null;
        $height = $photo['height'] ?? null;
        if (is_string($file) && $file !== '' && is_int($width) && is_int($height) && $width * $height > $area) {
            $best = $file;
            $area = $width * $height;
        }
    }
    return $best;
}

function collectPhoto(PDO $db, int $chat, int $message, string $group, string $file, int $now): void
{
    sql($db, 'INSERT INTO albums(chat_id, media_group_id, ready_at) VALUES(?,?,?)
        ON CONFLICT(chat_id, media_group_id) DO NOTHING', [$chat, $group, $now + 2]);
    $album = row(sql($db, 'SELECT * FROM albums WHERE chat_id=? AND media_group_id=?', [$chat, $group]))
        ?? throw new RuntimeException('Album missing');
    $id = (int) $album['id'];
    if (row(sql($db, 'SELECT message_id FROM photos WHERE chat_id=? AND message_id=?', [$chat, $message])) !== null) {
        return;
    }
    if ($album['state'] === 'submitted') {
        say($db, $chat, 'Фотография поступила после подтверждения. Отправьте её в составе нового альбома.');
        return;
    }
    sql($db, 'INSERT INTO photos(chat_id,message_id,album_id,file_id) VALUES(?,?,?,?)',
        [$chat, $message, $id, $file]);
    sql($db, "UPDATE albums SET revision=revision+1, ready_at=?, state='collecting' WHERE id=?", [$now + 2, $id]);
}

function publishReady(PDO $db, int $now): void
{
    foreach (rows(sql($db, "SELECT * FROM albums WHERE state='collecting' AND ready_at<=? ORDER BY id LIMIT 100", [$now])) as $album) {
        $id = (int) $album['id'];
        $revision = (int) $album['revision'];
        $photos = rows(sql($db, 'SELECT message_id FROM photos WHERE album_id=? ORDER BY message_id', [$id]));
        $numbers = array_map(static fn (array $photo): string => (string) $photo['message_id'], $photos);
        sql($db, "UPDATE albums SET state='review' WHERE id=?", [$id]);
        menu($db, (int) $album['chat_id'],
            'Фото в альбоме: '.count($photos).'. Порядок сообщений: '.implode(', ', $numbers)
                .'. Сверьте количество перед отправкой заявки.',
            [[['text' => 'Отправить заявку', 'callback_data' => "album:$id:$revision"]]]);
    }
}

function submitAlbum(PDO $db, int $id, int $revision, int $chat): void
{
    $album = row(sql($db, 'SELECT * FROM albums WHERE id=? AND chat_id=?', [$id, $chat]));
    if ($album === null || (int) $album['revision'] !== $revision || $album['state'] === 'collecting') {
        say($db, $chat, 'Альбом обновился. Дождитесь актуального списка и подтвердите его.');
        return;
    }
    sql($db, 'INSERT INTO requests(album_id, chat_id) VALUES(?,?) ON CONFLICT(album_id) DO NOTHING', [$id, $chat]);
    sql($db, "UPDATE albums SET state='submitted' WHERE id=?", [$id]);
    $request = row(sql($db, 'SELECT id FROM requests WHERE album_id=?', [$id]))
        ?? throw new RuntimeException('Request missing');
    say($db, $chat, 'Заявка №'.$request['id'].' сохранена.');
}
