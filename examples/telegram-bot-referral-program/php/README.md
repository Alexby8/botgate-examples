# Реферальная программа на PHP

Персональная ссылка, бонус после покупки и отмена при возврате.

## Запуск

Нужны PHP 8.3+ и расширения cURL, PDO SQLite. Для проверки в Telegram настройте HTTPS с корнем в `public/`.

Задайте `BOT_DB_PATH`, `BOT_USERNAME`, `BOT_TOKEN`, `WEBHOOK_URL`, `WEBHOOK_SECRET`. Храните SQLite-файл вне публичного каталога. Укажите WEBHOOK_URL для /webhook.php.

Выполните команды из каталога примера.

```sh
php init.php
php setup-webhook.php
```

Укажите BOT_USERNAME без @. Для проверки без Telegram создайте новую БД, задайте DEMO_MODE=1 и запустите php demo.php. Для сверки сохранённых оплат используйте php reconcile.php.

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-referral-program)
