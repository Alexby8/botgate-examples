<?php

declare(strict_types=1);

function supportGroup(): int
{
    $id = filter_var(setting('SUPPORT_GROUP_ID'), FILTER_VALIDATE_INT);
    if ($id === false || $id >= 0) {
        throw new RuntimeException('SUPPORT_GROUP_ID must be a negative chat ID');
    }
    return $id;
}

function operatorAllowed(int $id): bool
{
    return in_array((string) $id, array_map('trim', explode(',', setting('OPERATOR_IDS'))), true);
}

/** @param array<string, mixed> $update */
function handle(PDO $db, array $update, int $now): void
{
    $message = objectOrEmpty($update['message'] ?? null);
    $chat = objectOrEmpty($message['chat'] ?? null);
    $from = objectOrEmpty($message['from'] ?? null);
    $chatId = $chat['id'] ?? null;
    $actor = $from['id'] ?? null;
    $messageId = $message['message_id'] ?? null;
    $text = is_string($message['text'] ?? null) ? trim($message['text']) : '';
    if (!is_int($chatId) || !is_int($messageId) || !is_int($actor) || $actor <= 0
        || ($from['is_bot'] ?? false) === true || isset($message['sender_chat'])) {
        return;
    }
    $group = supportGroup();
    if (($chat['type'] ?? '') === 'private' && $chatId === $actor) {
        if ($text === '/start') {
            say($db, $actor, 'Напишите вопрос. Можно приложить фото или документ.');
            return;
        }
        if ($text === '' && !isset($message['photo']) && !isset($message['document'])
            && !isset($message['voice']) && !isset($message['video'])) {
            return;
        }
        $ticket = row(sql($db, 'SELECT id FROM tickets WHERE client=? AND closed_at IS NULL', [$actor]));
        if ($ticket === null) {
            sql($db, 'INSERT INTO tickets(client, created_at) VALUES(?,?)', [$actor, $now]);
            $id = (int) $db->lastInsertId();
            enqueue($db, 'sendMessage', ['chat_id' => $group,
                'text' => "Обращение №$id. Ответьте через Reply на карточку или копию. Для закрытия ответьте /close."], (string) $id);
            say($db, $actor, "Ваше обращение №$id принято.");
        } else {
            $id = (int) $ticket['id'];
        }
        enqueue($db, 'copyMessage', [
            'chat_id' => $group, 'from_chat_id' => $actor, 'message_id' => $messageId,
        ], (string) $id);
        return;
    }
    if ($chatId !== $group || !operatorAllowed($actor)) {
        return;
    }
    $replyId = objectOrEmpty($message['reply_to_message'] ?? null)['message_id'] ?? null;
    if (!is_int($replyId)) {
        return;
    }
    $ticket = row(sql($db, 'SELECT t.* FROM message_links m JOIN tickets t ON t.id=m.ticket_id
        WHERE m.chat_id=? AND m.message_id=?', [$group, $replyId]));
    if ($ticket === null) {
        return;
    }
    $id = (int) $ticket['id'];
    $client = (int) $ticket['client'];
    if ($ticket['closed_at'] !== null) {
        say($db, $group, "Обращение №$id уже закрыто.");
        return;
    }
    if ($text === '/close') {
        sql($db, 'UPDATE tickets SET closed_at=?, closed_by=? WHERE id=?', [$now, $actor, $id]);
        say($db, $client, "Обращение №$id закрыто. Напишите снова, если остался вопрос.");
        say($db, $group, "Обращение №$id закрыто.");
        return;
    }
    if ($text !== '' || isset($message['photo']) || isset($message['document'])
        || isset($message['voice']) || isset($message['video'])) {
        enqueue($db, 'copyMessage', [
            'chat_id' => $client, 'from_chat_id' => $group, 'message_id' => $messageId,
        ]);
    }
}

function rememberDelivery(PDO $db, string $ticket, int $messageId): void
{
    sql($db, 'INSERT INTO message_links(chat_id, message_id, ticket_id) VALUES(?,?,?)',
        [supportGroup(), $messageId, (int) $ticket]);
}
