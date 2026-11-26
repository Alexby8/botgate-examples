<?php
declare(strict_types=1);

/**
 * @param callable(string, array<array-key,mixed>): array{state:string,retry_after:int,message_id:?int} $send
 * @return 'sent'|'retry'|'failed'|'unknown'|'waiting'|'done'
 */
function sendNextPart(PDO $db, string $batch, callable $send, int $now): string
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $next = $db->prepare("SELECT * FROM message_parts
            WHERE batch_id=? AND state<>'sent' ORDER BY part_no LIMIT 1");
        $next->execute([$batch]);
        $part = $next->fetch(PDO::FETCH_ASSOC);
        $next->closeCursor();
        if (!is_array($part)) {
            $db->exec('COMMIT');
            return 'done';
        }

        $number = $part['part_no'];
        $attempts = $part['attempts'];
        $payloadJson = $part['payload_json'];
        if (!is_int($number) || !is_int($attempts) || !is_string($payloadJson)) {
            throw new RuntimeException('Повреждённая запись части');
        }
        if (!is_int($part['next_at']) || !in_array($part['state'], ['pending','retry'], true) || $part['next_at'] > $now) {
            $db->exec('COMMIT');
            return 'waiting';
        }

        $db->prepare("UPDATE message_parts SET state='sending',attempts=attempts+1
            WHERE batch_id=? AND part_no=?")->execute([$batch,$number]);
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }

    $state = 'unknown';
    $nextAt = 0;
    $messageId = null;
    try {
        $payload = json_decode($payloadJson, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($payload)) {
            throw new RuntimeException('Повреждённый payload');
        }
        $response = $send('sendMessage', $payload);
        if ($response['state'] === 'sent' && $response['message_id'] !== null) {
            $state = 'sent';
            $messageId = $response['message_id'];
            $nextAt = max($now, time()) + 2;
        } elseif ($response['state'] === 'retry' && $response['retry_after'] > 0) {
            $state = $attempts < 4 ? 'retry' : 'failed';
            $nextAt = max($now, time()) + $response['retry_after'];
        } elseif ($response['state'] === 'failed') {
            $state = 'failed';
        }
    } catch (Throwable) {
        // После сетевого сбоя Telegram мог уже принять сообщение.
    }

    $db->exec('BEGIN IMMEDIATE');
    try {
        $db->prepare('UPDATE message_parts SET state=?,next_at=?,message_id=?
            WHERE batch_id=? AND part_no=?')
            ->execute([$state,$nextAt,$messageId,$batch,$number]);
        if ($state === 'sent') {
            $db->prepare('UPDATE message_parts SET next_at=? WHERE batch_id=? AND part_no=?')
                ->execute([$nextAt,$batch,$number + 1]);
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    return $state;
}
