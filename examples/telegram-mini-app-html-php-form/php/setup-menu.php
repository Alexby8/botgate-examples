<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__.'/telegram.php';
$url = getenv('APP_URL');
if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)
    || parse_url($url, PHP_URL_SCHEME) !== 'https') {
    throw new RuntimeException('Set an HTTPS APP_URL');
}
telegram('setChatMenuButton', ['menu_button' => [
    'type' => 'web_app', 'text' => 'Оставить заявку',
    'web_app' => ['url' => $url],
]]);
echo "Menu button configured\n";
