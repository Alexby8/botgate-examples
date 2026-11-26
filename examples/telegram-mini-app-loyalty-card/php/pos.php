<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/domain.php';
$argv = $argv ?? [];
$db = database();
$cmd = $argv[1] ?? '';
atomic($db, function () use ($db, $cmd, $argv): void {
    $id = token($argv[2] ?? '');
    if ($cmd === 'order') {
        $user = positive($argv[3] ?? 0, PHP_INT_MAX);
        $total = positive($argv[4] ?? 0, 100000000);
        $old = row($db, 'SELECT * FROM orders WHERE id=?', [$id]);
        if ($old) {
            if (intValue($old['user_id']) !== $user || intValue($old['total_minor']) !== $total) {
                throw new DomainException('Order conflict');
            }
        return;
        }
        run($db, 'INSERT INTO orders(id,user_id,total_minor) VALUES(?,?,?)', [$id,$user,$total]);
    } elseif ($cmd === 'pay') {
        pay($db, $id, positive($argv[3] ?? 0, 100000000), time());
    } elseif ($cmd === 'refund') {
        echo refund($db, $id, time()).PHP_EOL;
    } else {
        throw new DomainException('Use order ID USER TOTAL_MINOR | pay ID CASH_MINOR | refund ID');
    }
});
