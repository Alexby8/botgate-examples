<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/transport.php';
$url = envRequired('WEBHOOK_URL');
$secret = envRequired('WEBHOOK_SECRET');
if (!str_starts_with($url, 'https://') || !preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $secret)) {
    throw new RuntimeException('Check HTTPS and secret');
}
$settings = sendTelegram('setMySettings', ['callback_query_mode' => 'default']);
if ($settings['state'] !== 'sent') {
    throw new RuntimeException('Callback settings not confirmed');
}
$result = sendTelegram('setWebhook', ['url' => $url,'secret_token' => $secret,'allowed_updates' => ['message','callback_query'],'max_connections' => 1]);
echo $result['state'].PHP_EOL;
