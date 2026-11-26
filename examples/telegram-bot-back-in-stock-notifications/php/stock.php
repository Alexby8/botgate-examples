<?php
declare(strict_types=1);
require_once __DIR__.'/common.php';

/** @return array<string, mixed> */
function watchCard(PDO $db, int $userId, int $productId): array
{
    $q = $db->prepare('SELECT p.*, w.id AS watch_id, w.generation, w.active FROM products p
        LEFT JOIN watches w ON w.product_id = p.id AND w.user_id = ? WHERE p.id = ?');
    $q->execute([$userId, $productId]);
    $row = row($q);
    if (!$row) throw new InvalidArgumentException('Нет товара');
    $button = (int) ($row['active'] ?? 0) === 1
        ? ['text' => 'Отменить подписку', 'callback_data' => 'unwatch:'.$row['watch_id'].':'.$row['generation']]
        : ['text' => 'Сообщить о поступлении', 'callback_data' => 'watch:'.$productId];
    return ['chat_id' => $userId, 'text' => $row['title'],
        'reply_markup' => ['inline_keyboard' => [[$button], [['text' => 'Открыть товар', 'url' => $row['url']]]]]];
}

function subscribe(PDO $db, int $userId, int $productId, int $updateId): string
{
    return atomic($db, function () use ($db, $userId, $productId, $updateId): string {
        $q = $db->prepare('INSERT INTO watch_events VALUES (?) ON CONFLICT DO NOTHING');
        $q->execute([$updateId]);
        if ($q->rowCount() === 0) return 'Это действие уже обработано.';
        $q = $db->prepare('SELECT * FROM products WHERE id = ?');
        $q->execute([$productId]);
        $product = row($q);
        if (!$product) return 'Товар не найден.';
        if ((int) $product['available'] > 0) return 'Товар уже в наличии. Откройте карточку.';
        $db->prepare('INSERT INTO watches (user_id, product_id) VALUES (?, ?)
            ON CONFLICT(user_id, product_id) DO UPDATE
            SET active = 1, generation = generation + 1 WHERE active = 0')
            ->execute([$userId, $productId]);
        return 'Подписка сохранена. Сообщим один раз, когда товар появится.';
    });
}

function unsubscribe(PDO $db, int $userId, int $watchId, int $generation): void
{
    atomic($db, function () use ($db, $userId, $watchId, $generation): void {
        $q = $db->prepare('UPDATE watches SET active = 0
            WHERE id = ? AND user_id = ? AND generation = ?');
        $q->execute([$watchId, $userId, $generation]);
        if ($q->rowCount() === 1) {
            $db->prepare("UPDATE stock_deliveries SET state = 'cancelled'
                WHERE watch_id = ? AND generation = ? AND state = 'pending'")
                ->execute([$watchId, $generation]);
        }
    });
}

function updateStock(PDO $db, int $productId, int $available, int $sourceVersion): bool
{
    if ($available < 0 || $sourceVersion < 1) throw new InvalidArgumentException('Некорректный остаток');
    return atomic($db, function () use ($db, $productId, $available, $sourceVersion): bool {
        $q = $db->prepare('SELECT * FROM products WHERE id = ?');
        $q->execute([$productId]);
        $old = row($q);
        if (!$old) throw new InvalidArgumentException('Нет товара');
        if ($sourceVersion <= (int) $old['source_version']) return false;
        $restocked = (int) $old['available'] === 0 && $available > 0;
        $episode = (int) $old['episode'] + (int) $restocked;
        $db->prepare('UPDATE products SET available = ?, source_version = ?, episode = ? WHERE id = ?')
            ->execute([$available, $sourceVersion, $episode, $productId]);
        if ($restocked) {
            $db->prepare('INSERT INTO stock_deliveries (watch_id, generation, episode, ready_at)
                SELECT id, generation, ?, ? FROM watches WHERE product_id = ? AND active = 1
                ON CONFLICT DO NOTHING')->execute([$episode, time(), $productId]);
        }
        return true;
    });
}

/** @param callable(string, array<string, mixed>): array{state: string, result?: mixed, code?: int, retry_after?: int} $send */
function notifyOne(PDO $db, callable $send): bool
{
    $job = atomic($db, function () use ($db) {
        $q = $db->prepare("SELECT d.*, w.user_id, w.product_id, w.active,
            w.generation AS current_generation, p.episode AS current_episode, p.available, p.title, p.url
            FROM stock_deliveries d JOIN watches w ON w.id = d.watch_id
            JOIN products p ON p.id = w.product_id
            WHERE d.state = 'pending' AND d.ready_at <= ?
              AND NOT EXISTS (SELECT 1 FROM stock_deliveries x
                WHERE x.watch_id = d.watch_id AND x.generation = d.generation
                  AND x.id <> d.id AND x.state IN ('sending', 'unknown'))
            ORDER BY d.id LIMIT 1");
        $q->execute([time()]);
        $job = row($q);
        if (!$job) return null;
        $valid = (int) $job['active'] === 1 && (int) $job['available'] > 0
            && $job['generation'] === $job['current_generation']
            && $job['episode'] === $job['current_episode'];
        $db->prepare('UPDATE stock_deliveries SET state = ?, attempts = attempts + 1, started_at = ? WHERE id = ?')
            ->execute([$valid ? 'sending' : 'cancelled', time(), $job['id']]);
        return $valid ? $job : [];
    });
    if ($job === null) return false;
    if ($job === []) return true;
    try {
        $outcome = $send('sendMessage', [
            'chat_id' => $job['user_id'], 'text' => $job['title'].' снова в наличии. Остаток не зарезервирован.',
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Открыть товар', 'url' => $job['url']]]]],
        ]);
    } catch (Throwable) {
        $outcome = ['state' => 'unknown'];
    }
    atomic($db, function () use ($db, $job, $outcome): void {
        $result = $outcome['result'] ?? null;
        $messageId = is_array($result) ? ($result['message_id'] ?? null) : null;
        $sent = $outcome['state'] === 'ok' && is_int($messageId);
        $limited = $outcome['state'] === 'rejected' && ($outcome['code'] ?? null) === 429;
        $state = $sent ? 'sent' : ($outcome['state'] === 'rejected' ? 'failed' : 'unknown');
        if ($limited && (int) $job['attempts'] + 1 < 5) $state = 'pending';
        $db->prepare('UPDATE stock_deliveries SET state = ?, message_id = ?, ready_at = ? WHERE id = ?')
            ->execute([$state, $sent ? $messageId : null, time() + ($outcome['retry_after'] ?? 60), $job['id']]);
        if ($sent || ($outcome['code'] ?? null) === 403) {
            $db->prepare('UPDATE watches SET active = 0 WHERE id = ? AND generation = ?')
                ->execute([$job['watch_id'], $job['generation']]);
        }
    });
    return true;
}
