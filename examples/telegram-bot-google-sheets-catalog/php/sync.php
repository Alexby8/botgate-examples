<?php

declare(strict_types=1);
require __DIR__.'/catalog.php';
require __DIR__.'/google.php';
$lock = fopen(envRequired('APP_DB').'.sync.lock', 'c');
if ($lock === false) {
    throw new RuntimeException('Cannot open sync lock');
}
if (! flock($lock, LOCK_EX | LOCK_NB)) {
    fclose($lock);
    exit(0);
}
$db = database();
try {
    replaceCatalog($db, readSheet(), time());
    echo "Catalog checked and installed\n";
} catch (Throwable $e) {
    $code = $e instanceof DomainException ? $e->getMessage() : 'Ошибка доступа, сети или ответа Google';
    run($db, 'INSERT INTO sync_state VALUES(1,?,?) ON CONFLICT(id) DO UPDATE SET attempted_at=excluded.attempted_at,error_code=excluded.error_code', [time(), $code]);
    fwrite(STDERR, $code."\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
