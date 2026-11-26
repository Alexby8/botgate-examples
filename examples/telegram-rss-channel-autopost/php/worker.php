<?php

declare(strict_types=1);
require __DIR__.'/domain.php';
require __DIR__.'/transport.php';
drain(database(), 1, 120);
