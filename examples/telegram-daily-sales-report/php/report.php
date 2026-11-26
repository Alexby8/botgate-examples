<?php

declare(strict_types=1);
require __DIR__.'/common.php';
require __DIR__.'/reporting.php';
$zone = envRequired('REPORT_TIMEZONE');
$date = $argv[1] ?? (new DateTimeImmutable('yesterday', new DateTimeZone($zone)))->format('Y-m-d');
$lock = fopen(envRequired('APP_DB').'.build.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit(1);
}
try {
    $id = buildReport(database(), $date, $zone, envRequired('REPORT_CHAT_ID'), time());
    echo 'Report ready: '.$id.PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, 'Report NOT built: inspect source availability and watermark'.PHP_EOL);
    exit(1);
}
