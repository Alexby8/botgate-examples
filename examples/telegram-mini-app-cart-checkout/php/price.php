<?php

declare(strict_types=1);

require __DIR__.'/common.php';
if (PHP_SAPI !== 'cli') {
    exit(1);
}
$price = filter_var($argv[1] ?? '', FILTER_VALIDATE_INT, ['options'=>['min_range'=>0,'max_range'=>100000000]]);
if ($price === false) {
    throw new InvalidArgumentException('Use price.php PRICE_IN_KOPECKS');
}
sql(database(), 'UPDATE products SET price_minor=? WHERE id=1', [$price]);
echo "Green tea price updated\n";
