<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/outbox.php';
require __DIR__.'/domain.php';
$argv = $argv ?? [];
$db = database();
atomic($db, function () use ($db, $argv): void {
    if (($argv[1] ?? '') === 'slot') {
        $id = positive($argv[2] ?? 0);
        $start = positive($argv[3] ?? 0, PHP_INT_MAX);
        $user = positive($argv[4] ?? 0, PHP_INT_MAX);
        $key = token($argv[5] ?? '');
        run($db, 'INSERT INTO slots(id,starts_at,booking_user,booking_key) VALUES(?,?,?,?)', [$id,$start,$user,$key]);
    } elseif (($argv[1] ?? '') === 'cancel') {
        cancelBooking($db, positive($argv[2] ?? 0), token($argv[3] ?? ''), time());
    } elseif (($argv[1] ?? '') === 'tick') {
        foreach (run($db, "SELECT id FROM slots WHERE starts_at>? OR id IN (SELECT slot_id FROM offers WHERE state='offered') ORDER BY id", [time()])->fetchAll() as $value) {
            promote($db, intValue(objectValue($value)['id']), time());
        }
    } else {
        throw new DomainException('slot ID START_UNIX BOOKED_USER BOOKING_KEY | cancel ID BOOKING_KEY | tick');
    }
});
