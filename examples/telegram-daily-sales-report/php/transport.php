<?php

declare(strict_types=1);

/**
 * @param array<string,mixed> $params
 *
 * @return array{state:string,retry_after:int,message_id:?int,code:?int}
 */
function sendTelegram(string $method, array $params): array
{
    $curl = curl_init('https://api.telegram.org/bot'.envRequired('BOT_TOKEN').'/'.$method);
    if ($curl === false) {
        throw new RuntimeException('HTTP init failed');
    }
    curl_setopt_array($curl, [CURLOPT_POST => true,CURLOPT_POSTFIELDS => json_encode($params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],CURLOPT_RETURNTRANSFER => true,CURLOPT_CONNECTTIMEOUT => 5,CURLOPT_TIMEOUT => 20]);
    $raw = curl_exec($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    return classifyTelegram($http, is_string($raw) ? $raw : null);
}

/**
 * @return array{state:string,retry_after:int,message_id:?int,code:?int}
 */
function classifyTelegram(int $http, ?string $raw): array
{
    $unknown = ['state' => 'unknown','retry_after' => 0,'message_id' => null,'code' => null];
    if ($raw === null || $http >= 500) {
        return $unknown;
    }
    $body = json_decode($raw, true);
    if (!is_array($body)) {
        return $unknown;
    }
    $result = $body['result'] ?? null;
    if ($http === 200 && ($body['ok'] ?? false) === true) {
        $id = is_array($result) && is_int($result['message_id'] ?? null) ? $result['message_id'] : null;
        return ['state' => 'sent','retry_after' => 0,'message_id' => $id,'code' => null];
    }
    if (($body['ok'] ?? null) !== false || !is_int($body['error_code'] ?? null)) {
        return $unknown;
    }
    $code = $body['error_code'];
    $parameters = $body['parameters'] ?? null;
    if ($code === 429 && is_array($parameters) && is_int($parameters['retry_after'] ?? null) && $parameters['retry_after'] > 0) {
        return ['state' => 'retry','retry_after' => $parameters['retry_after'],'message_id' => null,'code' => $code];
    }
    return ['state' => $code >= 400 && $code < 500 ? 'failed' : 'unknown','retry_after' => 0,'message_id' => null,'code' => $code];
}
