# Задачи сотрудников на PHP

Назначение, личный список, статусы и журнал.

## Запуск

Нужны PHP 8.3+ и расширения cURL, mbstring, PDO SQLite. Для проверки в Telegram настройте HTTPS с корнем в `public/`.

Задайте `APP_DB`, `BOT_TOKEN`, `WEBHOOK_URL`, `WEBHOOK_SECRET`. Храните SQLite-файл вне публичного каталога. Укажите WEBHOOK_URL для /webhook.php.

Выполните команды из каталога примера.

```sh
php init.php
php admin.php employee 1001 manager "Ольга"
php admin.php employee 1002 employee "Илья"
php setup-webhook.php
```

Подставьте два Telegram ID и отправьте /start с обоих аккаунтов. Назначьте задачу командой /new USER|YYYY-MM-DD HH:MM|Название с будущим сроком по Москве. Запускайте worker.php после реплик, для просрочки — php admin.php tick.

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-employee-tasks)
