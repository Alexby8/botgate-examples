<?php
declare(strict_types=1);

function checkDestination(int $owner, int $chat, string $kind): void
{
    $details = apiObject('getChat', ['chat_id' => $chat]);
    $type = $details['type'] ?? '';
    if ($kind === 'channel' ? $type !== 'channel' : !in_array($type, ['group', 'supergroup'], true)) {
        throw new DomainException('Тип чата изменился');
    }
    if (($details['is_forum'] ?? false) === true) {
        throw new DomainException('Для форума выберите также тему в отдельном сценарии');
    }
    // Requiring the bot to be admin makes getChatMember checks reliable for other users.
    $bot = apiObject('getChatMember', ['chat_id' => $chat, 'user_id' => (int) setting('BOT_ID')]);
    if (!isAdmin($bot) || ($kind === 'channel' && ($bot['can_post_messages'] ?? false) !== true)) {
        throw new DomainException('Добавьте бота администратором с правом отправки');
    }
    $member = apiObject('getChatMember', ['chat_id' => $chat, 'user_id' => $owner]);
    if (($member['status'] ?? '') !== 'creator') {
        throw new DomainException('Подключать чат в этом примере может только его владелец');
    }
}

function requestDestination(PDO $db, int $user, string $kind, int $update): void
{
    $id = random_int(1, 2147483647);
    sql($db, "UPDATE chat_requests SET state='cancelled' WHERE user_id=? AND state='pending'", [$user]);
    sql($db, 'INSERT INTO chat_requests(id,user_id,kind,expires_at) VALUES(?,?,?,?)', [$id, $user, $kind, time()+600]);
    telegramOnce($db, 'choose:'.$update, 'sendMessage', [
        'chat_id' => $user, 'text' => 'Выберите свой чат. Бот должен быть добавлен администратором. Запрос действует 10 минут.',
        'reply_markup' => ['keyboard' => [[['text' => 'Выбрать '.($kind === 'channel' ? 'канал' : 'группу'),
            'request_chat' => ['request_id' => $id, 'chat_is_channel' => $kind === 'channel',
                'chat_is_created' => true, 'bot_is_member' => true, 'chat_is_forum' => false, 'request_title' => true]]]],
            'resize_keyboard' => true, 'one_time_keyboard' => true],
    ]);
}

function connectDestination(PDO $db, int $user, int $request, int $chat, int $update): void
{
    $pending = row(sql($db, "SELECT * FROM chat_requests WHERE id=? AND user_id=? AND state='pending' AND expires_at>?",
        [$request, $user, time()]));
    if ($pending === null) {
        telegramOnce($db, 'stale:'.$update, 'sendMessage', ['chat_id' => $user, 'text' => 'Запрос устарел. Используйте /group или /channel.']);
        return;
    }
    sql($db, "UPDATE chat_requests SET state='checking',selected_chat=? WHERE id=?", [$chat, $request]);
    try {
        checkDestination($user, $chat, (string) $pending['kind']);
        $test = telegramOnce($db, 'test:'.$request, 'sendMessage', ['chat_id' => $chat,
            'text' => 'Проверка подключения уведомлений. Этот чат выбран его владельцем.']);
        $ok = $test['state'] === 'ok';
        atomic($db, function () use ($db, $ok, $user, $chat, $pending, $request): void {
            if ($ok) {
                sql($db, 'INSERT INTO integrations VALUES(?,?,?,1,?) ON CONFLICT(user_id) DO UPDATE SET
                    chat_id=excluded.chat_id,kind=excluded.kind,active=1,confirmed_at=excluded.confirmed_at',
                    [$user, $chat, (string) $pending['kind'], time()]);
            }
            sql($db, 'UPDATE chat_requests SET state=? WHERE id=?', [$ok ? 'confirmed' : 'unconfirmed', $request]);
        });
        $text = $ok ? 'Чат подключён. Отключить: /disconnect' : 'Пробная отправка не подтверждена. Интеграция не включена.';
    } catch (Throwable) {
        sql($db, "UPDATE chat_requests SET state='failed' WHERE id=?", [$request]);
        $text = 'Не удалось проверить доступ. Проверьте владельца чата и права бота, затем начните выбор заново.';
    }
    telegramOnce($db, 'connection-result:'.$request, 'sendMessage', ['chat_id' => $user, 'text' => $text,
        'reply_markup' => ['remove_keyboard' => true]]);
}

/** @param array<array-key,mixed> $update */
function handleUpdate(PDO $db, array $update): void
{
    $m = $update['message'] ?? null;
    if (!is_array($m) || !is_array($m['chat'] ?? null) || !is_array($m['from'] ?? null)
        || ($m['chat']['type'] ?? '') !== 'private' || !is_int($m['from']['id'] ?? null)
        || ($m['chat']['id'] ?? null) !== $m['from']['id'] || !is_int($update['update_id'] ?? null)) {
        return;
    }
    $user = $m['from']['id']; $id = $update['update_id']; $text = $m['text'] ?? '';
    if ($text === '/disconnect') {
        atomic($db, function () use ($db, $user): void {
            sql($db, 'UPDATE integrations SET active=0 WHERE user_id=?', [$user]);
            sql($db, "UPDATE chat_requests SET state='cancelled' WHERE user_id=? AND state='pending'", [$user]);
        });
        telegramOnce($db, 'disconnect:'.$id, 'sendMessage', ['chat_id' => $user, 'text' => 'Уведомления отключены.',
            'reply_markup' => ['remove_keyboard' => true]]);
    } elseif ($text === '/group' || $text === '/channel') {
        requestDestination($db, $user, $text === '/channel' ? 'channel' : 'group', $id);
    } elseif (is_array($m['chat_shared'] ?? null) && is_int($m['chat_shared']['request_id'] ?? null)
        && is_int($m['chat_shared']['chat_id'] ?? null)) {
        connectDestination($db, $user, $m['chat_shared']['request_id'], $m['chat_shared']['chat_id'], $id);
    }
}
