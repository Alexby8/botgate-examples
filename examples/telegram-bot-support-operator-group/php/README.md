# Поддержка из группы операторов на PHP

Оператор отвечает клиенту через Reply и закрывает обращение командой /close.

## Запуск

Нужны PHP 8.3+, PDO SQLite, cURL и HTTPS с корнем `public/`.

Задайте переменные `BOT_DB_PATH`, `BOT_TOKEN`, `WEBHOOK_SECRET`, `WEBHOOK_URL`. Путь к SQLite должен быть абсолютным и лежать за пределами public/. Укажите HTTPS-адрес /webhook.php в WEBHOOK_URL. После действий запускайте worker.php.

Укажите отрицательный ID закрытой группы в SUPPORT_GROUP_ID и ID операторов через запятую в OPERATOR_IDS.

Из каталога примера выполните.

```sh
php init.php
php setup-webhook.php
php worker.php
```

[Руководство BotGate](https://bot-gate.ru/guides/telegram-bot-support-operator-group)
