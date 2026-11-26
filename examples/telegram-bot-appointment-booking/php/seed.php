<?php

declare(strict_types=1);

require __DIR__.'/common.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$db = database();
$first = new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Moscow'));
atomic($db, function () use ($db, $first): void {
    foreach ([$first, $first->modify('+1 day')] as $day) {
        foreach ([[10,30],[11,0]] as [$hour,$minute]) {
            sql($db, 'INSERT INTO slots(starts_at,local_day) VALUES(?,?) ON CONFLICT(starts_at) DO NOTHING',
                [$day->setTime($hour,$minute)->getTimestamp(), $day->format('Y-m-d')]);
        }
    }
});
echo "Demo intervals ready\n";
