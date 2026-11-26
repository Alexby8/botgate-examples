<?php
declare(strict_types=1);
require dirname(__DIR__).'/stock.php';

try {
    $update = incomingUpdate();
    $query = $update['callback_query'] ?? null;
    $updateId = $update['update_id'] ?? null;
    $from = is_array($query) ? ($query['from'] ?? null) : null;
    $message = is_array($query) ? ($query['message'] ?? null) : null;
    $chat = is_array($message) ? ($message['chat'] ?? null) : null;
    if (!is_array($query) || !is_string($query['id'] ?? null)
        || !is_array($from) || !is_int($from['id'] ?? null)
        || !is_array($message) || !is_int($message['message_id'] ?? null)
        || !is_array($chat) || ($chat['type'] ?? null) !== 'private'
        || ($chat['id'] ?? null) !== $from['id'] || !is_int($updateId)) {
        http_response_code(200);
        return;
    }

    $db = database();
    $user = $from['id'];
    $text = 'Кнопка устарела.';
    $productId = null;
    $data = $query['data'] ?? '';
    if (is_string($data) && preg_match('/\Awatch:([1-9][0-9]{0,9})\z/', $data, $matches)) {
        $productId = (int) $matches[1];
        $text = subscribe($db, $user, $productId, $updateId);
    } elseif (is_string($data) && preg_match('/\Aunwatch:([1-9][0-9]{0,9}):([1-9][0-9]{0,9})\z/', $data, $matches)) {
        unsubscribe($db, $user, (int) $matches[1], (int) $matches[2]);
        $find = $db->prepare('SELECT product_id FROM watches WHERE id = ? AND user_id = ?');
        $find->execute([(int) $matches[1], $user]);
        $found = $find->fetchColumn();
        $productId = is_int($found) ? $found : null;
        $text = 'Этот запрос отмены обработан. Новые подписки не изменены.';
    }

    telegram('answerCallbackQuery', ['callback_query_id' => $query['id'], 'text' => $text], 3);
    if ($productId !== null) {
        try {
            $card = watchCard($db, $user, $productId);
        } catch (InvalidArgumentException) {
            http_response_code(200);
            return;
        }
        telegram('editMessageReplyMarkup', ['chat_id' => $user,
            'message_id' => $message['message_id'], 'reply_markup' => $card['reply_markup']], 3);
    }
    http_response_code(200);
} catch (Throwable) {
    http_response_code(503);
}
