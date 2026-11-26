<?php
declare(strict_types=1);

/**
 * @param array<string, mixed> $params
 * @return array{state: string, result?: mixed, code?: int, retry_after?: int}
 */
function telegram(string $method, array $params, bool $multipart = false): array
{
    $curl = curl_init('https://api.telegram.org/bot'.setting('BOT_TOKEN').'/'.$method);
    curl_setopt_array($curl, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8,
        CURLOPT_POSTFIELDS => $multipart ? $params : json_encode($params, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => $multipart ? [] : ['Content-Type: application/json'],
    ]);
    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if (!is_string($raw) || $status >= 500) {
        return ['state' => 'unknown'];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['state' => 'unknown'];
    }
    if (($data['ok'] ?? null) === true) {
        return ['state' => 'ok', 'result' => $data['result'] ?? null];
    }
    if (($data['ok'] ?? null) !== false || !is_int($data['error_code'] ?? null)) {
        return ['state' => 'unknown'];
    }
    $parameters = $data['parameters'] ?? null;
    $delay = is_array($parameters) ? ($parameters['retry_after'] ?? null) : null;
    return ['state' => 'rejected', 'code' => $data['error_code'],
        'retry_after' => is_int($delay) && $delay > 0 ? $delay : 60];
}

/** @return array<string, mixed> */
function incomingUpdate(): array
{
    $secret = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !is_string($secret)
        || !hash_equals(setting('WEBHOOK_SECRET'), $secret)) {
        reply(['error' => 'Forbidden'], 403);
    }
    return jsonBody();
}
