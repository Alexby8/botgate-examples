<?php

declare(strict_types=1);
require_once __DIR__.'/values.php';

function envRequired(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing environment: '.$name);
    }
    return $value;
}

/**
 * @param array<string, mixed>|null $data
 *
 * @param list<string> $headers
 *
 * @return array{status:int, body:array<string, mixed>}
 */
function jsonHttp(string $method, string $url, ?array $data = null, array $headers = []): array
{
    if ($method === '') {
        throw new InvalidArgumentException('Empty HTTP method');
    }
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('Cannot initialize HTTP client');
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 40,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', ...$headers],
    ]);
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
    $raw = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if (!is_string($raw)) {
        throw new RuntimeException('Transport result unknown');
    }
    if ($status === 204) {
        return ['status' => 204, 'body' => []];
    }
    $body = objectValue(json_decode($raw, true, 64, JSON_THROW_ON_ERROR));
    return ['status' => $status, 'body' => $body];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function telegram(string $method, array $params): array
{
    $response = jsonHttp('POST', 'https://api.telegram.org/bot'.envRequired('BOT_TOKEN').'/'.$method, $params);
    if ($response['status'] !== 200 || ($response['body']['ok'] ?? false) !== true) {
        throw new RuntimeException('Telegram call failed; inspect HTTP/error_code without logging token');
    }
    $message = $response['body']['result'] ?? null;
    if ($method === 'sendMessage' && (!is_array($message) || !is_int($message['message_id'] ?? null))) {
        throw new RuntimeException('Telegram did not confirm a message ID');
    }
    return $response['body'];
}

function database(): PDO
{
    $db = new PDO('sqlite:'.envRequired('APP_DB'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA busy_timeout=5000');
    return $db;
}
