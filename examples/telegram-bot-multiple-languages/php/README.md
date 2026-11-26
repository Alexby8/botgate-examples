# Laravel-бот на русском и английском

Нужны PHP 8.3+ с mbstring и PDO SQLite, Composer, Laravel 13 и отдельный тестовый бот. В папке лежат файлы, которые нужно добавить в новое приложение.

```bash
example_dir="$(pwd)"
composer create-project "laravel/laravel:^13.0" language-bot
cd language-bot
cp -R "$example_dir/app" "$example_dir/config" "$example_dir/database" "$example_dir/lang" "$example_dir/routes" .
```

Добавьте аргумент `api: __DIR__.'/../routes/api.php'` в существующий вызов `withRouting` в `bootstrap/app.php`. Настройте `.env`: APP_URL с HTTPS-адресом, APP_DEBUG=false, BOT_TOKEN тестового бота и отдельный случайный BOT_WEBHOOK_SECRET. Для SQLite используйте локальный файл в закрытом каталоге и рабочий `DB_CONNECTION=sqlite`. Корень сайта укажите на `public/`.

```bash
php artisan migrate
php artisan route:list --path=api/telegram
php artisan bot:webhook
php artisan bot:deliver --watch
```

Откройте личный чат и отправьте /start. Для аккаунта с русским языком Telegram ожидайте русское приглашение. Отправьте X, выберите через /language кнопку English и снова отправьте X. Ошибка и кнопки станут английскими. Anna завершит шаг, а /help останется на сохранённом языке. Для тестового уведомления вызовите `php artisan bot:notify 123456789 DEMO-001` с собственным ID.

| Файл | Назначение |
| --- | --- |
| `lang/ru/bot.php`, `lang/en/bot.php`, `app/GuideBot/BotText.php` | Словари, выбор языка и запасной перевод. |
| `app/GuideBot/Dialogue.php`, `Event.php`, `Webhook.php` в app/GuideBot | Диалог, входящие события и проверенный webhook. |
| `database/migrations/`, `app/GuideBot/Outbox.php` | Пользователь, история update и сохранённые ответы. |
| `app/Console/Commands/`, `app/GuideBot/Telegram.php` | Регистрация webhook, постановка уведомления и доставка. |

Ручной выбор хранится в `demo_users.locale`. Посмотреть исходящие результаты можно запросом `SELECT event_key,state,message_id FROM demo_outbox ORDER BY id;`. Если отсутствует русский ключ, используется английский. Уведомления, уже записанные в очередь, сохраняют сформированный текст.
