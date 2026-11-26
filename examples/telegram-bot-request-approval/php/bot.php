<?php

declare(strict_types=1);

/** @param array<string, mixed> $params
 *  @return array{state: 'sent'|'retry'|'failed'|'unknown', id: ?int, delay: int}
 */
function telegram(string $method, array $params): array
{
    $token = setting('BOT_TOKEN');
    if (!preg_match('/^[0-9]+:[A-Za-z0-9_-]+$/D', $token)) {
        throw new RuntimeException('Invalid BOT_TOKEN format');
    }
    $curl = curl_init('https://api.telegram.org/bot'.$token.'/'.$method);
    if ($curl === false) {
        throw new RuntimeException('Cannot initialize cURL');
    }
    curl_setopt_array($curl, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($curl);
    $http = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $body = objectOrEmpty(is_string($raw) ? json_decode($raw, true) : null);
    if ($http >= 200 && $http < 300 && ($body['ok'] ?? false) === true) {
        $id = objectOrEmpty($body['result'] ?? null)['message_id'] ?? null;
        return ['state' => 'sent', 'id' => is_int($id) ? $id : null, 'delay' => 0];
    }
    if (($body['ok'] ?? null) === false && ($body['error_code'] ?? null) === 429) {
        $delay = objectOrEmpty($body['parameters'] ?? null)['retry_after'] ?? null;
        return ['state' => 'retry', 'id' => null, 'delay' => is_int($delay) ? max(1, $delay) : 30];
    }
    if (($body['ok'] ?? null) === false && $http >= 400 && $http < 500) {
        return ['state' => 'failed', 'id' => null, 'delay' => 0];
    }
    return ['state' => 'unknown', 'id' => null, 'delay' => 0];
}

/** @param array<string, mixed> $params */
function enqueue(PDO $db, string $method, array $params, ?string $binding = null): void
{
    sql($db, 'INSERT INTO outbox(method, payload, binding) VALUES(?,?,?)',
        [$method, json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $binding]);
}

function say(PDO $db, int $chat, string $text): void
{
    enqueue($db, 'sendMessage', ['chat_id' => $chat, 'text' => $text]);
}

/** @param list<list<array{text: string, callback_data: string}>> $buttons */
function menu(PDO $db, int $chat, string $text, array $buttons, ?string $binding = null): void
{
    enqueue($db, 'sendMessage', ['chat_id' => $chat, 'text' => $text, 'reply_markup' => ['inline_keyboard' => $buttons]], $binding);
}
