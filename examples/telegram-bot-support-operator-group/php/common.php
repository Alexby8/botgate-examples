<?php
declare(strict_types=1);

function setting(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing setting: '.$name);
    }
    return $value;
}

function database(): PDO
{
    $path = setting('BOT_DB_PATH');
    if (!is_file($path)) {
        throw new RuntimeException('Run init.php first');
    }
    $db = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('PRAGMA busy_timeout=3000');
    return $db;
}

/** @param list<int|string|null> $values */
function sql(PDO $db, string $query, array $values = []): PDOStatement
{
    $statement = $db->prepare($query);
    $statement->execute($values);
    return $statement;
}

/** @return array<string, int|string|null>|null */
function row(PDOStatement $statement): ?array
{
    $result = $statement->fetch(PDO::FETCH_ASSOC);
    if ($result === false) {
        return null;
    }
    if (!is_array($result)) {
        throw new RuntimeException('Invalid database row');
    }
    foreach ($result as $key => $value) {
        if (!is_string($key) || !(is_int($value) || is_string($value) || $value === null)) {
            throw new RuntimeException('Invalid database field');
        }
    }
    return $result;
}

/**
 * @template T
 * @param callable(): T $action
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

/** @return array<string, mixed> */
function jsonBody(): array
{
    $raw = file_get_contents('php://input', length: 65537);
    if ($raw === false || strlen($raw) > 65536) {
        throw new InvalidArgumentException('Request too large');
    }
    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data) || array_is_list($data)) {
        throw new InvalidArgumentException('Expected JSON object');
    }
    $result = [];
    foreach ($data as $key => $value) {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Invalid JSON key');
        }
        $result[$key] = $value;
    }
    return $result;
}

/** @param array<string, mixed> $data */
function reply(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}

/** @return list<array<string, int|string|null>> */
function rows(PDOStatement $statement): array
{
    $result = [];
    while (($record = row($statement)) !== null) {
        $result[] = $record;
    }
    return $result;
}

/** @return array<string, mixed> */
function objectOrEmpty(mixed $value): array
{
    $result = [];
    if (is_array($value)) {
        foreach ($value as $key => $entry) {
            if (is_string($key)) {
                $result[$key] = $entry;
            }
        }
    }
    return $result;
}
