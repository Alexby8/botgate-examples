# Согласование заявок на PHP

Одобрение или отказ с причиной для конкретной заявки.

## Запуск

Нужны PHP 8.3+, PDO SQLite, cURL и HTTPS с корнем `public/`.

Задайте переменные `BOT_DB_PATH`, `BOT_TOKEN`, `WEBHOOK_SECRET`, `WEBHOOK_URL`. Путь к SQLite должен быть абсолютным и лежать за пределами public/. Укажите HTTPS-адрес /webhook.php в WEBHOOK_URL. После действий запускайте worker.php.

Нужно расширение mbstring. Автор и руководитель открывают чат бота. Подставьте их Telegram ID в seed.php. В примере заявки №142 и №143.

Из каталога примера выполните.

```sh
php init.php
php setup-webhook.php
php seed.php 1001 1002
php worker.php
```

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-request-approval)
