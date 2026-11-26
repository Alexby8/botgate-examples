<?php

declare(strict_types=1);
require_once __DIR__.'/values.php';

function envRequired(string $key): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing '.$key);
    }
    return $value;
}
function database(): PDO
{
    $db = new PDO('sqlite:'.envRequired('APP_DB'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('PRAGMA busy_timeout=5000');
    return $db;
}
/**
 * @param list<int|string|null> $params
 */
function run(PDO $db, string $sql, array $params = []): PDOStatement
{
    $query = $db->prepare($sql);
    $query->execute($params);
    return $query;
}
/**
 * @param list<int|string|null> $params
 * @return array<string,mixed>|null
 */
function row(PDO $db, string $sql, array $params = []): ?array
{
    $value = run($db, $sql, $params)->fetch();
    return $value === false ? null : objectValue($value);
}
/**
 * @template T
 * @param callable():T $action
 * @return T
 */
function atomic(PDO $db, callable $action): mixed
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $result = $action();
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}
function positive(mixed $value, int $max = 100000000): int
{
    $n = intValue($value);
    if ($n < 1 || $n > $max) {
        throw new DomainException('Число вне допустимого диапазона');
    } return $n;
}
function token(mixed $value): string
{
    $v = stringValue($value);
    if (!preg_match('/^[A-Za-z0-9_-]{8,80}$/D', $v)) {
        throw new DomainException('Неверный ключ операции');
    } return $v;
}
