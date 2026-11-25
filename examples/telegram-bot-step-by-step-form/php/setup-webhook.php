<?php
declare(strict_types=1);
require __DIR__.'/telegram.php';

$url = getenv('WEBHOOK_URL');
$secret = getenv('WEBHOOK_SECRET');
if (!$url || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
    throw new RuntimeException('Set HTTPS WEBHOOK_URL including /webhook.php');
}
if (!$secret || !preg_match('/^[a-zA-Z0-9_-]{1,256}$/D', $secret)) {
    throw new RuntimeException('Set WEBHOOK_SECRET');
}

telegram('setWebhook', [
    'url' => $url, 'secret_token' => $secret,
    'allowed_updates' => ['message'], 'max_connections' => 1,
]);
echo "Webhook configured\n";
