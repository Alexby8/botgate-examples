<?php

declare(strict_types=1);

require __DIR__.'/common.php';
require __DIR__.'/bot.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$url = setting('WEBHOOK_URL');
$secret = setting('WEBHOOK_SECRET');
if (!str_starts_with($url, 'https://') || !filter_var($url, FILTER_VALIDATE_URL)
    || !preg_match('/^[A-Za-z0-9_-]{1,256}$/D', $secret)) {
    throw new InvalidArgumentException('Check HTTPS WEBHOOK_URL and WEBHOOK_SECRET');
}
$state = telegram('setWebhook', [
    'url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message','callback_query'],
])['state'];
echo $state === 'sent' ? "Webhook registered\n" : "Webhook registration: ".$state."\n";
exit($state === 'sent' ? 0 : 1);
