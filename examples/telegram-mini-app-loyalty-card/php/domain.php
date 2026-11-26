<?php

declare(strict_types=1);
function ledger(PDO $db, int $user, string $key, string $kind, int $delta, int $now): void
{
    run($db, 'INSERT INTO ledger(user_id,event_key,kind,delta,created_at) VALUES(?,?,?,?,?)', [$user,$key,$kind,$delta,$now]);
}
function expirePoints(PDO $db, int $user, int $now): void
{
    foreach (run($db, 'SELECT * FROM lots WHERE user_id=? AND remaining>0 AND expires_at<=?', [$user,$now])->fetchAll() as $value) {
        $l = objectValue($value);
        $id = intValue($l['id']);
        $n = intValue($l['remaining']);
        run($db, 'UPDATE lots SET remaining=0,expired=expired+? WHERE id=?', [$n,$id]);
        ledger($db, $user, 'expire:'.$id, 'Истёк срок баллов', -$n, $now);
    }
}
function balance(PDO $db, int $user, int $now): int
{
    return intValue(run($db, 'SELECT COALESCE(SUM(remaining),0) FROM lots WHERE user_id=? AND expires_at>? AND revoked=0', [$user,$now])->fetchColumn());
}
function requestPoints(PDO $db, int $user, string $order, int $points, int $now): void
{
    $o = row($db, "SELECT * FROM orders WHERE id=? AND user_id=? AND state='open'", [$order,$user]);
    if (!$o || $points < 0) {
        throw new DomainException('Заказ или количество баллов некорректны');
    }
    expirePoints($db, $user, $now);
    $cap = intdiv(intValue($o['total_minor']) * 30, 10000);
    if ($points > min($cap, balance($db, $user, $now))) {
        throw new DomainException('Недостаточно баллов или превышен лимит 30%');
    }
    run($db, 'UPDATE orders SET requested=? WHERE id=?', [$points,$order]);
}
// Trusted checkout only: cash is the verified amount paid in minor units.
function pay(PDO $db, string $id, int $cash, int $now): void
{
    $o = row($db, 'SELECT * FROM orders WHERE id=?', [$id]);
    if (!$o) {
        throw new DomainException('Заказ не найден');
    }
    if ($o['state'] === 'paid') {
        if (intValue($o['cash_minor']) !== $cash) {
            throw new DomainException('Конфликт суммы повторного события');
        }
        return;
    }
    if ($o['state'] !== 'open') {
        throw new DomainException('Заказ закрыт');
    }
    $user = intValue($o['user_id']);
    expirePoints($db, $user, $now);
    $points = intValue($o['requested']);
    if (row($db, "SELECT id FROM orders WHERE user_id=? AND return_state='review'", [$user])) {
        throw new DomainException('Нужна сверка бонусов по возврату');
    }
    if ($cash !== intValue($o['total_minor']) - $points * 100 || $points > balance($db, $user, $now)) {
        throw new DomainException('Сумма или доступные баллы изменились: нужна сверка оплаты');
    }
    $left = $points;
    foreach (run($db, 'SELECT * FROM lots WHERE user_id=? AND remaining>0 AND revoked=0 AND expires_at>? ORDER BY expires_at,id', [$user,$now])->fetchAll() as $value) {
        if ($left === 0) {
            break;
        }
        $l = objectValue($value);
        $n = min($left, intValue($l['remaining']));
        $lot = intValue($l['id']);
        run($db, 'UPDATE lots SET remaining=remaining-? WHERE id=?', [$n,$lot]);
        run($db, 'INSERT INTO allocations(order_id,lot_id,points) VALUES(?,?,?)', [$id,$lot,$n]);
        $left -= $n;
    }
    if ($points > 0) {
        ledger($db, $user, 'spend:'.$id, 'Списание по заказу '.$id, -$points, $now);
    }
    $earned = intdiv($cash, 2000); // 5% of cash; one point = one rouble, round down.
    run($db, "UPDATE orders SET state='paid',cash_minor=? WHERE id=?", [$cash,$id]);
    run($db, 'INSERT INTO lots(order_id,user_id,points,remaining,expires_at) VALUES(?,?,?,?,?)', [$id,$user,$earned,$earned,$now + 90 * 86400]);
    ledger($db, $user, 'earn:'.$id, 'Начисление по заказу '.$id, $earned, $now);
}
function refund(PDO $db, string $id, int $now): string
{
    $o = row($db, 'SELECT * FROM orders WHERE id=?', [$id]);
    if (!$o || $o['state'] !== 'paid') {
        throw new DomainException('Оплаченный заказ не найден');
    }
    if ($o['return_state'] === 'done') {
        return 'done';
    }
    $user = intValue($o['user_id']);
    expirePoints($db, $user, $now);
    $earned = row($db, 'SELECT * FROM lots WHERE order_id=?', [$id]) ?? throw new RuntimeException('Lot missing');
    // Never quietly take back points already used in another paid order.
    if (intValue($earned['remaining']) + intValue($earned['expired']) < intValue($earned['points'])) {
        if ($o['return_state'] !== 'review') {
            run($db, "UPDATE orders SET return_state='review' WHERE id=?", [$id]);
            ledger($db, $user, 'review:'.$id, 'Возврат '.$id.': требуется сверка использованных начислений', 0, $now);
        }
        return 'review';
    }
    $remove = intValue($earned['remaining']);
    run($db, 'UPDATE lots SET remaining=0,revoked=1 WHERE id=?', [intValue($earned['id'])]);
    ledger($db, $user, 'revoke:'.$id, 'Отмена начисления по возврату '.$id, -$remove, $now);
    foreach (run($db, 'SELECT a.points AS used,l.id,l.expires_at,l.revoked FROM allocations a JOIN lots l ON l.id=a.lot_id WHERE a.order_id=?', [$id])->fetchAll() as $value) {
        $l = objectValue($value);
        $n = intValue($l['used']);
        $lot = intValue($l['id']);
        $restore = intValue($l['expires_at']) > $now && intValue($l['revoked']) === 0 ? $n : 0;
        if ($restore > 0) {
            run($db, 'UPDATE lots SET remaining=remaining+? WHERE id=?', [$restore,$lot]);
        }
        ledger($db, $user, 'restore:'.$id.':'.$lot, $restore > 0 ? 'Возврат списания '.$id : 'Списание не восстановлено: срок исходных баллов истёк', $restore, $now);
    }
    run($db, "UPDATE orders SET return_state='done' WHERE id=?", [$id]);
    return 'done';
}
/**
 * @return array<string,mixed>
 */
function card(PDO $db, int $user, int $now): array
{
    expirePoints($db,$user,$now);
    return ['balance' => balance($db,$user,$now),
        'lots' => run($db,'SELECT remaining,expires_at FROM lots WHERE user_id=? AND remaining>0 AND revoked=0 ORDER BY expires_at,id',[$user])->fetchAll(),
        'history' => run($db,'SELECT kind,delta,created_at FROM ledger WHERE user_id=? ORDER BY id DESC LIMIT 30',[$user])->fetchAll(),
        'orders' => run($db,"SELECT id,total_minor,requested FROM orders WHERE user_id=? AND state='open' ORDER BY id LIMIT 20",[$user])->fetchAll(),
        'review' => (bool)row($db,"SELECT id FROM orders WHERE user_id=? AND return_state='review'",[$user])];
}
