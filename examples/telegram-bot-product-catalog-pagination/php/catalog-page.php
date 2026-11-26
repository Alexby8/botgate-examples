<?php
declare(strict_types=1);

/** @return array{page: int, text: string, reply_markup: array<string, mixed>} */
function catalogPage(PDO $db, int $categoryId, int $requestedPage, string $token, int $revision): array
{
    $size = 5;
    $count = $db->prepare('SELECT COUNT(*) FROM products WHERE category_id = ? AND is_active = 1');
    $count->execute([$categoryId]);
    $total = (int) $count->fetchColumn();
    $pages = max(1, (int) ceil($total / $size));
    $page = max(0, min($requestedPage, $pages - 1));
    $query = $db->prepare(
        'SELECT id, title, price_minor FROM products
         WHERE category_id = :category AND is_active = 1
         ORDER BY sort_order, id LIMIT :size OFFSET :offset'
    );
    $query->bindValue(':category', $categoryId, PDO::PARAM_INT);
    $query->bindValue(':size', $size, PDO::PARAM_INT);
    $query->bindValue(':offset', $page * $size, PDO::PARAM_INT);
    $query->execute();
    $lines = ['Каталог · страница '.($page + 1).' из '.$pages];
    while (($product = $query->fetch(PDO::FETCH_ASSOC)) !== false) {
        if (!is_array($product) || !is_int($product['price_minor'] ?? null) || !is_string($product['title'] ?? null)) {
            throw new RuntimeException('Invalid product data');
        }
        $price = number_format($product['price_minor'] / 100, 2, ',', ' ');
        $lines[] = $product['title'].' — '.$price.' ₽';
    }
    if ($total === 0) {
        $lines[] = 'В этой категории пока нет доступных позиций.';
    }
    $buttons = [];
    if ($page > 0) {
        $buttons[] = ['text' => 'Назад', 'callback_data' => 'nav:'.$token.':'.$revision.':'.($page - 1)];
    }
    if ($page + 1 < $pages) {
        $buttons[] = ['text' => 'Вперёд', 'callback_data' => 'nav:'.$token.':'.$revision.':'.($page + 1)];
    }
    return [
        'page' => $page, 'text' => implode("\n", $lines),
        'reply_markup' => ['inline_keyboard' => $buttons === [] ? [] : [$buttons]],
    ];
}
