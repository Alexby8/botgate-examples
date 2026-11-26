# Каталог с листанием на PHP

Первая и вторая страницы и обработка старой кнопки.

## Запуск

Нужны PHP 8.3+, PDO SQLite, cURL и HTTPS с корнем `public/`.

Задайте переменные `BOT_DB_PATH`, `BOT_TOKEN`, `WEBHOOK_SECRET`, `WEBHOOK_URL`. Путь к SQLite должен быть абсолютным и лежать за пределами public/. Укажите HTTPS-адрес /webhook.php в WEBHOOK_URL. После действий запускайте worker.php.

Отправьте /catalog и выберите «Канцелярия». Вместе с ассортиментом обновляйте categories.version.

Из каталога примера выполните.

```sh
php init.php
php setup-webhook.php
php worker.php
```

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-product-catalog-pagination)
