<?php
declare(strict_types=1);
require_once __DIR__ . '/form.php';

/** @param array<array-key, mixed> $update */
function applyFormUpdate(PDO $db, string $botId, array $update, int $now): bool
{
    $m = $update['message'] ?? null;
    if (!is_array($m) || !is_array($m['chat'] ?? null) || !is_array($m['from'] ?? null)
        || ($m['chat']['type'] ?? null) !== 'private'
        || !is_int($update['update_id'] ?? null)
        || !is_int($m['chat']['id'] ?? null)
        || !is_int($m['from']['id'] ?? null)
        || $m['chat']['id'] !== $m['from']['id']
        || ($m['from']['is_bot'] ?? false)) {
        return false;
    }
    $chatId = $m['chat']['id'];
    $userId = $m['from']['id'];
    $updateId = $update['update_id'];
    $json = static fn(array $value): string => json_encode(
        $value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
    );
    // SQLite: блокировка записи получается до чтения состояния.
    $db->exec('BEGIN IMMEDIATE');
    try {
        $seen = $db->prepare('INSERT INTO form_events VALUES (?, ?)
            ON CONFLICT (bot_id, update_id) DO NOTHING');
        $seen->execute([$botId, $updateId]);
        if ($seen->rowCount() === 0) {
            $db->exec('COMMIT');
            return false;
        }
        $read = $db->prepare('SELECT state_json FROM form_states
            WHERE bot_id = ? AND chat_id = ? AND user_id = ?');
        $read->execute([$botId, $chatId, $userId]);
        $stored = $read->fetchColumn();
        $state = decodeFormState($stored);
        $result = formStep($state, is_string($m['text'] ?? null) ? $m['text'] : null, $now);
        $db->prepare('INSERT INTO form_states VALUES (?, ?, ?, ?)
            ON CONFLICT (bot_id, chat_id, user_id)
            DO UPDATE SET state_json = excluded.state_json')
            ->execute([$botId, $chatId, $userId, $json($result['state'])]);

        if ($result['application'] !== null) {
            $db->prepare('INSERT INTO applications VALUES (?, ?, ?, ?, ?)')
                ->execute([$botId, $updateId, $chatId, $userId, $json($result['application'])]);
        }
        $db->prepare('INSERT INTO form_outbox (bot_id, update_id, payload_json)
            VALUES (?, ?, ?)')->execute([$botId, $updateId, $json([
                'chat_id' => $chatId, 'text' => $result['text'],
            ])]);
        $db->exec('COMMIT');
        return true;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}

/** @return array<string, int|string> */
function decodeFormState(mixed $stored): array
{
    if ($stored === false) {
        return [];
    }
    $state = is_string($stored) ? json_decode($stored, true, 32, JSON_THROW_ON_ERROR) : null;
    if (!is_array($state)) {
        throw new UnexpectedValueException('Invalid stored state');
    }
    foreach ($state as $key => $value) {
        if (!is_string($key) || (!is_int($value) && !is_string($value))) {
            throw new UnexpectedValueException('Invalid stored field');
        }
    }
    return $state;
}
