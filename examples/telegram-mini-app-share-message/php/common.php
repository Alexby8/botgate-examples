<?php
declare(strict_types=1);

function setting(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        throw new RuntimeException('Missing setting: '.$name);
    }
    return $value;
}

/** @return array<string, mixed> */
function jsonBody(): array
{
    $raw = file_get_contents('php://input', length: 65537);
    if ($raw === false || strlen($raw) > 65536) {
        throw new InvalidArgumentException('Request too large');
    }
    $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    if (!is_array($data) || array_is_list($data)) {
        throw new InvalidArgumentException('Expected JSON object');
    }
    $result = [];
    foreach ($data as $key => $value) {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Invalid JSON key');
        }
        $result[$key] = $value;
    }
    return $result;
}

/** @param array<string, mixed> $data */
function reply(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    exit;
}
