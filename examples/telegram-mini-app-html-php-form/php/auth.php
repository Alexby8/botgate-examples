<?php
declare(strict_types=1);

function validateInitData(string $raw, string $token, int $now): int
{
    if ($raw === '' || strlen($raw) > 12000) {
        throw new DomainException('Invalid Telegram data');
    }
    $fields = [];
    foreach (explode('&', $raw) as $pair) {
        $parts = explode('=', $pair, 2);
        if (count($parts) !== 2) {
            throw new DomainException('Invalid Telegram data');
        }
        $key = urldecode($parts[0]);
        if (!preg_match('/^[a-zA-Z0-9_]+$/D', $key)
            || array_key_exists($key, $fields)) {
            throw new DomainException('Invalid Telegram fields');
        }
        $fields[$key] = urldecode($parts[1]);
    }
    $hash = $fields['hash'] ?? '';
    unset($fields['hash']);
    if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
        throw new DomainException('Invalid Telegram hash');
    }
    ksort($fields, SORT_STRING);
    $lines = [];
    foreach ($fields as $key => $value) {
        $lines[] = $key.'='.$value;
    }
    $secret = hash_hmac('sha256', $token, 'WebAppData', true);
    $expected = hash_hmac('sha256', implode("\n", $lines), $secret);
    if (!hash_equals($expected, $hash)) {
        throw new DomainException('Invalid Telegram signature');
    }
    $date = $fields['auth_date'] ?? '';
    if (!ctype_digit($date) || (int) $date < $now - 600 || (int) $date > $now + 30) {
        throw new DomainException('Reopen the Mini App');
    }
    try {
        $user = json_decode($fields['user'] ?? '', true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new DomainException('Invalid Telegram user');
    }
    if (!is_array($user) || !is_int($user['id'] ?? null) || $user['id'] <= 0) {
        throw new DomainException('Invalid Telegram user');
    }
    return $user['id'];
}
