<?php

declare(strict_types=1);

namespace App\GuideBot;

use Illuminate\Support\Facades\DB;

final class Dialogue
{
    // Called inside the webhook transaction, without HTTP requests.
    public function handle(Event $event): void
    {
        DB::table('demo_users')->insertOrIgnore([
            'chat_id' => $event->chatId, 'locale' => BotText::initial($event->language), 'locale_source' => 'telegram',
        ]);
        $user = DB::table('demo_users')->where('chat_id', $event->chatId)->lockForUpdate()->first();
        if (!$user) {
            throw new \RuntimeException('Missing user');
        }
        $locale = (string) $user->locale;
        $action = $event->action;
        $key = 'unknown';
        $replace = [];
        $buttons = BotText::menu($locale);
        if (in_array($action, ['/language', 'language'], true)) {
            $key = 'choose_language';
            $buttons = ['inline_keyboard' => [[
                ['text' => 'Русский', 'callback_data' => 'lang:ru'],
                ['text' => 'English', 'callback_data' => 'lang:en'],
            ]]];
        } elseif ($event->callbackId !== null && in_array($action, ['lang:ru', 'lang:en'], true)) {
            $locale = substr($action, 5);
            DB::table('demo_users')->where('chat_id', $event->chatId)->update(['locale' => $locale, 'locale_source' => 'manual']);
            $key = 'language_saved';
            $buttons = BotText::menu($locale);
        } elseif ($action === '/start') {
            DB::table('demo_users')->where('chat_id', $event->chatId)->update(['awaiting_name' => true]);
            $key = 'welcome';
        } elseif (in_array($action, ['/help', 'help'], true)) {
            $key = 'help';
        } elseif ($event->callbackId !== null) {
            $key = 'stale';
        } elseif ((bool) $user->awaiting_name && !str_starts_with($action, '/')) {
            $key = 'bad_name';
            if (mb_strlen($action) >= 2 && mb_strlen($action) <= 60) {
                DB::table('demo_users')->where('chat_id', $event->chatId)->update(['name' => $action, 'awaiting_name' => false]);
                $key = 'saved';
                $replace = ['name' => $action];
            }
        }
        $text = BotText::get($key, $locale, $replace);
        if ($key === 'language_saved' && (bool) $user->awaiting_name) {
            $text .= "\n".BotText::get('ask_name', $locale);
        }
        Outbox::add($event->chatId, 'update:'.$event->updateId, ['text' => $text, 'reply_markup' => $buttons]);
    }

    public function notify(int $chatId, string $reference): void
    {
        DB::transaction(function () use ($chatId, $reference): void {
            $user = DB::table('demo_users')->where('chat_id', $chatId)->lockForUpdate()->first();
            if (!$user) {
                throw new \RuntimeException('User must start the bot first');
            }
            Outbox::add($chatId, 'notice:'.$chatId.':'.$reference, [
                'text' => BotText::get('notice', (string) $user->locale, ['reference' => $reference]),
                'reply_markup' => BotText::menu((string) $user->locale),
            ]);
        });
    }

    public function canSend(\stdClass $row): bool
    {
        return true;
    }
}
