<?php

declare(strict_types=1);

require_once __DIR__.'/pricing.php';

/**
 * @param  array<string,mixed>  $input
 * @return array<string,mixed>
 */
function saveQuote(PDO $db, int $user, array $input, int $now): array
{
    $p = options($input);
    $key = token($input['operation'] ?? '');
    $json = json_encode($p, JSON_THROW_ON_ERROR);
    $hash = hash('sha256', $json);
    $old = row($db, 'SELECT * FROM quotes WHERE user_id=? AND operation=?', [$user, $key]);
    if ($old) {
        if ($old['input_hash'] !== $hash) {
            throw new DomainException('Ключ уже относится к другому расчёту');
        }

return $old;
    }
    $id = bin2hex(random_bytes(16));
    run($db, 'INSERT INTO quotes(id,user_id,operation,input_hash,params,tariff,total_minor,created_at,expires_at) VALUES(?,?,?,?,?,?,?,?,?)', [$id, $user, $key, $hash, $json, '2026-11-v1', price($p), $now, $now + 86400]);

    return row($db, 'SELECT * FROM quotes WHERE id=?', [$id]) ?? throw new RuntimeException('Quote missing');
}
function submitQuote(PDO $db, int $user, string $id, int $now): void
{
    $q = row($db, 'SELECT * FROM quotes WHERE id=? AND user_id=?', [$id, $user]);
    if (! $q) {
        throw new DomainException('Расчёт не найден');
    }
    if ($q['submitted_at'] !== null) {
        return;
    }
    if (intValue($q['expires_at']) <= $now) {
        throw new DomainException('Расчёт устарел. Сохраните новый');
    }
    run($db, 'UPDATE quotes SET submitted_at=? WHERE id=?', [$now, $id]);
    enqueue(
        $db,
        positive(envRequired('MANAGER_CHAT_ID'), PHP_INT_MAX),
        'quote:'.$id,
        'Заявка '.$id."\nКлиент: ".$user."\nПараметры: ".stringValue($q['params'])."\nПредварительно: ".number_format(intValue($q['total_minor']) / 100, 2, ',', ' ').' ₽'
    );
}
/**
 * @param  array<string,mixed>  $r
 */
function canDeliver(PDO $db, array $r, int $now): bool
{
    return true;
}
