<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/referrals.php';
if (PHP_SAPI !== 'cli' || getenv('DEMO_MODE') !== '1') {
    exit(1);
}
$db = database();
$now = time();
$code = joinProgram($db, 1001, null, $now);
joinProgram($db, 1002, $code, $now);
sql($db, "INSERT INTO orders(id,user_id,amount,paid_at,status) VALUES('demo-order',1002,150000,?,'paid')
    ON CONFLICT(id) DO NOTHING", [$now]);
awardOrder($db, 'demo-order', $now);
awardOrder($db, 'demo-order', $now);
echo rewardHistory($db, 1001);
refundOrder($db, 'demo-order', $now);
refundOrder($db, 'demo-order', $now);
echo rewardHistory($db, 1001);
