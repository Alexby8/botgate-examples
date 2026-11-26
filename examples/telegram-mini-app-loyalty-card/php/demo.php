<?php
declare(strict_types=1);

require __DIR__.'/common.php';
require __DIR__.'/domain.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
$db->exec('PRAGMA foreign_keys=ON');
$schema = file_get_contents(__DIR__.'/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Cannot read schema.sql');
}
$db->exec($schema);
$user = 1001;
$start = 1_800_000_000;
$day = 86_400;
atomic($db, function () use ($db, $user, $start, $day): void {
    run($db, 'INSERT INTO orders(id,user_id,total_minor) VALUES(?,?,?)', ['ORDER-001', $user, 100000]);
    pay($db, 'ORDER-001', 100000, $start);
    printf("День 0: %d\n", balance($db, $user, $start));

    $secondPayment = $start + 30 * $day;
    run($db, 'INSERT INTO orders(id,user_id,total_minor) VALUES(?,?,?)', ['ORDER-002', $user, 100000]);
    requestPoints($db, $user, 'ORDER-002', 30, $secondPayment);
    pay($db, 'ORDER-002', 97000, $secondPayment);
    pay($db, 'ORDER-002', 97000, $secondPayment);
    printf("День 30: %d\n", balance($db, $user, $secondPayment));

    $expiry = $start + 90 * $day;
    expirePoints($db, $user, $expiry);
    printf("День 90: %d\n", balance($db, $user, $expiry));

    $refund = $expiry + $day;
    refund($db, 'ORDER-002', $refund);
    refund($db, 'ORDER-002', $refund);
    printf("День 91: %d\n", balance($db, $user, $refund));
    foreach (run($db, 'SELECT delta,kind FROM ledger ORDER BY id')->fetchAll() as $entry) {
        $entry = objectValue($entry);
        printf("%+d %s\n", intValue($entry['delta']), stringValue($entry['kind']));
    }
});
