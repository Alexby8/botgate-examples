<?php

declare(strict_types=1);

require __DIR__.'/common.php';
require __DIR__.'/bot.php';
require __DIR__.'/app.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$author = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$approver = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if ($author === false || $approver === false || $author === $approver) {
    throw new InvalidArgumentException('Use seed.php AUTHOR_ID APPROVER_ID with two distinct users');
}
$db = database();
atomic($db, function () use ($db,$author,$approver): void {
    sql($db, 'INSERT INTO approvers(user_id,limit_minor) VALUES(?,5000000) ON CONFLICT(user_id) DO NOTHING', [$approver]);
    foreach ([[142,'Два монитора',4000000],[143,'Комплект кабелей',600000]] as [$id,$name,$price]) {
        $changed = sql($db, 'INSERT INTO requests(id,title,amount_minor,author,approver) VALUES(?,?,?,?,?)
            ON CONFLICT(id) DO NOTHING', [$id,$name,$price,$author,$approver])->rowCount();
        if ($changed === 1) {
            sendCard($db, $id);
        }
    }
});
echo "Demo requests 142 and 143 ready\n";
