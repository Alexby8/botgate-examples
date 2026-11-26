# Зелёный чай снова в наличии

Нужны 64-битный PHP 8.3+ с PDO SQLite и cURL, отдельный тестовый бот и HTTPS. Корень сайта — `public/`. Для CLI и веб-процесса настройте `BOT_TOKEN`, `BOT_DB_PATH` с новым SQLite-файлом в закрытом каталоге и общий `WEBHOOK_SECRET`. Для регистрации задайте `WEBHOOK_URL` с полным путём `/webhook.php`.

Запустите `php init.php` и `php setup-webhook.php`. Откройте личный чат тестового бота. Подставьте свой ID в `php card.php 123456789`, затем нажмите «Сообщить о поступлении».

```bash
php update-stock.php 3 1
php update-stock.php 3 1
php update-stock.php 8 2
php worker.php
```

Ожидайте одно уведомление и одну строку sent. Складская команда принимает итоговый доступный остаток и версию события. Проверяйте `SELECT watch_id,generation,episode,state,message_id FROM stock_deliveries ORDER BY id;`.

`stock.php` хранит логику подписки, обновления остатков и отправки. `public/webhook.php` принимает callback. `worker.php` вызывает отправку и подтверждает её по message_id. После отмены новое поколение подписки защищено от старой кнопки отмены. Если товар снова закончится до отправки, ожидающая задача отменится при проверке worker. Сверьте чат перед любым ручным повтором unknown.
