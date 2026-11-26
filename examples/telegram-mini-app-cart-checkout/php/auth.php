<?php
declare(strict_types=1);

function miniUser(string $raw, string $token, int $now): int
{
    if ($raw === '' || strlen($raw) > 16384) {
        throw new InvalidArgumentException('Open Mini App again');
    }
    $fields = [];
    foreach (explode('&', $raw) as $part) {
        $pair = explode('=', $part, 2);
        if (count($pair) !== 2) {
            throw new InvalidArgumentException('Invalid initData');
        }
        $key = urldecode($pair[0]);
        if (array_key_exists($key, $fields)) {
            throw new InvalidArgumentException('Duplicate initData field');
        }
        $fields[$key] = urldecode($pair[1]);
    }
    $hash = $fields['hash'] ?? '';
    unset($fields['hash']);
    ksort($fields, SORT_STRING);
    $lines = [];
    foreach ($fields as $key => $value) {
        $lines[] = $key.'='.$value;
    }
    $secret = hash_hmac('sha256', $token, 'WebAppData', true);
    $expected = hash_hmac('sha256', implode("\n", $lines), $secret);
    $date = $fields['auth_date'] ?? '';
    if (!preg_match('/^[a-f0-9]{64}$/D', $hash) || !hash_equals($expected, $hash)
        || !ctype_digit($date) || (int) $date < $now - 600 || (int) $date > $now + 30) {
        throw new InvalidArgumentException('Expired or invalid initData');
    }
    $user = json_decode($fields['user'] ?? '', true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($user) || !is_int($user['id'] ?? null) || $user['id'] < 1) {
        throw new InvalidArgumentException('Missing Telegram user');
    }
    return $user['id'];
}
