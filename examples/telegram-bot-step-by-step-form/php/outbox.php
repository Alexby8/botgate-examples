<?php
declare(strict_types=1);
require_once __DIR__.'/telegram.php';

/** Deliver the oldest pending reply for this bot. Run a single worker per database. */
function deliverReply(PDO $db, string $botId, callable $send, int $now): bool
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $next = $db->prepare("SELECT * FROM form_outbox
            WHERE bot_id = ? AND status = 'pending' ORDER BY rowid LIMIT 1");
        $next->execute([$botId]);
        $row = $next->fetch(PDO::FETCH_ASSOC);
        $next->closeCursor();
        if ($row === false) {
            $db->exec('COMMIT');
            return false;
        }
        if (!is_array($row) || !is_int($row['available_at'] ?? null)
            || !is_int($row['attempts'] ?? null) || !is_int($row['update_id'] ?? null)
            || !is_string($row['payload_json'] ?? null)) {
            throw new UnexpectedValueException('Invalid outbox row');
        }
        if ($row['available_at'] > $now) {
            $db->exec('COMMIT');
            return false;
        }
        $payloadJson = $row['payload_json'];
        $db->prepare("UPDATE form_outbox SET status = 'sending', attempts = attempts + 1
            WHERE bot_id = ? AND update_id = ?")->execute([$botId, $row['update_id']]);
        $db->exec('COMMIT');
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }

    $messageId = null;
    $availableAt = 0;
    try {
        $payload = json_decode($payloadJson, true, 32, JSON_THROW_ON_ERROR);
        $message = $send('sendMessage', $payload);
        $messageId = $message['message_id'] ?? null;
        $status = is_int($messageId) ? 'sent' : 'unknown';
    } catch (TelegramRejection $error) {
        $status = 'rejected';
        if ($error->getCode() === 429 && $error->retryAfter > 0 && $row['attempts'] < 2) {
            $status = 'pending';
            $availableAt = max(time(), $now) + $error->retryAfter;
        }
    } catch (Throwable) {
        $status = 'unknown';
    }

    $db->prepare('UPDATE form_outbox SET status = ?, message_id = ?, available_at = ?
        WHERE bot_id = ? AND update_id = ?')
        ->execute([$status, $messageId, $availableAt, $botId, $row['update_id']]);
    return true;
}
