<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\GuideBot\Dialogue;
use Illuminate\Console\Command;

final class Notify extends Command
{
    protected $signature = 'bot:notify {chat} {reference}';
    protected $description = 'Queue one translated demo notification';
    public function handle(Dialogue $dialogue): int
    {
        $chat = filter_var($this->argument('chat'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reference = $this->argument('reference');
        if ($chat === false || !is_string($reference) || !preg_match('/^[A-Za-z0-9-]{1,40}$/D', $reference)) {
            $this->error('Use a positive chat ID and a short reference.');
            return self::FAILURE;
        }
        $dialogue->notify($chat, $reference);
        $this->info('Stored in outbox; this does not confirm delivery.');
        return self::SUCCESS;
    }
}
