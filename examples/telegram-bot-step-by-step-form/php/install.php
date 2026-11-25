<?php
declare(strict_types=1);
require __DIR__.'/database.php';

if (!is_dir(__DIR__.'/var') && !mkdir(__DIR__.'/var', 0700)) {
    throw new RuntimeException('Cannot create private storage');
}
$schema = file_get_contents(__DIR__.'/schema.sql');
if ($schema === false) {
    throw new RuntimeException('Cannot read schema.sql');
}
database()->exec($schema);
echo "Storage ready\n";
