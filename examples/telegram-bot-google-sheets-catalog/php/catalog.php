<?php

declare(strict_types=1);
require_once __DIR__.'/common.php';
/** @param array<string,mixed> $response
 * @return array<string,array{id:string,title:string,price_minor:int,active:bool,stock:int}>
 */
function parseCatalog(array $response): array
{
    $rows = $response['values'] ?? null;
    if (! is_array($rows) || ! array_is_list($rows) || count($rows) < 2 || count($rows) > 1001
        || $rows[0] !== ['id', 'title', 'price_minor', 'active', 'stock']) {
        throw new DomainException('Неверная шапка, пустая таблица или больше 1000 строк');
    }
    $products = [];
    foreach (array_slice($rows, 1) as $offset => $cells) {
        $line = $offset + 2;
        if (! is_array($cells) || ! array_is_list($cells)) {
            throw new DomainException('Строка '.$line.': неверный формат');
        }
        if ($cells === [] || count(array_filter($cells, fn (mixed $v): bool => $v !== '')) === 0) {
            continue;
        }
        [$id, $title, $price, $active, $stock] = array_pad($cells, 5, null);
        if (count($cells) > 5 || ! is_string($id) || ! preg_match('/^[a-z][a-z0-9_-]{2,39}$/D', $id)
            || isset($products[$id]) || ! is_string($title) || trim($title) === '' || mb_strlen($title) > 120
            || ! is_int($price) || $price < 1 || $price > 100000000
            || ! is_bool($active) || ! is_int($stock) || $stock < 0 || $stock > 1000000) {
            throw new DomainException('Строка '.$line.': ID, цена, флаг или остаток неверны');
        }
        $products[$id] = ['id' => $id, 'title' => trim($title), 'price_minor' => $price, 'active' => $active, 'stock' => $stock];
    }
    if ($products === []) {
        throw new DomainException('Каталог не содержит товаров');
    }
    ksort($products);

    return $products;
}
/** @param array<string,mixed> $response */
function replaceCatalog(PDO $db, array $response, int $now): void
{
    $products = parseCatalog($response);
    atomic($db, function () use ($db, $products, $now): void {
        $old = row($db, 'SELECT products FROM catalog WHERE id=1');
        if ($old !== null) {
            $before = objectValue(json_decode(stringValue($old['products']), true, 512, JSON_THROW_ON_ERROR));
            if (array_diff(array_keys($before), array_keys($products)) !== []) {
                throw new DomainException('Удалён ID. Для отключения верните строку и снимите active');
            }
        }
        $json = json_encode($products, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        run(
            $db,
            'INSERT INTO catalog VALUES(1,?,?,?) ON CONFLICT(id) DO UPDATE SET version=excluded.version,checked_at=excluded.checked_at,products=excluded.products',
            [hash('sha256', $json), $now, $json]
        );
        run($db, 'INSERT INTO sync_state VALUES(1,?,NULL) ON CONFLICT(id) DO UPDATE SET attempted_at=excluded.attempted_at,error_code=NULL', [$now]);
    });
}

function catalogText(PDO $db, string $command, int $now): string
{
    $snapshot = row($db, 'SELECT * FROM catalog WHERE id=1');
    if ($snapshot === null) {
        return 'Каталог пока недоступен. Попробуйте позже.';
    }
    $products = objectValue(json_decode(stringValue($snapshot['products']), true, 512, JSON_THROW_ON_ERROR));
    $stale = intValue($snapshot['checked_at']) < $now - 900;
    $notice = 'Данные давно не обновлялись. Цену и наличие уточните у менеджера.';
    if (str_starts_with($command, '/item ')) {
        $id = trim(substr($command, 6));
        $product = isset($products[$id]) ? objectValue($products[$id]) : null;
        if ($product === null || $product['active'] !== true) {
            return 'Товар отключён или не найден.'.($stale ? "\n".$notice : '');
        }

        return stringValue($product['title']).' — '.money(intValue($product['price_minor'])).' ₽. '.
            ($stale ? $notice : (intValue($product['stock']) > 0 ? 'Есть в каталоге.' : 'Нет в наличии.'));
    }
    $active = array_values(array_filter($products, fn (mixed $p): bool => objectValue($p)['active'] === true));
    $pages = max(1, (int) ceil(count($active) / 10));
    $page = preg_match('/^\/catalog ([1-9][0-9]{0,2})$/D', $command, $match) ? (int) $match[1] : 1;
    $page = min($page, $pages);
    $lines = ['Каталог. Карточка: /item ID. Страница: /catalog 2'];
    foreach (array_slice($active, ($page - 1) * 10, 10) as $value) {
        $p = objectValue($value);
        $lines[] = stringValue($p['id']).' · '.stringValue($p['title']).' · '.money(intValue($p['price_minor'])).' ₽'.
            (intValue($p['stock']) > 0 ? '' : ' · нет в наличии');
    }
    if ($active === []) {
        $lines[] = 'Сейчас нет доступных товаров.';
    }
    $lines[] = 'Страница '.$page.' / '.$pages;
    if ($stale) {
        $lines[] = $notice;
    }

    return implode("\n", $lines);
}

function money(int $minor): string
{
    return intdiv($minor, 100).','.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT);
}
