<?php

declare(strict_types=1);

namespace App\GuideBot;

use Illuminate\Support\Facades\DB;

final class Outbox
{
    /** @param array<string, mixed> $payload */
    public static function add(int $chatId, string $key, array $payload, string $kind = 'reply', int $revision = 0): void
    {
        DB::table('demo_outbox')->insertOrIgnore([
            'chat_id' => $chatId, 'event_key' => $key,
            'payload' => json_encode(['chat_id' => $chatId] + $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'kind' => $kind, 'revision' => $revision, 'state' => 'pending',
            'attempts' => 0, 'next_at' => now()->getTimestamp(),
        ]);
    }
}
