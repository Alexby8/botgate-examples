<?php
declare(strict_types=1);

/**
 * @param array<string, int|string> $state
 * @return array{state: array<string, int|string>, text: string, application: array<string, int|string>|null}
 */
function formStep(array $state, ?string $text, int $now): array
{
    $text = $text === null ? null : trim($text);
    $reply = static fn(array $s, string $message, ?array $application = null)
        => ['state' => $s, 'text' => $message, 'application' => $application];

    if ($text === '/form') {
        return $reply(['step' => 'name', 'expires_at' => $now + 1800],
            'Как к вам обращаться? /cancel — отмена.');
    }
    if ($text === '/cancel') {
        return $reply(['step' => 'idle'], 'Анкета отменена. Начать заново: /form.');
    }

    $step = $state['step'] ?? 'idle';
    if (in_array($step, ['idle', 'done'], true)) {
        return $reply($state, 'Чтобы заполнить новую анкету, отправьте /form.');
    }
    if (($state['expires_at'] ?? 0) <= $now) {
        return $reply(['step' => 'idle'], 'Время заполнения истекло. Начните с /form.');
    }
    if ($text === '/back') {
        if ($step === 'confirm') {
            unset($state['task']);
            $state['step'] = 'task';
            return $reply($state, 'Опишите задачу заново: от 10 до 1000 символов.');
        }
        unset($state['name'], $state['task']);
        $state['step'] = 'name';
        return $reply($state, 'Как к вам обращаться?');
    }
    if ($text === null || $text === '') {
        return $reply($state, 'На этом шаге нужен текст. /back — назад, /cancel — отмена.');
    }
    if (str_starts_with($text, '/') && !($step === 'confirm' && $text === '/confirm')) {
        return $reply($state, 'Используйте /back, /cancel или ответьте на текущий вопрос.');
    }

    if ($step === 'name') {
        if (mb_strlen($text, 'UTF-8') > 80) {
            return $reply($state, 'Сократите имя до 80 символов.');
        }
        $state['name'] = $text;
        $state['step'] = 'task';
        return $reply($state, 'Опишите задачу: от 10 до 1000 символов. /back — назад.');
    }
    if ($step === 'task') {
        $length = mb_strlen($text, 'UTF-8');
        if ($length < 10 || $length > 1000) {
            return $reply($state, 'Нужно от 10 до 1000 символов. Уточните описание.');
        }
        $state['task'] = $text;
        $state['step'] = 'confirm';
        return $reply($state, "Имя: {$state['name']}\nЗадача: {$text}\n\n"
            . '/confirm — отправить, /back — изменить, /cancel — отменить.');
    }
    if ($step === 'confirm' && $text === '/confirm') {
        return $reply(['step' => 'done'], 'Заявка сохранена. Спасибо!',
            ['name' => $state['name'], 'task' => $state['task']]);
    }
    return $reply($state, 'Проверьте данные и отправьте /confirm или /back.');
}
