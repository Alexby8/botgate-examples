<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\GuideBot\{Dialogue, Telegram};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class Deliver extends Command
{
    protected $signature = 'bot:deliver {--watch}';
    protected $description = 'Deliver persisted demo messages';

    public function handle(Telegram $telegram, Dialogue $dialogue): int
    {
        do {
            $ids = DB::table('demo_outbox')->whereIn('state', ['pending', 'retry'])
                ->where('next_at', '<=', now()->getTimestamp())->orderBy('id')->limit(50)->pluck('id');
            foreach ($ids as $id) {
                $row = DB::transaction(function () use ($id, $dialogue): ?\stdClass {
                    $row = DB::table('demo_outbox')->where('id', $id)->lockForUpdate()->first();
                    if (!$row || !in_array($row->state, ['pending', 'retry'], true)
                        || (int) $row->next_at > now()->getTimestamp()) {
                        return null;
                    }
                    if (!$dialogue->canSend($row)) {
                        DB::table('demo_outbox')->where('id', $id)->update(['state' => 'cancelled']);
                        return null;
                    }
                    DB::table('demo_outbox')->where('id', $id)->update([
                        'state' => 'sending', 'attempts' => (int) $row->attempts + 1,
                    ]);
                    return $row;
                });
                if (!$row instanceof \stdClass) {
                    continue;
                }
                try {
                    /** @var array<string, mixed> $payload */
                    $payload = json_decode((string) $row->payload, true, 512, JSON_THROW_ON_ERROR);
                    $result = $telegram->call('sendMessage', $payload);
                } catch (\Throwable) {
                    $result = ['state' => 'unknown', 'delay' => 0, 'message_id' => null, 'code' => null];
                }
                if ($result['state'] === 'retry' && (int) $row->attempts + 1 >= 5) {
                    $result['state'] = 'failed';
                }
                DB::table('demo_outbox')->where('id', $id)->update([
                    'state' => $result['state'], 'next_at' => now()->getTimestamp() + $result['delay'],
                    'message_id' => $result['message_id'], 'error_code' => $result['code'],
                ]);
            }
            if ($this->option('watch')) {
                usleep(500000);
            }
        } while ($this->option('watch'));
        return self::SUCCESS;
    }
}
