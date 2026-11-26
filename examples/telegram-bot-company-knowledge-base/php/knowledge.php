<?php

declare(strict_types=1);

/**
 * @return list<array{id:string,title:string,url:string,body:string,keywords:string}>
 */
function documents(): array
{
    return require __DIR__.'/fixtures/faq.php';
}

/**
 * @return list<string>
 */
function words(string $text): array
{
    if (preg_match_all('/[\p{L}\p{N}]+/u', str_replace('ё', 'е', mb_strtolower($text, 'UTF-8')), $matches) === false) {
        return [];
    }
    return array_values(array_filter(array_unique($matches[0]), static fn (string $word): bool =>
        mb_strlen($word) > 2 && !in_array($word, ['как','где','мне','для','это','можно','хочу','что'], true)));
}

/**
 * @return list<array{id:string,title:string,url:string,body:string,keywords:string,score:float}>
 */
function retrieve(string $question): array
{
    $query = words($question);
    if ($query === []) {
        return [];
    }
    $hits = [];
    foreach (documents() as $doc) {
        $terms = words($doc['title'].' '.$doc['keywords'].' '.$doc['body']);
        $score = count(array_intersect($query, $terms)) / count($query);
        if ($score > 0) {
            $hits[] = $doc + ['score' => $score];
        }
    }
    usort($hits, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);
    return array_slice($hits, 0, 3);
}
