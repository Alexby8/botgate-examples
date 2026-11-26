<?php

declare(strict_types=1);

function bookingTime(int $unix, string $format): string
{
    return (new DateTimeImmutable('@'.$unix))->setTimezone(new DateTimeZone('Europe/Moscow'))->format($format);
}

function slotName(int $unix): string
{
    return bookingTime($unix, 'd.m H:i').'–'.bookingTime($unix + 1800, 'H:i').' МСК';
}

/** @return list<list<array{text:string, callback_data:string}>> */
function bookingButtons(int $id, string $key, bool $confirm): array
{
    $cancel = ['text' => 'Отменить', 'callback_data' => "stop:$id:$key"];
    return $confirm ? [[['text' => 'Подтвердить', 'callback_data' => "yes:$id:$key"], $cancel]] : [[$cancel]];
}

/** @param array<string, mixed> $update */
function handle(PDO $db, array $update, int $now): void
{
    $a = privateAction($update);
    if ($a === null) {
        return;
    }
    $user = $a['user'];
    if (!$a['callback']) {
        if ($a['text'] === '/book' || $a['text'] === '/start') {
            showDays($db, $user, $now);
        } elseif ($a['text'] === '/mybooking') {
            $bookings = rows(sql($db, "SELECT * FROM slots WHERE user_id=? AND starts_at>?
                AND (state='booked' OR (state='held' AND expires_at>?)) ORDER BY starts_at LIMIT 10", [$user,$now,$now]));
            foreach ($bookings as $slot) {
                menu($db, $user, ($slot['state'] === 'booked' ? 'Запись: ' : 'Резерв: ').slotName((int) $slot['starts_at']),
                    bookingButtons((int) $slot['id'], (string) $slot['booking_key'], $slot['state'] === 'held'));
            }
            if ($bookings === []) {
                say($db, $user, 'Активных записей нет. /book покажет расписание.');
            }
        }
        return;
    }
    if (preg_match('/^day:(\d{4}-\d{2}-\d{2})$/D', $a['text'], $m)) {
        $times = rows(sql($db, "SELECT id, starts_at FROM slots WHERE local_day=? AND starts_at>?
            AND (state='free' OR (state='held' AND expires_at<=?)) ORDER BY starts_at LIMIT 20", [$m[1], $now, $now]));
        if ($times === []) {
            say($db, $user, 'На эту дату мест нет. Выберите другую через /book.');
            return;
        }
        $buttons = array_map(static fn (array $slot): array => [[
            'text' => bookingTime((int) $slot['starts_at'], 'H:i'), 'callback_data' => 'hold:'.$slot['id'],
        ]], $times);
        menu($db, $user, 'Выберите время по Москве', $buttons);
        return;
    }
    if (preg_match('/^hold:([0-9]+)$/D', $a['text'], $m)) {
        reserve($db, (int) $m[1], $user, $now);
        return;
    }
    if (preg_match('/^(yes|stop):([0-9]+):([a-f0-9]{24})$/D', $a['text'], $m)) {
        resolveBooking($db, (int) $m[2], $user, $m[3], $m[1], $now);
    }
}

function showDays(PDO $db, int $user, int $now): void
{
    $days = rows(sql($db, "SELECT local_day, MIN(starts_at) AS first FROM slots WHERE starts_at>?
        AND (state='free' OR (state='held' AND expires_at<=?)) GROUP BY local_day ORDER BY local_day LIMIT 7", [$now,$now]));
    if ($days === []) {
        say($db, $user, 'Свободных интервалов пока нет.');
        return;
    }
    $buttons = array_map(static fn (array $day): array => [[
        'text' => bookingTime((int) $day['first'], 'd.m'), 'callback_data' => 'day:'.$day['local_day'],
    ]], $days);
    menu($db, $user, 'Выберите дату консультации', $buttons);
}

// Called inside the transaction of a persisted update.
function reserve(PDO $db, int $id, int $user, int $now): void
{
    $slot = row(sql($db, 'SELECT * FROM slots WHERE id=? AND starts_at>?', [$id,$now]));
    if ($slot === null) {
        say($db, $user, 'Интервал устарел. Откройте /book.');
        return;
    }
    if ($slot['state'] === 'held' && (int) $slot['user_id'] === $user && (int) $slot['expires_at'] > $now) {
        $key = (string) $slot['booking_key'];
        $end = (int) $slot['expires_at'];
    } else {
        $key = bin2hex(random_bytes(12));
        $end = min($now + 300, (int) $slot['starts_at']);
        $changed = sql($db, "UPDATE slots SET state='held', user_id=?, booking_key=?, expires_at=?
            WHERE id=? AND (state='free' OR (state='held' AND expires_at<=?))",
            [$user, $key, $end, $id, $now])->rowCount();
        if ($changed !== 1) {
            say($db, $user, 'Это время уже занято. Выберите другое через /book.');
            return;
        }
    }
    menu($db, $user, 'Резерв: '.slotName((int) $slot['starts_at']).'. Подтвердите до '.bookingTime($end, 'H:i:s').' МСК.',
        bookingButtons($id, $key, true));
}

function resolveBooking(PDO $db, int $id, int $user, string $key, string $action, int $now): void
{
    $slot = row(sql($db, 'SELECT * FROM slots WHERE id=? AND user_id=? AND booking_key=? AND starts_at>?',
        [$id, $user, $key, $now]));
    if ($slot === null || ($slot['state'] === 'held' && (int) $slot['expires_at'] <= $now)) {
        say($db, $user, 'Резерв или запись недоступны. Откройте /book.');
        return;
    }
    if ($action === 'yes') {
        sql($db, "UPDATE slots SET state='booked', expires_at=NULL
            WHERE id=? AND state='held' AND booking_key=? AND user_id=? AND expires_at>?",
            [$id, $key, $user, $now]);
        menu($db, $user, 'Запись подтверждена: '.slotName((int) $slot['starts_at']), bookingButtons($id, $key, false));
        return;
    }
    sql($db, "UPDATE slots SET state='free', user_id=NULL, booking_key=NULL, expires_at=NULL WHERE id=?", [$id]);
    say($db, $user, 'Запись или резерв отменены. Время снова доступно в /book.');
}
