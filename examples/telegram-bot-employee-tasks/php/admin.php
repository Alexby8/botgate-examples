<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/outbox.php';
require __DIR__.'/domain.php';
$argv = $argv ?? [];
$db = database();
atomic($db, function () use ($db, $argv): void {
    $cmd = $argv[1] ?? '';
    if ($cmd === 'employee') {
        $id = positive($argv[2] ?? 0, PHP_INT_MAX);
        $role = $argv[3] ?? '';
        $name = $argv[4] ?? '';
        if (!in_array($role, ['manager','employee'], true) || mb_strlen($name) < 1 || mb_strlen($name) > 80) {
            throw new DomainException('Invalid employee');
        }
        run($db, 'INSERT INTO employees(user_id,name,role) VALUES(?,?,?)', [$id,$name,$role]);
    } elseif ($cmd === 'disable') {
        run($db, 'UPDATE employees SET active=0 WHERE user_id=?', [positive($argv[2] ?? 0, PHP_INT_MAX)]);
    } elseif ($cmd === 'tick') {
        remind($db, time());
    } else {
        throw new DomainException('employee USER manager|employee NAME | disable USER | tick');
    }
});
