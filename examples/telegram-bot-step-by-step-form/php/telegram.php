<?php
declare(strict_types=1);

final class TelegramRejection extends RuntimeException
{
    public function __construct(int $code, public readonly int $retryAfter)
    {
        parent::__construct('Telegram rejected the request', $code);
    }
}

/** @param array<string, mixed> $payload */
function telegram(string $method, array $payload): mixed
{
    $token = getenv('BOT_TOKEN');
    if ($token === false || $token === '') {
        throw new RuntimeException('Set BOT_TOKEN');
    }
    $curl = curl_init('https://api.telegram.org/bot'.$token.'/'.$method);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ]);
    $raw = curl_exec($curl);
    if (!is_string($raw)) {
        throw new RuntimeException('Telegram result unknown');
    }
    $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($body)) {
        throw new RuntimeException('Invalid Telegram response');
    }
    if (($body['ok'] ?? null) === false && is_int($body['error_code'] ?? null)) {
        $params = $body['parameters'] ?? null;
        $delay = is_array($params) ? ($params['retry_after'] ?? null) : null;
        throw new TelegramRejection($body['error_code'], is_int($delay) ? $delay : 0);
    }
    if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200 || ($body['ok'] ?? null) !== true) {
        throw new RuntimeException('Unexpected Telegram response');
    }
    return $body['result'];
}
