<?php
declare(strict_types=1);

function database(): PDO
{
    $path = getenv('BOT_DB_PATH');
    if (!$path || !is_file($path)) throw new RuntimeException('Создайте БД и задайте BOT_DB_PATH');
    $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA foreign_keys = ON');
    $db->exec('PRAGMA busy_timeout = 1000');
    return $db;
}

/** @template T
 * @param callable(): T $work
 * @return T
 */
function atomic(PDO $db, callable $work): mixed
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $work();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}

/** @return array<string, int|string|null>|false */
function row(PDOStatement $query): array|false
{
    $value = $query->fetch(PDO::FETCH_ASSOC);
    if ($value === false) return false;
    if (!is_array($value)) throw new RuntimeException('Некорректная строка БД');
    foreach ($value as $key => $field) {
        if (!is_string($key) || !(is_int($field) || is_string($field) || $field === null)) {
            throw new RuntimeException('Некорректное поле БД');
        }
    }
    return $value;
}

/** @param array<string, mixed> $params
 * @return array{state: string, result?: mixed, code?: int, retry_after?: int}
 */
function telegram(string $method, array $params, int $timeout = 8): array
{
    $token = getenv('BOT_TOKEN');
    if (!$token) throw new RuntimeException('Задайте BOT_TOKEN');
    $prefix = getenv('TELEGRAM_TEST_ENV') === '1' ? '/test/' : '/';
    $curl = curl_init('https://api.telegram.org/bot'.$token.$prefix.$method);
    if ($curl === false) {
        return ['state' => 'unknown'];
    }
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($params, JSON_THROW_ON_ERROR)]);
    $raw = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    unset($curl);
    $body = is_string($raw) ? json_decode($raw, true) : null;
    if ($status >= 200 && $status < 300 && is_array($body) && ($body['ok'] ?? null) === true) {
        return ['state' => 'ok', 'result' => $body['result'] ?? null];
    }
    if ($status < 500 && is_array($body) && ($body['ok'] ?? null) === false
        && is_int($body['error_code'] ?? null)) {
        $parameters = $body['parameters'] ?? [];
        $wait = is_array($parameters) ? ($parameters['retry_after'] ?? 60) : 60;
        return ['state' => 'rejected', 'code' => $body['error_code'],
            'retry_after' => is_int($wait) ? max(1, $wait) : 60];
    }
    return ['state' => 'unknown'];
}

/** @return array<array-key, mixed> */
function incomingUpdate(): array
{
    $secret = getenv('WEBHOOK_SECRET');
    $provided = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$secret
        || !is_string($provided) || !hash_equals($secret, $provided)) {
        http_response_code(403);
        exit;
    }
    $raw = file_get_contents('php://input', false, null, 0, 1024 * 1024 + 1);
    if (!is_string($raw) || strlen($raw) > 1024 * 1024) {
        http_response_code(413);
        exit;
    }
    $update = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($update) || !is_int($update['update_id'] ?? null)) {
        throw new InvalidArgumentException('Некорректное событие');
    }
    return $update;
}
