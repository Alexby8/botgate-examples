<?php
declare(strict_types=1);

/** @param list<array{text:string,parse_mode:string}> $parts */
function storeParts(PDO $db, string $batch, string $chatId, array $parts): void
{
    $db->exec('BEGIN IMMEDIATE');
    try {
        $check = $db->prepare('SELECT 1 FROM message_parts WHERE batch_id=? LIMIT 1');
        $check->execute([$batch]);
        $exists = $check->fetchColumn() !== false;
        $check->closeCursor();
        if (!$exists) {
            $insert = $db->prepare('INSERT INTO message_parts(batch_id,part_no,payload_json) VALUES(?,?,?)');
            foreach ($parts as $i => $part) {
                $payload = ['chat_id' => $chatId] + $part;
                $insert->execute([$batch,$i + 1,json_encode($payload, JSON_THROW_ON_ERROR)]);
            }
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}
