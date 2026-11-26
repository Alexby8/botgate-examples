<?php

declare(strict_types=1);

function telegramUser(string $raw, string $botToken, int $now): int
{
    if ($raw === '' || strlen($raw) > 16384) {
        throw new RuntimeException('Invalid initData');
    }
    $fields = [];
    foreach (explode('&', $raw) as $pair) {
        [$key,$value] = array_pad(explode('=', $pair, 2), 2, '');
        $key = urldecode($key);
        $value = urldecode($value);
        if ($key === '' || array_key_exists($key, $fields)) {
            throw new RuntimeException('Duplicate field');
        }
        $fields[$key] = $value;
    }
    $hash = $fields['hash'] ?? '';
    unset($fields['hash']);
    if (!preg_match('/^[a-f0-9]{64}$/D', $hash)) {
        throw new RuntimeException('Invalid hash');
    }
    ksort($fields, SORT_STRING);
    $lines = [];
    foreach ($fields as $key => $value) {
        $lines[] = $key.'='.$value;
    }
    $secret = hash_hmac('sha256', $botToken, 'WebAppData', true);
    if (!hash_equals(hash_hmac('sha256', implode("\n", $lines), $secret), $hash)) {
        throw new RuntimeException('Bad signature');
    }
    $date = filter_var($fields['auth_date'] ?? '', FILTER_VALIDATE_INT);
    if ($date === false || $date > $now + 30 || $date < $now - 3600) {
        throw new RuntimeException('Expired initData');
    }
    $user = json_decode($fields['user'] ?? '', true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($user) || !is_int($user['id'] ?? null) || $user['id'] <= 0) {
        throw new RuntimeException('Missing user');
    }
    return $user['id'];
}
