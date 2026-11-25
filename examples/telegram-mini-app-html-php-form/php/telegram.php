<?php
declare(strict_types=1);

function botToken(): string
{
    $token = getenv('BOT_TOKEN');
    if (!is_string($token) || $token === '') {
        throw new RuntimeException('Set BOT_TOKEN in the PHP process');
    }
    return $token;
}

/** @param array<string, mixed> $params */
function telegram(string $method, array $params): mixed
{
    $curl = curl_init('https://api.telegram.org/bot'.botToken().'/'.$method);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params, JSON_THROW_ON_ERROR),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 8,
    ]);
    try {
        $raw = curl_exec($curl);
        if (!is_string($raw)) {
            throw new RuntimeException('Telegram result unknown');
        }
        $body = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($body) || !is_bool($body['ok'] ?? null)) {
            throw new RuntimeException('Invalid Telegram response');
        }
        if ($body['ok'] === false) {
            throw new UnexpectedValueException('Telegram rejected request');
        }
        if (curl_getinfo($curl, CURLINFO_HTTP_CODE) !== 200) {
            throw new RuntimeException('Unexpected HTTP status');
        }
        return $body['result'];
    } finally {
        unset($curl);
    }
}

function notifyLead(int $userId, int $leadId): string
{
    try {
        telegram('sendMessage', [
            'chat_id' => $userId,
            'text' => 'Заявка №'.$leadId.' сохранена. Спасибо за обращение!',
        ]);
        return 'sent';
    } catch (UnexpectedValueException) {
        return 'rejected';
    } catch (Throwable) {
        return 'unknown';
    }
}
