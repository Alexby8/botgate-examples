<?php
declare(strict_types=1);

function utf16Length(string $text): int
{
    return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
}

/** Принимает обычный UTF-8 текст, не HTML и не Markdown.
 *  @return list<string>
 */
function splitTelegramText(string $text, int $limit = 3000): array
{
    if ($limit < 1 || $limit > 3000 || !mb_check_encoding($text, 'UTF-8')) {
        throw new InvalidArgumentException('Проверьте UTF-8 и limit от 1 до 3000');
    }
    if (!preg_match('/\S/u', $text)) {
        throw new InvalidArgumentException('Пустой текст');
    }
    $paragraphs = preg_split('/(\R\R+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    if ($paragraphs === false) {
        throw new RuntimeException('Не удалось разобрать абзацы');
    }
    $parts = [];
    $current = '';
    $used = 0;
    foreach ($paragraphs as $paragraph) {
        $size = utf16Length($paragraph);
        // Абзац, который целиком помещается в сообщение, не разрываем.
        if ($size <= $limit) {
            if ($used + $size > $limit) {
                $parts[] = $current;
                $current = '';
                $used = 0;
            }
            $current .= $paragraph;
            $used += $size;
            continue;
        }
        if (preg_match_all('/\X/u', $paragraph, $matches) === false) {
            throw new RuntimeException('Не удалось разобрать Unicode');
        }
        foreach ($matches[0] as $cluster) {
            $units = utf16Length($cluster);
            if ($units > $limit) {
                throw new LengthException('Один составной символ превышает лимит');
            }
            if ($used + $units > $limit) {
                $parts[] = $current;
                $current = '';
                $used = 0;
            }
            $current .= $cluster;
            $used += $units;
        }
    }
    if ($current !== '') {
        $parts[] = $current;
    }
    foreach ($parts as $part) {
        if (!preg_match('/\S/u', $part)) {
            throw new InvalidArgumentException('Получилась часть только из пробелов: очистите исходный текст');
        }
    }
    return $parts;
}
