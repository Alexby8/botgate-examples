<?php

declare(strict_types=1);

require __DIR__.'/catalog.php';

$arguments = $argv ?? [];
$command = count($arguments) > 1 ? implode(' ', array_slice($arguments, 1)) : '/catalog';
echo catalogText(database(), $command, time()).PHP_EOL;
