<?php
declare(strict_types=1);

function joinProgram(PDO $db, int $user, ?string $inviteCode, int $now): string
{
    return atomic($db, function () use ($db, $user, $inviteCode, $now): string {
        $member = row(sql($db, 'SELECT code FROM members WHERE user_id=?', [$user]));
        if ($member !== null) {
            return (string) $member['code'];
        }
        // Resolve before insertion: a new member cannot invite themselves.
        $inviter = $inviteCode === null ? null : row(sql($db,
            'SELECT user_id FROM members WHERE code=?', [$inviteCode]));
        $code = bin2hex(random_bytes(12));
        sql($db, 'INSERT INTO members(user_id,code,joined_at) VALUES(?,?,?)', [$user, $code, $now]);
        if ($inviter !== null && (int) $inviter['user_id'] !== $user) {
            sql($db, 'INSERT INTO referrals(invitee,inviter,bound_at) VALUES(?,?,?)',
                [$user, (int) $inviter['user_id'], $now]);
        }
        return $code;
    });
}

function awardOrder(PDO $db, string $orderId, int $now): bool
{
    return atomic($db, function () use ($db, $orderId, $now): bool {
        $order = row(sql($db, 'SELECT o.*,r.inviter,r.bound_at FROM orders o
            JOIN referrals r ON r.invitee=o.user_id WHERE o.id=?', [$orderId]));
        if ($order === null || $order['status'] !== 'paid' || (int) $order['amount'] < 100000
            || (int) $order['paid_at'] < (int) $order['bound_at']) {
            return false;
        }
        $inserted = sql($db, 'INSERT INTO rewards(program,invitee,inviter,order_id,points,created_at)
            VALUES(?,?,?,?,100,?) ON CONFLICT(program,invitee) DO NOTHING',
            ['first-purchase-v1', (int) $order['user_id'], (int) $order['inviter'], $orderId, $now])->rowCount();
        if ($inserted === 0) {
            return false;
        }
        $reward = (int) $db->lastInsertId();
        sql($db, 'INSERT INTO reward_log(reward_id,user_id,delta,action,created_at) VALUES(?,?,100,?,?)',
            [$reward, (int) $order['inviter'], 'award', $now]);
        return true;
    });
}

function refundOrder(PDO $db, string $orderId, int $now): void
{
    atomic($db, function () use ($db, $orderId, $now): void {
        sql($db, "UPDATE orders SET status='refunded' WHERE id=?", [$orderId]);
        $reward = row(sql($db, 'SELECT * FROM rewards WHERE order_id=? AND reversed_at IS NULL', [$orderId]));
        if ($reward === null) {
            return;
        }
        sql($db, 'INSERT INTO reward_log(reward_id,user_id,delta,action,created_at) VALUES(?,?,?,?,?)',
            [(int) $reward['id'], (int) $reward['inviter'], -(int) $reward['points'], 'reverse', $now]);
        sql($db, 'UPDATE rewards SET reversed_at=? WHERE id=?', [$now, (int) $reward['id']]);
    });
}

function rewardHistory(PDO $db, int $user): string
{
    $sum = row(sql($db, 'SELECT COALESCE(SUM(delta),0) AS total FROM reward_log WHERE user_id=?', [$user]));
    $text = 'Баланс: '.(int) ($sum['total'] ?? 0)." баллов\nПоследние операции:\n";
    $statement = sql($db, 'SELECT delta,action,created_at FROM reward_log WHERE user_id=? ORDER BY id DESC LIMIT 10', [$user]);
    while (($entry = row($statement)) !== null) {
        $text .= gmdate('Y-m-d H:i', (int) $entry['created_at']).' UTC: '
            .($entry['action'] === 'award' ? 'начисление' : 'отмена').' '.(int) $entry['delta']."\n";
    }
    return $text;
}
