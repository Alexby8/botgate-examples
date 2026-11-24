<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/transport.php';
$mode = $argv[1] ?? '';
if ($mode === 'menu') {
    $url = envRequired('MINI_APP_URL');
    if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
        throw new RuntimeException('MINI_APP_URL must be an HTTPS URL');
    }
    $result = sendTelegram('setChatMenuButton', [
        'menu_button' => ['type' => 'web_app', 'text' => 'Рассчитать', 'web_app' => ['url' => $url]],
    ]);
    echo $result['state'].PHP_EOL;
    exit($result['state'] === 'sent' ? 0 : 1);
}
if ($mode !== 'ids') {
    fwrite(STDERR, "Usage: php connect.php ids|menu\n");
    exit(1);
}
// Только для нового тестового бота без webhook; offset не передаём.
$curl = curl_init('https://api.telegram.org/bot'.envRequired('BOT_TOKEN').'/getUpdates');
if ($curl === false) {
    throw new RuntimeException('HTTP init');
}
curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => 'timeout=0&limit=100',
    CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 10]);
$raw = curl_exec($curl);
$body = is_string($raw) ? objectValue(json_decode($raw, true, 512, JSON_THROW_ON_ERROR)) : [];
if (($body['ok'] ?? false) !== true || !is_array($body['result'] ?? null)) {
    fwrite(STDERR, "Не получены обновления. Проверьте токен и отсутствие webhook у тестового бота.\n");
    exit(1);
}
$ids = [];
foreach ($body['result'] as $value) {
    $u = objectValue($value);
    $m = objectValue($u['message'] ?? []);
    $chat = objectValue($m['chat'] ?? []);
    if (($chat['type'] ?? '') === 'private' && ($m['text'] ?? '') === '/start') {
        $ids[intValue($chat['id'])] = true;
    }
}
echo $ids === [] ? "Сначала отправьте /start новому тестовому боту.\n" : 'Private /start chat IDs: '.implode(', ', array_keys($ids)).PHP_EOL;
