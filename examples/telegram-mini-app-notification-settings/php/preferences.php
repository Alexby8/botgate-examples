<?php
declare(strict_types=1);

/** @return array<string,int|string|null> */
function preferences(PDO $db, int $user, int $now): array
{
    sql($db, 'INSERT INTO preferences(user_id,updated_at) VALUES(?,?) ON CONFLICT(user_id) DO NOTHING', [$user, $now]);
    return row(sql($db, 'SELECT * FROM preferences WHERE user_id=?', [$user]))
        ?? throw new RuntimeException('Preferences missing');
}

/** @param array<string,mixed> $data */
function savePreferences(PDO $db, int $user, array $data, int $now): void
{
    foreach (['orders', 'offers', 'quiet'] as $name) {
        if (!is_bool($data[$name] ?? null)) {
            throw new InvalidArgumentException('Категории должны быть true или false');
        }
    }
    $zone = $data['timezone'] ?? null;
    if (!is_string($zone) || !in_array($zone, DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC), true)) {
        throw new InvalidArgumentException('Выберите часовой пояс IANA');
    }
    $minutes = [];
    foreach (['start', 'end'] as $name) {
        $value = $data[$name] ?? null;
        if (!is_string($value) || !preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/D', $value, $match)) {
            throw new InvalidArgumentException('Время должно иметь формат ЧЧ:ММ');
        }
        $minutes[$name] = (int) $match[1] * 60 + (int) $match[2];
    }
    if ($data['quiet'] && $minutes['start'] === $minutes['end']) {
        throw new InvalidArgumentException('Начало и конец тишины должны различаться');
    }
    preferences($db, $user, $now);
    sql($db, 'UPDATE preferences SET orders=?,offers=?,timezone=?,quiet=?,start_min=?,end_min=?,updated_at=? WHERE user_id=?',
        [(int) $data['orders'], (int) $data['offers'], $zone, (int) $data['quiet'], $minutes['start'], $minutes['end'], $now, $user]);
}

/** @param array<string,int|string|null> $pref */
function isQuiet(array $pref, int $utc): bool
{
    if ((int) $pref['quiet'] !== 1) {
        return false;
    }
    $local = (new DateTimeImmutable('@'.$utc))->setTimezone(new DateTimeZone((string) $pref['timezone']));
    $minute = (int) $local->format('G') * 60 + (int) $local->format('i');
    $start = (int) $pref['start_min'];
    $end = (int) $pref['end_min'];
    return $start < $end ? $minute >= $start && $minute < $end : $minute >= $start || $minute < $end;
}

/** @param array<string,int|string|null> $pref */
function nextAllowed(array $pref, int $utc): int
{
    if (!isQuiet($pref, $utc)) {
        return $utc;
    }
    // Iterate real UTC minutes: skipped/repeated local DST times are not invented.
    $candidate = intdiv($utc, 60) * 60 + 60;
    for ($i = 0; $i < 2880; ++$i, $candidate += 60) {
        if (!isQuiet($pref, $candidate)) {
            return $candidate;
        }
    }
    throw new RuntimeException('No allowed minute in the next 48 hours');
}

function enqueue(PDO $db, string $event, int $user, string $category, string $body, int $now, int $expires): void
{
    if (!in_array($category, ['orders', 'offers'], true) || $expires <= $now || strlen($body) > 3000) {
        throw new InvalidArgumentException('Invalid notification');
    }
    sql($db, 'INSERT INTO notifications(event_key,user_id,category,body,due_at,expires_at) VALUES(?,?,?,?,?,?)
        ON CONFLICT(event_key) DO NOTHING', [$event, $user, $category, $body, $now, $expires]);
}

/** @return array<string,int|string|null>|null */
function claimNotification(PDO $db, int $now): ?array
{
    return atomic($db, function () use ($db, $now): ?array {
        $job = row(sql($db, "SELECT * FROM notifications WHERE state='pending' AND due_at<=? ORDER BY id LIMIT 1", [$now]));
        if ($job === null) {
            return null;
        }
        $pref = preferences($db, (int) $job['user_id'], $now);
        $state = null;
        if ((int) $job['expires_at'] <= $now) {
            $state = 'expired';
        } elseif ((int) $pref['blocked'] === 1) {
            $state = 'blocked';
        } elseif ((int) ($pref[(string) $job['category']] ?? 0) !== 1) {
            $state = 'skipped';
        }
        if ($state !== null) {
            sql($db, 'UPDATE notifications SET state=? WHERE id=?', [$state, (int) $job['id']]);
            return null;
        }
        $due = nextAllowed($pref, $now);
        if ($due > $now) {
            sql($db, 'UPDATE notifications SET due_at=? WHERE id=?', [$due, (int) $job['id']]);
            return null;
        }
        sql($db, "UPDATE notifications SET state='sending',claimed_at=?,attempts=attempts+1 WHERE id=?", [$now, (int) $job['id']]);
        return $job;
    });
}
