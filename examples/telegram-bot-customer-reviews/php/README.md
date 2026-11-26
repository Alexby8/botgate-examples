# Отзывы клиентов на PHP

Оценка, комментарий, сигнал менеджеру и разрешение на публикацию.

## Запуск

Нужны PHP 8.3+ и расширения cURL, mbstring, PDO SQLite. Для проверки в Telegram настройте HTTPS с корнем в `public/`.

Задайте `APP_DB`, `BOT_TOKEN`, `WEBHOOK_URL`, `WEBHOOK_SECRET`, `MANAGER_CHAT_ID`. Храните SQLite-файл вне публичного каталога. Укажите WEBHOOK_URL для /webhook.php.

Выполните команды из каталога примера.

```sh
php init.php
php setup-webhook.php
php demo-order.php 123456789
php invite.php R-1042
php worker.php
```

Подставьте ID клиента, который уже открыл чат бота. У бота должен быть доступ и к чату менеджера. После оценки, текста и решения о публикации запускайте worker.php.

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-customer-reviews)
