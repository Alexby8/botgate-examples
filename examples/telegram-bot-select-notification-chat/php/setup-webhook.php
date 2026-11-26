<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/telegram.php';
if (PHP_SAPI !== 'cli') exit(1);
$url = setting('WEBHOOK_URL');
if (!str_starts_with($url, 'https://')) throw new RuntimeException('HTTPS required');
$result = telegram('setWebhook', [
    'url' => $url, 'secret_token' => setting('WEBHOOK_SECRET'),
    'allowed_updates' => ['message'],
]);
if ($result['state'] !== 'ok') throw new RuntimeException('Webhook not confirmed');
echo "Webhook configured\n";
