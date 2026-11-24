<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/transport.php';
$url = envRequired('WEBHOOK_URL');
$secret = envRequired('WEBHOOK_SECRET');
if (!str_starts_with($url, 'https://') || !preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $secret)) {
    throw new RuntimeException('Check webhook URL and secret');
}
$result = sendTelegram('setWebhook', [
    'url' => $url, 'secret_token' => $secret, 'max_connections' => 4,
    'allowed_updates' => ['message'],
]);
echo $result['state'].PHP_EOL;
