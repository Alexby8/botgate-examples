<?php

declare(strict_types=1);

/**
 * @return array<string,mixed>
 */
function objectValue(mixed $value): array
{
    if (!is_array($value)) {
        throw new InvalidArgumentException('Expected object');
    }
    $result = [];
    foreach ($value as $key => $item) {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Expected object keys');
        }
        $result[$key] = $item;
    }
    return $result;
}

function stringValue(mixed $value): string
{
    if (!is_string($value)) {
        throw new InvalidArgumentException('Expected string');
    }
    return $value;
}

function intValue(mixed $value): int
{
    if (!is_int($value) && !is_string($value)) {
        throw new InvalidArgumentException('Expected integer');
    }
    $integer = filter_var($value, FILTER_VALIDATE_INT);
    if ($integer === false) {
        throw new InvalidArgumentException('Invalid integer');
    }
    return $integer;
}
