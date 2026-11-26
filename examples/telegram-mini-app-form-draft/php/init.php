<?php

declare(strict_types=1);
require __DIR__.'/common.php';
$sql = file_get_contents(__DIR__.'/schema.sql');
if ($sql === false) {
    throw new RuntimeException('Schema missing');
}
database()->exec($sql);
echo "Database ready\n";
