# Бонусная карта в Mini App на PHP

Баланс, сроки действия и журнал бонусов.

## Запуск

Нужны PHP 8.3+ и расширения PDO SQLite. Для проверки в Telegram настройте HTTPS с корнем в `public/`.

Задайте `APP_DB`, `BOT_TOKEN`. Храните SQLite-файл вне публичного каталога.

Выполните команды из каталога примера.

```sh
php init.php
php demo.php
```

Укажите /app.html в меню бота. pos.php имитирует доверенные операции кассы. demo.php создаёт базу в памяти и показывает остатки 50, 68, 48 и 0 по дням 0, 30, 90 и 91.

[Руководство BotGate](https://bot-gate.ru/guides/telegram-mini-app-loyalty-card)
