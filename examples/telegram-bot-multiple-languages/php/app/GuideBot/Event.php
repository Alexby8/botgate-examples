<?php

declare(strict_types=1);

namespace App\GuideBot;

final readonly class Event
{
    public function __construct(
        public int $updateId,
        public int $chatId,
        public string $action,
        public string $language,
        public ?string $callbackId,
    ) {
    }

    /** @param array<string, mixed> $update */
    public static function from(array $update): ?self
    {
        $callback = $update['callback_query'] ?? null;
        $message = is_array($callback) ? ($callback['message'] ?? null) : ($update['message'] ?? null);
        $from = is_array($callback) ? ($callback['from'] ?? null) : (is_array($message) ? ($message['from'] ?? null) : null);
        $chat = is_array($message) ? ($message['chat'] ?? null) : null;
        if (!is_array($from) || !is_array($chat) || ($chat['type'] ?? '') !== 'private'
            || !is_int($from['id'] ?? null) || $from['id'] <= 0 || ($chat['id'] ?? null) !== $from['id']
            || !is_int($update['update_id'] ?? null)) {
            return null;
        }
        $action = is_array($callback) ? ($callback['data'] ?? '') : ($message['text'] ?? '');
        $callbackId = is_array($callback) ? ($callback['id'] ?? null) : null;
        return new self(
            $update['update_id'],
            $from['id'],
            is_string($action) ? trim($action) : '',
            is_string($from['language_code'] ?? null) ? $from['language_code'] : '',
            is_string($callbackId) ? $callbackId : null
        );
    }
}
