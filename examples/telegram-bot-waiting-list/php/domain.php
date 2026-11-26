<?php

declare(strict_types=1);
function slotTime(int $value): string
{
    return (new DateTimeImmutable('@'.$value))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m.Y H:i').' МСК';
}
function promote(PDO $db, int $slotId, int $now): void
{
    $slot = row($db, 'SELECT * FROM slots WHERE id=?', [$slotId]);
    if (!$slot) {
        throw new DomainException('Такого времени нет');
    }
    $active = row($db, "SELECT * FROM offers WHERE slot_id=? AND state='offered'", [$slotId]);
    if ($active && (intValue($active['expires_at']) <= $now || intValue($slot['starts_at']) <= $now)) {
        run($db, "UPDATE offers SET state='expired' WHERE id=?", [intValue($active['id'])]);
        run($db, "UPDATE waiting SET state='expired' WHERE id=?", [intValue($active['entry_id'])]);
        $active = null;
    }
    if ($slot['booking_user'] !== null || $active !== null || intValue($slot['starts_at']) <= $now) {
        return;
    }
    $next = row($db, "SELECT * FROM waiting WHERE slot_id=? AND state='waiting' ORDER BY id LIMIT 1", [$slotId]);
    if (!$next) {
        return;
    }
    $token = bin2hex(random_bytes(16));
    $end = min($now + 600, intValue($slot['starts_at']));
    run($db, "INSERT INTO offers(token,entry_id,slot_id,user_id,expires_at,state) VALUES(?,?,?,?,?,'offered')", [$token,intValue($next['id']),$slotId,intValue($next['user_id']),$end]);
    $id = (int)$db->lastInsertId();
    run($db, "UPDATE waiting SET state='offered' WHERE id=?", [intValue($next['id'])]);
    enqueue(
        $db,
        intValue($next['user_id']),
        'offer:'.$id,
        'Освободилось время: '.slotTime(intValue($slot['starts_at']))."\nПодтвердите до ".slotTime($end).'. Предложение только для вас.',
        [[['text' => 'Подтвердить','callback_data' => 'offer:'.$token.':yes'],['text' => 'Отказаться','callback_data' => 'offer:'.$token.':no']]],
        'offer',
        $id
    );
}
function cancelBooking(PDO $db, int $slotId, string $key, int $now): void
{
    $old = row($db, 'SELECT slot_id FROM cancellations WHERE booking_key=?', [$key]);
    if ($old) {
        if (intValue($old['slot_id']) !== $slotId) {
            throw new DomainException('Конфликт отмены');
        }
        return;
    }
    $slot = row($db, 'SELECT * FROM slots WHERE id=? AND booking_key=?', [$slotId,$key]);
    if (!$slot) {
        throw new DomainException('Отмена относится к другой записи');
    }
    run($db, 'INSERT INTO cancellations(booking_key,slot_id,created_at) VALUES(?,?,?)', [$key,$slotId,$now]);
    run($db, 'UPDATE slots SET booking_user=NULL,booking_key=NULL WHERE id=?', [$slotId]);
    promote($db, $slotId, $now);
}
function handle(PDO $db, int $user, string $action, bool $callback, int $update, int $now): void
{
    $text = 'Команды: /join НОМЕР — ждать конкретное время; /status НОМЕР — проверить очередь; /leave НОМЕР — выйти.';
    if (preg_match('~^/(join|status|leave) ([1-9][0-9]*)$~D', $action, $m)) {
        $slotId = positive($m[2]);
        $slot = row($db, 'SELECT * FROM slots WHERE id=?', [$slotId]);
        if (!$slot) {
            throw new DomainException('Слот не найден');
        }
        if (intValue($slot['starts_at']) <= $now) {
            throw new DomainException('Это время уже прошло');
        }
        promote($db, $slotId, $now);
        $entry = row($db, "SELECT * FROM waiting WHERE slot_id=? AND user_id=? AND state IN ('waiting','offered')", [$slotId,$user]);
        if ($m[1] === 'join') {
            if ($slot['booking_user'] !== null && intValue($slot['booking_user']) === $user) {
                throw new DomainException('Вы уже записаны на это время');
            }
            if (!$entry) {
                run($db, "INSERT INTO waiting(slot_id,user_id,state) VALUES(?,?,'waiting')", [$slotId,$user]);
            }
            promote($db, $slotId, $now);
            $text = 'Заявка в листе ожидания сохранена. /status '.$slotId.' покажет состояние. Сообщение о свободном времени ещё не означает запись.';
        } elseif ($m[1] === 'leave') {
            if ($entry) {
                run($db, "UPDATE waiting SET state='left' WHERE id=?", [intValue($entry['id'])]);
                run($db, "UPDATE offers SET state='declined' WHERE entry_id=? AND state='offered'", [intValue($entry['id'])]);
            }
            promote($db, $slotId, $now);
            $text = 'Ожидание отменено. Подтверждённую запись эта команда не отменяет.';
        } else {
            $offer = row($db, "SELECT * FROM offers WHERE slot_id=? AND user_id=? AND state='offered'", [$slotId,$user]);
            $current = row($db, 'SELECT * FROM slots WHERE id=?', [$slotId]) ?? throw new RuntimeException('Slot missing');
            if ($current['booking_user'] !== null && intValue($current['booking_user']) === $user) {
                $text = 'Вы записаны: '.slotTime(intValue($current['starts_at']));
            } elseif ($offer) {
                $t = stringValue($offer['token']);
                enqueue($db, $user, 'status:'.$update, 'Ваше предложение действует до '.slotTime(intValue($offer['expires_at'])), [[['text' => 'Подтвердить','callback_data' => 'offer:'.$t.':yes'],['text' => 'Отказаться','callback_data' => 'offer:'.$t.':no']]], 'offer', intValue($offer['id']));
                return;
            } elseif ($entry && $entry['state'] === 'waiting') {
                $ahead = intValue(run($db, "SELECT COUNT(*) FROM waiting WHERE slot_id=? AND state IN ('waiting','offered') AND id<?", [$slotId,intValue($entry['id'])])->fetchColumn());
                $text = 'Перед вами: '.$ahead.'. Время: '.slotTime(intValue($slot['starts_at']));
            } else {
                $text = 'Активного ожидания нет. Просроченное предложение не возвращает вас в начало очереди; можно заново отправить /join '.$slotId;
            }
        }
    } elseif ($callback && preg_match('/^offer:([a-f0-9]{32}):(yes|no)$/D', $action, $m)) {
        $offer = row($db, 'SELECT * FROM offers WHERE token=? AND user_id=?', [$m[1],$user]);
        if (!$offer) {
            throw new DomainException('Предложение не найдено');
        }
        $slotId = intValue($offer['slot_id']);
        promote($db, $slotId, $now);
        $offer = row($db, 'SELECT * FROM offers WHERE id=?', [intValue($offer['id'])]) ?? throw new RuntimeException('Offer missing');
        if ($offer['state'] === 'accepted') {
            $text = 'Вы уже подтвердили это предложение. Проверьте /status '.$slotId;
        } elseif ($offer['state'] !== 'offered' || intValue($offer['expires_at']) <= $now) {
            $text = 'Предложение уже закрыто. Проверьте /status '.$slotId;
        } else {
            $accepted = $m[2] === 'yes';
            $state = $accepted ? 'accepted' : 'declined';
            if ($accepted) {
                $changed = run($db, 'UPDATE slots SET booking_user=?,booking_key=? WHERE id=? AND booking_user IS NULL', [$user,stringValue($offer['token']),$slotId])->rowCount();
                if ($changed !== 1) {
                    throw new DomainException('Время уже занято');
                }
            }
            run($db, 'UPDATE offers SET state=? WHERE id=?', [$state,intValue($offer['id'])]);
            run($db, 'UPDATE waiting SET state=? WHERE id=?', [$state,intValue($offer['entry_id'])]);
            if (!$accepted) {
                promote($db, $slotId, $now);
            }
            $text = $accepted ? 'Запись подтверждена. /status '.$slotId.' покажет время.' : 'Вы отказались. Предложение передано следующему участнику.';
        }
    }
    enqueue($db,$user,'reply:'.$update,$text);
}
/**
 * @param array<string,mixed> $r
 */
function canDeliver(PDO $db,array $r,int $now): bool
{
    return $r['guard_kind'] !== 'offer' || row($db,"SELECT id FROM offers WHERE id=? AND state='offered' AND expires_at>?",[intValue($r['guard_id']),$now]) !== null;
}
