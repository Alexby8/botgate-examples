# Корзина в Mini App на PHP

Изменение количества, подтверждение цены и восстановление номера заказа.

## Запуск

Нужны PHP 8.3+, PDO SQLite и HTTPS с корнем `public/`.

Задайте переменные `BOT_DB_PATH`, `BOT_TOKEN`. Путь к SQLite должен быть абсолютным и лежать за пределами public/.

Укажите /index.html в кнопке меню бота. Команда php price.php 48000 меняет цену чая на 480 ₽. Получите новый расчёт и подтвердите его.

Из каталога примера выполните.

```sh
php init.php
```

[Руководство BotGate](https://bot-gate.ru/guides/telegram-mini-app-cart-checkout)
