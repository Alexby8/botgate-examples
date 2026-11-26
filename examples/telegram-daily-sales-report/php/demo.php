<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/reporting.php';
$db = database();
$date = $argv[1] ?? '2026-11-18';
$b = dayBounds($date, envRequired('REPORT_TIMEZONE'));
foreach (['money_events', 'leads', 'source_cursors', 'reports'] as $table) {
    $check = $db->prepare('SELECT COUNT(*) FROM '.$table);
    $check->execute();
    if ($check->fetchColumn() !== 0) {
        throw new RuntimeException('Use an empty demo DB');
    }
}
$db->beginTransaction();
$q = $db->prepare('INSERT INTO money_events VALUES(?,?,?,?,?,?)');
$q->execute(['pay-old','R-1','payment',100000,'RUB',$b['previous_start'] + 3600]);
$q->execute(['pay-1','R-2','payment',150000,'RUB',$b['start'] + 3600]);
$q->execute(['refund-1','R-1','refund',20000,'RUB',$b['start'] + 7200]);
$q = $db->prepare('INSERT INTO leads VALUES(?,?)');
$q->execute(['L-1',$b['previous_start'] + 100]);
$q->execute(['L-2',$b['start'] + 100]);
$q->execute(['L-3',$b['start'] + 200]);
$q = $db->prepare('INSERT INTO source_cursors VALUES(?,?,?)');
foreach (['money','leads'] as $s) {
    $q->execute([$s,$b['previous_start'],$b['end']]);
}
$db->commit();
echo "Demo dataset ready\n";
