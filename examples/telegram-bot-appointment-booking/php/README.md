# Запись на консультацию на PHP

Выбор даты и времени, резерв, подтверждение и отмена.

## Запуск

Нужны PHP 8.3+, PDO SQLite, cURL и HTTPS с корнем `public/`.

Задайте переменные `BOT_DB_PATH`, `BOT_TOKEN`, `WEBHOOK_SECRET`, `WEBHOOK_URL`. Путь к SQLite должен быть абсолютным и лежать за пределами public/. Укажите HTTPS-адрес /webhook.php в WEBHOOK_URL. После действий запускайте worker.php.

seed.php создаёт интервалы 10:30 и 11:00 на два ближайших дня по Москве. Отправьте /book. Для восстановления карточки есть /mybooking.

Из каталога примера выполните.

```sh
php init.php
php seed.php
php setup-webhook.php
php worker.php
```

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-appointment-booking)
