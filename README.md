# Примеры к руководствам BotGate

Практические примеры из [руководств BotGate](https://bot-gate.ru/guides).
В статье объясняется решение, здесь можно просмотреть отдельные файлы и запустить пример.

| Пример | Статья | Языки |
| --- | --- | --- |
| [Калькулятор стоимости услуг в Mini App](examples/telegram-mini-app-service-calculator/) | [Руководство](https://bot-gate.ru/guides/telegram-mini-app-service-calculator) | PHP + HTML/JavaScript |
| [Каталог бота из Google Таблиц](examples/telegram-bot-google-sheets-catalog/) | [Руководство](https://bot-gate.ru/guides/telegram-bot-google-sheets-catalog) | PHP |
| [Пошаговая анкета на консультацию](examples/telegram-bot-step-by-step-form/) | [Руководство](https://bot-gate.ru/guides/telegram-bot-step-by-step-form) | PHP |
| [Первая форма заявки в Mini App](examples/telegram-mini-app-html-php-form/) | [Руководство](https://bot-gate.ru/guides/telegram-mini-app-html-php-form) | PHP + HTML/JavaScript |
| [Фото и документы в Mini App](examples/telegram-mini-app-file-upload/) | [Руководство](https://bot-gate.ru/guides/telegram-mini-app-file-upload) | PHP + HTML/JavaScript |
| [Анонсы из RSS в Telegram](examples/telegram-rss-channel-autopost/) | [Руководство](https://bot-gate.ru/guides/telegram-rss-channel-autopost) | PHP |
| [Черновик заявки в Mini App](examples/telegram-mini-app-form-draft/) | [Руководство](https://bot-gate.ru/guides/telegram-mini-app-form-draft) | PHP + HTML/JavaScript |
| [Настройки уведомлений в Mini App](examples/telegram-mini-app-notification-settings/) | [Руководство](https://bot-gate.ru/guides/telegram-mini-app-notification-settings) | PHP + HTML/JavaScript |
| [Telegram-бот на русском и английском](examples/telegram-bot-multiple-languages/) | [Руководство](https://bot-gate.ru/guides/telegram-bot-multiple-languages) | PHP / Laravel |
| [Ежедневный отчёт в Telegram](examples/telegram-daily-sales-report/) | [Руководство](https://bot-gate.ru/guides/telegram-daily-sales-report) | PHP |

## Организация

`examples/<slug-статьи>/<язык>/` — самостоятельная реализация сценария.
Общий README статьи описывает результат и перечисляет варианты; README языка
содержит требования и запуск. Сейчас есть `php`, новые языки можно добавлять рядом.
HTML и JavaScript интерфейса Mini App находятся внутри PHP-примера, которому принадлежат.

## С чего начать

Откройте папку нужного примера и его README. Для получения кода:

```bash
git clone https://github.com/Alexby8/botgate-examples.git
```

Перейдите в языковую папку и выполняйте её инструкции. Установка зависимостей
и конфигурация у каждого примера свои: общей установки из корня репозитория нет.
Если статья ссылается на конкретный коммит, используйте эту версию для повторения.

В репозитории хранятся только шаблоны настроек и учебные данные. Локальные токены,
ключи Google, SQLite-базы и `vendor` не коммитятся. Демонстрации `demo.php`
работают без обращений к Google и Telegram; отдельные команды подключения и
отправки выполняют реальные действия, описанные в README.
