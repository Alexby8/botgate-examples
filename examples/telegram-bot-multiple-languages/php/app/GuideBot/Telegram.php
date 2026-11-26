<?php

declare(strict_types=1);

namespace App\GuideBot;

use Illuminate\Support\Facades\Http;

final class Telegram
{
    /** @param array<string, mixed> $payload
     *  @return array{state:string, delay:int, message_id:?int, code:?int} */
    public function call(string $method, array $payload): array
    {
        $unknown = ['state' => 'unknown', 'delay' => 0, 'message_id' => null, 'code' => null];
        $token = config('guidebot.token');
        if (!is_string($token) || $token === '') {
            throw new \LogicException('BOT_TOKEN is missing');
        }
        try {
            $response = Http::connectTimeout(5)->timeout(20)
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $payload);
            $body = $response->json();
        } catch (\Throwable) {
            return $unknown;
        }
        if (!is_array($body) || $response->serverError()) {
            return $unknown;
        }
        $result = $body['result'] ?? null;
        $messageId = is_array($result) && is_int($result['message_id'] ?? null) ? $result['message_id'] : null;
        if ($response->successful() && ($body['ok'] ?? null) === true
            && ($method !== 'sendMessage' || $messageId !== null)) {
            return ['state' => 'sent', 'delay' => 0, 'message_id' => $messageId, 'code' => null];
        }
        $code = $body['error_code'] ?? null;
        $parameters = $body['parameters'] ?? null;
        $delay = is_array($parameters) ? ($parameters['retry_after'] ?? null) : null;
        if (($body['ok'] ?? null) === false && $code === 429 && is_int($delay) && $delay > 0) {
            return ['state' => 'retry', 'delay' => $delay, 'message_id' => null, 'code' => 429];
        }
        if (($body['ok'] ?? null) === false && is_int($code) && $code >= 400 && $code < 500) {
            return ['state' => 'failed', 'delay' => 0, 'message_id' => null, 'code' => $code];
        }
        return $unknown;
    }
}
