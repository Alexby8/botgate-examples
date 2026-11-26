<?php

declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/outbox.php';
function publicHttps(string $url): bool
{
    $p = parse_url($url);
    return is_array($p) && ($p['scheme'] ?? '') === 'https' && isset($p['host'])
        && !isset($p['user']) && !isset($p['pass']) && !isset($p['fragment'])
        && (!isset($p['port']) || $p['port'] === 443);
}
function fetchFeed(string $url): string
{
    if (!publicHttps($url)) {
        throw new RuntimeException('FEED_URL');
    }
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host)) {
        throw new RuntimeException('FEED_HOST');
    }
    $addresses = gethostbynamel($host);
    if ($addresses === false || $addresses === []) {
        throw new RuntimeException('FEED_DNS');
    }
    foreach ($addresses as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_GLOBAL_RANGE)) {
            throw new RuntimeException('FEED_PRIVATE_IP');
        }
    }
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('HTTP_INIT');
    }
    $xml = '';
    curl_setopt_array($curl, [CURLOPT_RESOLVE => [$host.':443:'.$addresses[0]],
        CURLOPT_PROXY => '', CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'ExampleFeedBot/1.0',
        CURLOPT_WRITEFUNCTION => function (CurlHandle $handle, string $chunk) use (&$xml): int {
            if (strlen($xml) + strlen($chunk) > 2097152) {
                return 0;
            }
            $xml .= $chunk;
            return strlen($chunk);
        }]);
    if (curl_exec($curl) === false || curl_getinfo($curl, CURLINFO_RESPONSE_CODE) !== 200) {
        throw new RuntimeException('FEED_FETCH');
    }
    return $xml;
}
function childText(DOMElement $parent, string $name): string
{
    foreach ($parent->childNodes as $node) {
        if ($node instanceof DOMElement && $node->tagName === $name) {
            return trim($node->textContent);
        }
    }
    return '';
}
/** @return list<array{key:string,title:string,link:string,published:int}> */
function parseFeed(string $xml, int $now): array
{
    if (!mb_check_encoding($xml, 'UTF-8') || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
        throw new DomainException('Требуется UTF-8 RSS без DTD');
    }
    $previous = libxml_use_internal_errors(true);
    try {
        $doc = new DOMDocument();
        if (!$doc->loadXML($xml, LIBXML_NONET) || $doc->doctype !== null
            || $doc->documentElement?->tagName !== 'rss' || $doc->documentElement->getAttribute('version') !== '2.0') {
            throw new DomainException('Некорректный RSS 2.0');
        }
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $items = (new DOMXPath($doc))->query('/rss/channel/item');
    $channels = (new DOMXPath($doc))->query('/rss/channel');
    if ($items === false || $items->length > 300 || $channels === false || $channels->length !== 1) {
        throw new DomainException('Неверный канал или слишком много записей');
    }
    $result = [];
    $keys = [];
    foreach ($items as $item) {
        if (!$item instanceof DOMElement) {
            continue;
        }
        $title = trim(html_entity_decode(strip_tags(childText($item, 'title')), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $link = childText($item, 'link');
        $guid = childText($item, 'guid');
        if ($link === '') {
            foreach ($item->childNodes as $node) {
                if ($node instanceof DOMElement && $node->tagName === 'guid' && $node->getAttribute('isPermaLink') !== 'false') {
                    $link = $guid;
                }
            }
        }
        if ($title === '' || mb_strlen($title) > 500 || strlen($link) > 1500 || !publicHttps($link)) {
            throw new DomainException('Запись без подходящего заголовка или HTTPS-ссылки');
        }
        $key = hash('sha256', ($guid !== '' ? 'guid:'.$guid : 'link:'.$link));
        if (isset($keys[$key])) {
            throw new DomainException('Повтор идентификатора внутри ленты');
        }
        $keys[$key] = true;
        $published = $now;
        $rawDate = childText($item, 'pubDate');
        if ($rawDate !== '') {
            $date = DateTimeImmutable::createFromFormat(DATE_RSS, $rawDate);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date === false || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
                throw new DomainException('pubDate не соответствует формату примера');
            }
            $published = $date->getTimestamp();
        }
        $result[] = ['key' => $key, 'title' => $title, 'link' => $link, 'published' => $published];
    }
    usort($result, fn (array $a, array $b): int => [$a['published'], $a['key']] <=> [$b['published'], $b['key']]);
    return $result;
}
function ingest(PDO $db, string $feed, string $xml, string $channel, string $keyword, int $now): void
{
    $items = parseFeed($xml, $now);
    atomic($db, function () use ($db, $feed, $items, $channel, $keyword, $now): void {
        $first = row($db, 'SELECT url FROM feeds WHERE url=?', [$feed]) === null;
        $latest = row($db, 'SELECT MAX(due) AS due FROM entries');
        $due = max($now, intValue($latest['due'] ?? 0) + 120);
        foreach ($items as $item) {
            if (row($db, 'SELECT entry_key FROM entries WHERE feed=? AND entry_key=?', [$feed, $item['key']]) !== null) {
                continue;
            }
            $matches = $keyword === '' || mb_stripos($item['title'], $keyword) !== false;
            $state = $first ? 'baseline' : ($matches ? 'queued' : 'filtered');
            $publishAt = max($due, $item['published']);
            run($db, 'INSERT INTO entries VALUES(?,?,?,?,?,?,?)', [$feed, $item['key'], $item['title'], $item['link'], $item['published'], $state, $state === 'queued' ? $publishAt : null]);
            if ($state === 'queued') {
                enqueue($db, 'rss:'.hash('sha256', $feed.'|'.$item['key']), 'sendMessage', [
                    'chat_id' => $channel, 'text' => $item['title']."\n\n".$item['link']], $publishAt);
                $due = $publishAt + 120;
            }
        }
        run($db, 'INSERT INTO feeds VALUES(?,?,NULL) ON CONFLICT(url) DO UPDATE SET checked_at=excluded.checked_at,error_code=NULL', [$feed, $now]);
    });
}
