<?php
declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/telegram.php';
require dirname(__DIR__).'/referrals.php';
$update = incomingUpdate();
$message = $update['message'] ?? null;
$chat = is_array($message) ? ($message['chat'] ?? null) : null;
$from = is_array($message) ? ($message['from'] ?? null) : null;
if (!is_int($update['update_id'] ?? null) || !is_array($message) || !is_array($chat)
    || !is_array($from) || ($chat['type'] ?? '') !== 'private'
    || !is_int($from['id'] ?? null) || ($chat['id'] ?? null) !== $from['id']) {
    reply(['ok' => true]);
}
$user = $from['id'];
$text = is_string($message['text'] ?? null) ? $message['text'] : '';
$inviteCode = preg_match('/^\/start(?:@[a-zA-Z0-9_]+)? ref_([a-f0-9]{24})$/D', $text, $match) ? $match[1] : null;
$db = database();
// Every first private message fixes whether this user arrived by referral.
$code = joinProgram($db, $user, $inviteCode, time());
$answer = $text === '/balance' ? rewardHistory($db, $user) :
    'Ваша ссылка: https://t.me/'.setting('BOT_USERNAME').'?start=ref_'.$code
    ."\n100 баллов за нового клиента после оплаченной покупки от 1000 рублей. Баланс: /balance";
if (sql($db, 'INSERT OR IGNORE INTO updates(id) VALUES(?)', [$update['update_id']])->rowCount() === 1) {
    $result = telegram('sendMessage', ['chat_id' => $user, 'text' => $answer]);
    if ($result['state'] !== 'ok') {
        error_log('referral_reply_'.$result['state'].' update='.$update['update_id']);
    }
}
reply(['ok' => true]);
