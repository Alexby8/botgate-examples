<?php
declare(strict_types=1);
require dirname(__DIR__).'/common.php';
require dirname(__DIR__).'/telegram.php';
require dirname(__DIR__).'/tickets.php';
require dirname(__DIR__).'/vendor/autoload.php';

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
$db = database();
if (($message['text'] ?? '') !== '/ticket') {
    reply(['ok' => true]);
}
try {
    $token = registerTicket($db, 1, $user, setting('TICKET_KEY'), time());
} catch (DomainException) {
    reply(['ok' => true]);
}
$png = (new Endroid\QrCode\Writer\PngWriter())->write(
    new Endroid\QrCode\QrCode(data: $token, size: 400, margin: 16)
)->getString();
// A repeated webhook must not send another document automatically.
$claimed = sql($db, 'INSERT OR IGNORE INTO updates(id) VALUES(?)', [$update['update_id']])->rowCount();
if ($claimed === 0) {
    reply(['ok' => true]);
}
$result = telegram('sendDocument', [
    'chat_id' => $user,
    'document' => new CURLStringFile($png, 'ticket.png', 'image/png'),
    'caption' => 'Билет на встречу разработчиков. Покажите QR на входе. Копия билета не даёт второго прохода.',
], true);
if ($result['state'] !== 'ok') {
    error_log('ticket_delivery_'.$result['state'].' update='.$update['update_id']);
}
reply(['ok' => true]);
