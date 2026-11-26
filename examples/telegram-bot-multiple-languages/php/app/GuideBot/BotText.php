<?php

declare(strict_types=1);

namespace App\GuideBot;

use Illuminate\Support\Facades\Lang;

final class BotText
{
    public static function initial(string $tag): string
    {
        $base = explode('-', strtolower(str_replace('_', '-', $tag)))[0];
        return in_array($base, ['ru', 'en'], true) ? $base : 'en';
    }

    /** @param array<string, string> $replace */
    public static function get(string $key, string $locale, array $replace = []): string
    {
        if (!in_array($locale, ['ru', 'en'], true)) {
            $locale = 'en';
        }
        $full = 'bot.'.$key;
        if (!Lang::hasForLocale($full, $locale)) {
            logger()->warning('Missing bot translation', ['key' => $full, 'locale' => $locale]);
            $locale = 'en';
        }
        $value = Lang::get($full, $replace, $locale, false);
        if (is_string($value) && $value !== $full) {
            return $value;
        }
        logger()->error('Missing fallback translation', ['key' => $full]);
        return 'Message unavailable. Please use /language or try again later.';
    }

    /** @return array<string, mixed> */
    public static function menu(string $locale): array
    {
        return ['inline_keyboard' => [[
            ['text' => self::get('language_button', $locale), 'callback_data' => 'language'],
            ['text' => self::get('help_button', $locale), 'callback_data' => 'help'],
        ]]];
    }
}
