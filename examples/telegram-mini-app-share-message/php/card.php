<?php
declare(strict_types=1);

/** @return array{sku:string,title:string,text:string,url:string} */
function publicCard(string $sku): array
{
    // Replace this catalog with a SELECT of public fields and publication status.
    $products = [
        'mug-blue' => ['title' => 'Синяя кружка, 350 мл', 'price' => 650],
        'notebook' => ['title' => 'Блокнот для заметок', 'price' => 390],
    ];
    $product = $products[$sku] ?? null;
    if ($product === null) {
        throw new DomainException('Товар не опубликован');
    }
    $base = rtrim(setting('PUBLIC_SHOP_URL'), '/');
    if (!filter_var($base, FILTER_VALIDATE_URL) || parse_url($base, PHP_URL_SCHEME) !== 'https'
        || strpbrk($base, '?#') !== false || parse_url($base, PHP_URL_USER) !== null) {
        throw new RuntimeException('HTTPS public shop URL required');
    }
    $url = $base.'/products/'.rawurlencode($sku);
    return ['sku' => $sku, 'title' => $product['title'],
        'text' => $product['title']."\nЦена на момент подготовки: ".$product['price']." ₽\n".$url,
        'url' => $url];
}

/**
 * @param array{sku:string,title:string,text:string,url:string} $card
 * @return array<string,mixed>
 */
function preparedPayload(int $user, array $card): array
{
    return [
        'user_id' => $user,
        'result' => [
            'type' => 'article', 'id' => hash('sha256', $card['sku'].'|'.$card['text']),
            'title' => $card['title'],
            'input_message_content' => ['message_text' => $card['text']],
            'reply_markup' => ['inline_keyboard' => [[['text' => 'Открыть товар', 'url' => $card['url']]]]],
        ],
        'allow_user_chats' => true, 'allow_group_chats' => true,
        'allow_bot_chats' => false, 'allow_channel_chats' => false,
    ];
}
