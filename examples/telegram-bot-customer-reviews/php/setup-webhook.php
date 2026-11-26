<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/transport.php';
$result = sendTelegram('setWebhook', ['url' => envRequired('WEBHOOK_URL'),'secret_token' => envRequired('WEBHOOK_SECRET'),'allowed_updates' => ['message','callback_query']]);
if ($result['state'] !== 'sent') {
    throw new RuntimeException('Webhook configuration not confirmed');
}
echo "Webhook configured\n";
