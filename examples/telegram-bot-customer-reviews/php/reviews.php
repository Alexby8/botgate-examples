<?php

declare(strict_types=1);

/**
 * @param array<string,mixed> $payload
 */
function enqueue(PDO $db, string $key, ?int $reviewId, string $kind, array $payload): void
{
    $db->prepare('INSERT OR IGNORE INTO outbox(dedupe_key,review_id,kind,payload) VALUES(?,?,?,?)')
        ->execute([$key,$reviewId,$kind,json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)]);
}
function invite(PDO $db, string $orderId): void
{
    $db->beginTransaction();
    try {
        $q = $db->prepare("SELECT * FROM orders WHERE id=? AND status='completed'");
        $q->execute([$orderId]);
        $order = $q->fetch(PDO::FETCH_ASSOC);
        if (!is_array($order)) {
            throw new RuntimeException('Completed order not found');
        }
        $token = bin2hex(random_bytes(8));
        $db->prepare('INSERT OR IGNORE INTO reviews(order_id,user_id,token,expires_at) VALUES(?,?,?,?)')
            ->execute([$orderId,$order['user_id'],$token,time() + 14 * 86400]);
        $q = $db->prepare('SELECT * FROM reviews WHERE order_id=?');
        $q->execute([$orderId]);
        $r = objectValue($q->fetch(PDO::FETCH_ASSOC));
        $buttons = [];
        for ($score = 1;$score <= 5;$score++) {
            $buttons[] = ['text' => (string)$score,'callback_data' => 'rate:'.stringValue($r['token']).':'.$score];
        }
        enqueue($db, 'invite:'.intValue($r['id']), intValue($r['id']), 'invite', [
            'chat_id' => $order['user_id'],'text' => 'Заказ '.$orderId.' выполнен. Оцените работу от 1 (плохо) до 5 (отлично). Ответ добровольный, публикация обсуждается отдельно.',
            'reply_markup' => ['inline_keyboard' => [$buttons]]]);
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/**
 * Вызывается внутри транзакции inbox.
 *
 * @param array<string,mixed> $update
 */
function reviewUpdate(PDO $db, array $update): void
{
    $callback = $update['callback_query'] ?? null;
    $isCallback = is_array($callback);
    $message = $isCallback ? ($callback['message'] ?? null) : ($update['message'] ?? null);
    if (!is_array($message) || !is_array($message['chat'] ?? null) || $message['chat']['type'] !== 'private') {
        return;
    }
    $user = intValue($message['chat']['id']);
    $from = $isCallback ? ($callback['from'] ?? null) : ($message['from'] ?? null);
    if (!is_array($from) || ($from['id'] ?? null) !== $user) {
        return;
    }
    $text = $isCallback ? ($callback['data'] ?? '') : ($message['text'] ?? '');
    if (!is_string($text)) {
        return;
    }
    $action = '';
    $value = '';
    $token = '';
    if ($isCallback && preg_match('/^(rate|skip|publish):([a-f0-9]{16})(?::([0-5]))?$/D', $text, $m)) {
        $action = $m[1];
        $token = $m[2];
        $value = $m[3] ?? '';
    } elseif (!$isCallback && preg_match('/^\/comment ([a-f0-9]{16}) ([\s\S]+)$/uD', $text, $m)) {
        $action = 'comment';
        $token = $m[1];
        $value = trim($m[2]);
    } elseif (!$isCallback && is_array($message['reply_to_message'] ?? null)) {
        $q = $db->prepare('SELECT token FROM reviews WHERE user_id=? AND prompt_message_id=? AND completed_at IS NULL');
        $q->execute([$user,intValue($message['reply_to_message']['message_id'] ?? 0)]);
        $found = $q->fetchColumn();
        if (is_string($found)) {
            $action = 'comment';
            $token = $found;
            $value = trim($text);
        }
    }
    $updateId = intValue($update['update_id']);
    $reply = static function (string $text) use ($db, $user, $updateId): void {
        enqueue($db, 'ack:'.$updateId, null, 'reply', ['chat_id' => $user,'text' => $text]);
    };
    if ($token === '') {
        $reply('Откройте приглашение к вашему заказу. Комментарий отправляйте ответом на вопрос бота.');
        return;
    }
    $q = $db->prepare('SELECT * FROM reviews WHERE token=? AND user_id=?');
    $q->execute([$token,$user]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if (!is_array($r)) {
        $reply('Приглашение недоступно.');
        return;
    }
    $id = intValue($r['id']);
    if ($action !== 'publish' && intValue($r['expires_at']) < time()) {
        $reply('Срок приглашения истёк.');
        return;
    }
    if ($action === 'rate') {
        $score = (int)$value;
        if ($score < 1 || $score > 5) {
            return;
        }
        if ($r['score'] !== null) {
            $reply('Оценка уже принята и не изменена. Ответьте на вопрос о комментарии или используйте /comment '.$token.' ваш текст');
            return;
        }
        $db->prepare('UPDATE reviews SET score=? WHERE id=?')->execute([$score,$id]);
        enqueue($db, 'prompt:'.$id, $id, 'prompt', ['chat_id' => $user,
            'text' => 'Оценка '.$score.' для заказа '.stringValue($r['order_id']).' сохранена. Ответьте на это сообщение комментарием до 2000 символов. Если ответ не привязался: /comment '.$token.' ваш текст',
            'reply_markup' => ['force_reply' => true,'input_field_placeholder' => 'Что понравилось или стоит улучшить?']]);
        enqueue($db, 'skip:'.$id, $id, 'reply', ['chat_id' => $user,'text' => 'Комментарий необязателен.',
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Без комментария','callback_data' => 'skip:'.$token]]]]]);
        if ($score <= 2) {
            enqueue($db, 'low:'.$id, $id, 'manager', ['chat_id' => envRequired('MANAGER_CHAT_ID'),
                'text' => 'Низкая оценка '.$score.'/5 · заказ '.stringValue($r['order_id']).' · отзыв №'.$id.'. Проверьте обращение в рабочей системе.']);
        }
        return;
    }
    if ($action === 'comment' || $action === 'skip') {
        if ($r['score'] === null) {
            $reply('Сначала поставьте оценку.');
            return;
        }
        if ($r['completed_at'] !== null) {
            $reply('Отзыв уже завершён. Текст не изменён.');
            return;
        }
        $comment = $action === 'skip' ? '' : $value;
        if ($action === 'comment' && (mb_strlen($comment) < 1 || mb_strlen($comment) > 2000)) {
            $reply('Комментарий должен содержать от 1 до 2000 символов.');
            return;
        }
        $db->prepare('UPDATE reviews SET comment=?,completed_at=? WHERE id=?')->execute([$comment,time(),$id]);
        if ($comment === '') {
            $reply('Спасибо! Оценка сохранена без комментария.');
            return;
        }
        enqueue($db, 'consent:'.$id, $id, 'reply', ['chat_id' => $user,
            'text' => 'Спасибо! Разрешаете опубликовать этот текст и оценку на сайте компании без Telegram ID и username? По умолчанию отзыв остаётся внутренним. Выбор можно изменить этими же кнопками.',
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Разрешаю','callback_data' => 'publish:'.$token.':1'],['text' => 'Не публиковать / отозвать','callback_data' => 'publish:'.$token.':0']]]]]);
        return;
    }
    if ($action === 'publish' && in_array($value, ['0','1'], true)) {
        if ($r['completed_at'] === null || $r['comment'] === '') {
            $reply('Нет завершённого комментария для публикации.');
            return;
        }
        $db->prepare('UPDATE reviews SET publication_allowed=?,consent_version=?,consent_at=? WHERE id=?')->execute([(int)$value,'site-text-score-anonymous-v1',time(),$id]);
        $reply($value === '1' ? 'Разрешение сохранено. Публикация возможна после проверки менеджером.' : 'Публикация запрещена. Ранее опубликованный отзыв нужно снять с сайта.');
    }
}
