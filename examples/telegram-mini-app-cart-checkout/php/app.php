<?php

declare(strict_types=1);

final class Conflict extends DomainException
{
}

function revision(PDO $db, int $user): int
{
    sql($db, 'INSERT INTO carts(user_id) VALUES(?) ON CONFLICT(user_id) DO NOTHING', [$user]);
    $cart = row(sql($db, 'SELECT revision FROM carts WHERE user_id=?', [$user]))
        ?? throw new RuntimeException('Cart missing');
    return (int) $cart['revision'];
}

function checkRevision(PDO $db, int $user, int $expected): void
{
    if (revision($db, $user) !== $expected) {
        throw new Conflict('Корзина изменилась. Откройте свежий расчёт.');
    }
}

/** @return list<array<string, int|string|null>> */
function items(PDO $db, int $user): array
{
    return rows(sql($db, 'SELECT p.id,p.name,p.price_minor,p.stock,ci.quantity FROM cart_items ci
        JOIN products p ON p.id=ci.product_id WHERE ci.user_id=? ORDER BY p.id', [$user]));
}

/** @return array<string, mixed> */
function state(PDO $db, int $user): array
{
    return [
        'revision' => revision($db, $user),
        'cart' => items($db, $user),
        'products' => rows(sql($db, 'SELECT * FROM products ORDER BY id')),
    ];
}

function setQuantity(PDO $db, int $user, int $expected, int $product, int $quantity): void
{
    checkRevision($db, $user, $expected);
    $stock = row(sql($db, 'SELECT stock FROM products WHERE id=?', [$product]));
    if ($stock === null || $quantity < 0 || $quantity > 20) {
        throw new InvalidArgumentException('Проверьте товар и количество от 0 до 20.');
    }
    if ($quantity > (int) $stock['stock']) {
        throw new Conflict('Такого количества больше нет на складе.');
    }
    if ($quantity === 0) {
        sql($db, 'DELETE FROM cart_items WHERE user_id=? AND product_id=?', [$user, $product]);
    } else {
        sql($db, 'INSERT INTO cart_items(user_id,product_id,quantity) VALUES(?,?,?)
            ON CONFLICT(user_id,product_id) DO UPDATE SET quantity=excluded.quantity', [$user,$product,$quantity]);
    }
    sql($db, 'UPDATE carts SET revision=revision+1 WHERE user_id=?', [$user]);
}

/** @return array{total:int, items:list<array{id:int, name:string, price_minor:int, quantity:int}>} */
function snapshot(PDO $db, int $user): array
{
    $lines = items($db, $user);
    if ($lines === []) {
        throw new Conflict('Корзина пуста.');
    }
    $total = 0;
    $result = [];
    foreach ($lines as $line) {
        $quantity = (int) $line['quantity'];
        $price = (int) $line['price_minor'];
        if ($quantity > (int) $line['stock']) {
            throw new Conflict('Остаток изменился. Уменьшите количество в корзине.');
        }
        $total += $quantity * $price;
        $result[] = ['id'=>(int) $line['id'], 'name'=>(string) $line['name'], 'price_minor'=>$price, 'quantity'=>$quantity];
    }
    return ['total'=>$total, 'items'=>$result];
}

/** @return array<string, mixed> */
function preview(PDO $db, int $user, int $expected, int $now): array
{
    checkRevision($db, $user, $expected);
    $snapshot = snapshot($db, $user);
    $key = bin2hex(random_bytes(16));
    sql($db, 'INSERT INTO quotes(id,user_id,revision,payload,expires_at) VALUES(?,?,?,?,?)',
        [$key,$user,$expected,json_encode($snapshot, JSON_THROW_ON_ERROR),$now+300]);
    return ['quote'=>$key,'expires_at'=>$now+300,...$snapshot];
}

/** @return array{order_id:int,total:int} */
function place(PDO $db, int $user, string $quoteId, string $operation, int $now): array
{
    // A replay must succeed even after the quote expires or the cart is cleared.
    $old = row(sql($db, 'SELECT * FROM orders WHERE user_id=? AND operation_key=?', [$user,$operation]));
    if ($old !== null) {
        if ($old['quote_id'] !== $quoteId) {
            throw new InvalidArgumentException('Ключ операции относится к другому расчёту.');
        }
        return ['order_id'=>(int) $old['id'], 'total'=>(int) $old['total_minor']];
    }
    $quote = row(sql($db, 'SELECT * FROM quotes WHERE id=? AND user_id=?', [$quoteId,$user]));
    if ($quote === null || (int) $quote['expires_at'] <= $now) {
        throw new Conflict('Расчёт устарел. Получите новый и подтвердите сумму.');
    }
    checkRevision($db, $user, (int) $quote['revision']);
    $snapshot = snapshot($db, $user);
    $payload = json_encode($snapshot, JSON_THROW_ON_ERROR);
    if ($payload !== $quote['payload']) {
        throw new Conflict('Цена или состав заказа изменились. Подтвердите новый расчёт.');
    }

    sql($db, 'INSERT INTO orders(user_id,quote_id,operation_key,total_minor,payload,created_at) VALUES(?,?,?,?,?,?)',
        [$user,$quoteId,$operation,$snapshot['total'],$payload,$now]);
    $orderId = (int) $db->lastInsertId();
    foreach ($snapshot['items'] as $line) {
        $changed = sql($db, 'UPDATE products SET stock=stock-? WHERE id=? AND stock>=?',
            [$line['quantity'],$line['id'],$line['quantity']])->rowCount();
        if ($changed !== 1) {
            throw new Conflict('Остаток изменился. Уменьшите количество.');
        }
    }
    sql($db, 'DELETE FROM cart_items WHERE user_id=?', [$user]);
    sql($db, 'UPDATE carts SET revision=revision+1 WHERE user_id=?', [$user]);
    return ['order_id'=>$orderId,'total'=>$snapshot['total']];
}
