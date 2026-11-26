<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require __DIR__.'/common.php';
$url = getenv('WEBHOOK_URL');
$secret = getenv('WEBHOOK_SECRET');
if (!$url || !str_starts_with($url, 'https://') || !$secret) {
    throw new RuntimeException('Задайте HTTPS URL и WEBHOOK_SECRET');
}
$answer = telegram('setWebhook', ['url' => $url, 'secret_token' => $secret,
    'allowed_updates' => ['message', 'callback_query']]);
if ($answer['state'] !== 'ok' || ($answer['result'] ?? null) !== true) {
    throw new RuntimeException('Webhook не подтверждён; проверьте getWebhookInfo');
}
echo "Webhook установлен\n";
