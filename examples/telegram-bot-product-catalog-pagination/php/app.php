<?php

declare(strict_types=1);

require __DIR__.'/catalog-page.php';

/** @param array<string, mixed> $update */
function handle(PDO $db, array $update, int $now): void
{
    $a = privateAction($update);
    if ($a === null) {
        return;
    }
    $chat = $a['chat'];
    if (!$a['callback'] && in_array($a['text'], ['/start','/catalog'], true)) {
        menu($db, $chat, 'Выберите раздел', [[['text'=>'Канцелярия', 'callback_data'=>'category:1']],
            [['text'=>'Пустой раздел', 'callback_data'=>'category:2']]]);
        return;
    }
    if (!$a['callback']) {
        return;
    }
    if (preg_match('/^category:([12])$/D', $a['text'], $m)) {
        openCatalog($db, $chat, (int) $m[1], $now);
        return;
    }
    if (!preg_match('/^nav:([a-f0-9]{16}):([0-9]{1,9}):([0-9]{1,6})$/D', $a['text'], $m)) {
        return;
    }
    switchPage($db, $chat, $a['message'], $m[1], (int) $m[2], (int) $m[3], $now);
}

function openCatalog(PDO $db, int $chat, int $category, int $now): void
{
    $version = row(sql($db, 'SELECT version FROM categories WHERE id=?', [$category]));
    if ($version === null) {
        say($db, $chat, 'Раздел закрыт. Откройте /catalog.');
        return;
    }
    $token = bin2hex(random_bytes(8));
    sql($db, 'DELETE FROM sessions WHERE chat_id=?', [$chat]);
    sql($db, 'INSERT INTO sessions(token,chat_id,category_id,catalog_version,expires_at) VALUES(?,?,?,?,?)',
        [$token,$chat,$category,$version['version'],$now+900]);
    $page = catalogPage($db, $category, 0, $token, 1);
    enqueue($db, 'sendMessage', ['chat_id'=>$chat, 'text'=>$page['text'], 'reply_markup'=>$page['reply_markup']], $token);
}

function switchPage(PDO $db, int $chat, int $message, string $token, int $revision, int $page, int $now): void
{
    $session = row(sql($db, 'SELECT s.*,c.version FROM sessions s JOIN categories c ON c.id=s.category_id
        WHERE s.token=? AND s.chat_id=? AND s.message_id=?', [$token,$chat,$message]));
    if ($session === null || (int) $session['expires_at'] <= $now
        || (int) $session['revision'] !== $revision || $session['catalog_version'] !== $session['version']
        || abs($page - (int) $session['page']) !== 1) {
        say($db, $chat, 'Кнопка устарела. Откройте /catalog и выберите раздел.');
        return;
    }
    $next = catalogPage($db, (int) $session['category_id'], $page, $token, $revision + 1);
    sql($db, 'UPDATE sessions SET revision=?, page=? WHERE token=?', [$revision+1,$next['page'],$token]);
    enqueue($db, 'editMessageText', [
        'chat_id'=>$chat, 'message_id'=>$message, 'text'=>$next['text'], 'reply_markup'=>$next['reply_markup'],
    ]);
}

function rememberDelivery(PDO $db, string $token, int $message): void
{
    sql($db, 'UPDATE sessions SET message_id=? WHERE token=?', [$message,$token]);
}
