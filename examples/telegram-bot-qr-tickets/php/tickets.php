<?php
declare(strict_types=1);

function ticketToken(string $id, string $key): string
{
    if (strlen($key) < 32) {
        throw new RuntimeException('TICKET_KEY must contain at least 32 characters');
    }
    return $id.'.'.hash_hmac('sha256', $id, $key);
}

function registerTicket(PDO $db, int $event, int $user, string $key, int $now): string
{
    return atomic($db, function () use ($db, $event, $user, $key, $now): string {
        $exists = row(sql($db, 'SELECT active FROM events WHERE id=?', [$event]));
        if ($exists === null || (int) $exists['active'] !== 1) {
            throw new DomainException('Регистрация закрыта');
        }
        $ticket = row(sql($db, 'SELECT id,token_hash,cancelled FROM tickets WHERE event_id=? AND user_id=?', [$event, $user]));
        if ($ticket !== null) {
            if ((int) $ticket['cancelled'] === 1) {
                throw new DomainException('Билет аннулирован');
            }
            $token = ticketToken((string) $ticket['id'], $key);
            if (!hash_equals((string) $ticket['token_hash'], hash('sha256', $token))) {
                throw new RuntimeException('Сохранённый билет не совпадает с TICKET_KEY');
            }
            return $token;
        }
        $id = bin2hex(random_bytes(16));
        $token = ticketToken($id, $key);
        sql($db, 'INSERT INTO tickets(id,event_id,user_id,token_hash,created_at) VALUES(?,?,?,?,?)',
            [$id, $event, $user, hash('sha256', $token), $now]);
        return $token;
    });
}

/** @return array{status: string, ticket?: string, used_at?: int} */
function checkTicket(PDO $db, int $event, int $checker, string $token, bool $consume, int $now): array
{
    return atomic($db, function () use ($db, $event, $checker, $token, $consume, $now): array {
        $role = row(sql($db, 'SELECT 1 AS allowed FROM checkers c JOIN events e ON e.id=c.event_id
            WHERE c.event_id=? AND c.user_id=? AND c.active=1 AND e.active=1', [$event, $checker]));
        if ($role === null) {
            throw new DomainException('Нет доступа к проверке этого мероприятия');
        }
        if (!preg_match('/^[a-f0-9]{32}\.[a-f0-9]{64}$/D', $token)) {
            return ['status' => 'invalid'];
        }
        $ticket = row(sql($db, 'SELECT * FROM tickets WHERE token_hash=? AND event_id=?',
            [hash('sha256', $token), $event]));
        if ($ticket === null || (int) $ticket['cancelled'] === 1) {
            return ['status' => 'invalid'];
        }
        if ($ticket['used_at'] !== null) {
            return ['status' => 'used', 'ticket' => (string) $ticket['id'], 'used_at' => (int) $ticket['used_at']];
        }
        if (!$consume) {
            return ['status' => 'ready', 'ticket' => (string) $ticket['id']];
        }
        $changed = sql($db, 'UPDATE tickets SET used_at=?,used_by=? WHERE id=? AND used_at IS NULL AND cancelled=0',
            [$now, $checker, (string) $ticket['id']])->rowCount();
        if ($changed !== 1) {
            throw new RuntimeException('Не удалось записать проход');
        }
        return ['status' => 'accepted', 'ticket' => (string) $ticket['id'], 'used_at' => $now];
    });
}
