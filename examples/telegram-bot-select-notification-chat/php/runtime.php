<?php
declare(strict_types=1);

/**
 * @param array<string,mixed> $params
 * @return array<string,mixed>
 */
function apiObject(string $method, array $params): array
{
    $answer = telegram($method, $params);
    $object = $answer['result'] ?? null;
    if ($answer['state'] !== 'ok' || !is_array($object)) {
        throw new RuntimeException('Telegram check failed: '.$method);
    }
    $result = [];
    foreach ($object as $key => $value) {
        if (!is_string($key)) {
            throw new RuntimeException('Invalid API object');
        }
        $result[$key] = $value;
    }
    return $result;
}

/**
 * @param array<string,mixed> $params
 * @return array{state:string,result?:mixed}
 */
function telegramOnce(PDO $db, string $key, string $method, array $params): array
{
    $inserted = sql($db, "INSERT INTO actions(action_key,method,state,created_at) VALUES(?,?,'sending',?)
        ON CONFLICT(action_key) DO NOTHING", [$key, $method, time()])->rowCount();
    if ($inserted === 0) {
        $old = row(sql($db, 'SELECT state,result FROM actions WHERE action_key=?', [$key]));
        return ['state' => (string) ($old['state'] ?? 'unknown'),
            'result' => json_decode((string) ($old['result'] ?? 'null'), true)];
    }
    $answer = telegram($method, $params);
    sql($db, 'UPDATE actions SET state=?,result=? WHERE action_key=?',
        [$answer['state'], json_encode($answer['result'] ?? null, JSON_THROW_ON_ERROR), $key]);
    return $answer;
}

/** @param array<string,mixed> $member */
function isAdmin(array $member): bool
{
    return in_array($member['status'] ?? '', ['creator', 'administrator'], true);
}
