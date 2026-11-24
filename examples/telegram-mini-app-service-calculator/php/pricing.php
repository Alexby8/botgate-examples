<?php

declare(strict_types=1);

/**
 * @param  array<string, mixed>  $input
 * @return array{qty:int, size:string, color:string, urgent:string, delivery:string}
 */
function options(array $input): array
{
    $raw = $input['qty'] ?? null;
    $qty = is_int($raw) || is_string($raw)
        ? filter_var($raw, FILTER_VALIDATE_INT)
        : false;
    if ($qty === false || $qty < 1 || $qty > 1000) {
        throw new DomainException('Количество должно быть целым числом от 1 до 1000');
    }
    $choices = [
        'size' => ['A4', 'A3'],
        'color' => ['bw', 'color'],
        'urgent' => ['no', 'yes'],
        'delivery' => ['pickup', 'courier'],
    ];
    $values = [];
    foreach ($choices as $name => $allowed) {
        $value = $input[$name] ?? null;
        if (! is_string($value) || ! in_array($value, $allowed, true)) {
            throw new DomainException('Недопустимое значение поля '.$name);
        }
        $values[$name] = $value;
    }

    return ['qty' => $qty, 'size' => $values['size'], 'color' => $values['color'],
        'urgent' => $values['urgent'], 'delivery' => $values['delivery']];
}

/** @param array{qty:int, size:string, color:string, urgent:string, delivery:string} $p */
function price(array $p): int
{
    $unit = ($p['color'] === 'color' ? 2800 : 1200)
        * ($p['size'] === 'A3' ? 2 : 1);
    $production = 15000 + $p['qty'] * $unit;
    if ($p['urgent'] === 'yes') {
        $production = intdiv($production * 125 + 50, 100);
    }

    return $production + ($p['delivery'] === 'courier' ? 35000 : 0);
}
