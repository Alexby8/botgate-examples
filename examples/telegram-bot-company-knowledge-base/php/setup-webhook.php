<?php

declare(strict_types=1);
require __DIR__.'/common.php';
telegram('setWebhook', [
    'url' => envRequired('WEBHOOK_URL'),
    'secret_token' => envRequired('WEBHOOK_SECRET'),
    'allowed_updates' => ['message', 'callback_query'],
]);
echo "Webhook configured\n";
