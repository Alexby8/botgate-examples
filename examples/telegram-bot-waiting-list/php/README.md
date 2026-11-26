# Лист ожидания на PHP

Освобождение времени и предложения по очереди.

## Запуск

Нужны PHP 8.3+ и расширения cURL, PDO SQLite. Для проверки в Telegram настройте HTTPS с корнем в `public/`.

Задайте `APP_DB`, `BOT_TOKEN`, `WEBHOOK_URL`, `WEBHOOK_SECRET`. Храните SQLite-файл вне публичного каталога. Укажите WEBHOOK_URL для /webhook.php.

Выполните команды из каталога примера.

```sh
php init.php
php setup-webhook.php
php admin.php slot 1 START_UNIX 1001 booking-original
```

Подставьте будущий Unix timestamp и ID исходного владельца. Два клиента отправляют /join 1. Запустите php admin.php cancel 1 booking-original и php worker.php. Для просроченного предложения выполните php admin.php tick и снова worker.php.

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-waiting-list)
