<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\GuideBot\Telegram;
use Illuminate\Console\Command;

final class SetupWebhook extends Command
{
    protected $signature = 'bot:webhook';
    protected $description = 'Register this demo webhook';
    public function handle(Telegram $telegram): int
    {
        $url = config('app.url');
        $secret = config('guidebot.secret');
        if (!is_string($url) || !str_starts_with($url, 'https://') || !is_string($secret)
            || !preg_match('/^[A-Za-z0-9_-]{32,256}$/D', $secret)) {
            $this->error('Set HTTPS APP_URL and a random BOT_WEBHOOK_SECRET first.');
            return self::FAILURE;
        }
        $result = $telegram->call('setWebhook', [
            'url' => rtrim($url, '/').'/api/telegram', 'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'], 'max_connections' => 1,
        ]);
        $this->line($result['state']);
        return $result['state'] === 'sent' ? self::SUCCESS : self::FAILURE;
    }
}
