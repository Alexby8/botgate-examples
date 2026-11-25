<?php
declare(strict_types=1);

function database(): PDO
{
    $db = new PDO('sqlite:'.__DIR__.'/var/leads.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $db->exec('PRAGMA busy_timeout = 5000');
    return $db;
}

/** @return array{lead: array{id: int, notification: string}, created: bool} */
function saveLead(PDO $db, int $userId, string $requestId, string $name, string $question): array
{
    $insert = $db->prepare('INSERT INTO leads
        (user_id, request_id, name, question, created_at, notification)
        VALUES (?, ?, ?, ?, ?, ?)
        ON CONFLICT(user_id, request_id) DO NOTHING');
    $insert->execute([$userId, $requestId, $name, $question, time(), 'pending']);
    $created = $insert->rowCount() === 1;
    $select = $db->prepare('SELECT * FROM leads WHERE user_id = ? AND request_id = ?');
    $select->execute([$userId, $requestId]);
    $lead = $select->fetch();
    if (!is_array($lead) || !is_int($lead['id'] ?? null) || !is_string($lead['notification'] ?? null)) {
        throw new RuntimeException('Lead not found');
    }
    if ($lead['name'] !== $name || $lead['question'] !== $question) {
        throw new LogicException('Request ID already used with different fields');
    }
    return ['lead' => ['id' => $lead['id'], 'notification' => $lead['notification']], 'created' => $created];
}
