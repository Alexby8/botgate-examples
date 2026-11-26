<?php
declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/referrals.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$db = database();
// Work in bounded batches. Terminal/ineligible orders are excluded from selection.
$statement = sql($db, "SELECT o.id FROM orders o JOIN referrals r ON r.invitee=o.user_id
    WHERE o.status='paid' AND o.amount>=100000 AND o.paid_at>=r.bound_at
    AND NOT EXISTS (SELECT 1 FROM rewards w WHERE w.program='first-purchase-v1' AND w.invitee=o.user_id)
    ORDER BY o.paid_at,o.id LIMIT 100");
$ids = [];
while (($order = row($statement)) !== null) {
    $ids[] = (string) $order['id'];
}
$statement->closeCursor();
foreach ($ids as $id) {
    awardOrder($db, $id, time());
}
echo "Reconciled\n";
