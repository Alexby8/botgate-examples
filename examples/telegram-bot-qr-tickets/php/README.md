# Билеты с QR на PHP

Выдача PNG в боте и однократная запись прохода в Mini App.

## Запуск

Нужны PHP 8.3+ и расширения cURL, GD, PDO SQLite, а также Composer. Для проверки в Telegram настройте HTTPS с корнем в `public/`.

Задайте `BOT_DB_PATH`, `BOT_TOKEN`, `WEBHOOK_URL`, `WEBHOOK_SECRET`, `TICKET_KEY`. Храните SQLite-файл вне публичного каталога. Укажите WEBHOOK_URL для /webhook.php.

Выполните команды из каталога примера.

```sh
composer install
php init.php
php setup-webhook.php
php checker-admin.php 123456789 on
```

Подставьте ID проверяющего и задайте TICKET_KEY длиной от 32 случайных символов. Укажите /checker.html в меню бота. Гость отправляет /ticket, сотрудник в Mini App сканирует код и подтверждает вход.

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-qr-tickets)
