<?php

declare(strict_types=1);

namespace App\GuideBot;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class Webhook
{
    public function __invoke(Request $request, Dialogue $dialogue): JsonResponse
    {
        $secret = config('guidebot.secret');
        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');
        abort_unless(is_string($secret) && $secret !== ''
            && is_string($provided) && hash_equals($secret, $provided), 403);
        abort_if(strlen($request->getContent()) > 131072, 413);
        $update = $request->json()->all();
        $event = Event::from($update);
        if ($event === null) {
            return response()->json(['ok' => true]);
        }
        DB::transaction(function () use ($event, $dialogue): void {
            if (!DB::table('demo_updates')->insertOrIgnore(['update_id' => $event->updateId])) {
                return;
            }
            $dialogue->handle($event);
        }, 3);
        // Telegram executes this method after receiving the webhook response.
        // Its result is not available here; business replies use the outbox.
        return response()->json($event->callbackId === null ? ['ok' => true] : [
            'method' => 'answerCallbackQuery', 'callback_query_id' => $event->callbackId,
        ]);
    }
}
