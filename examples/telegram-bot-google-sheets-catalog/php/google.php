<?php

declare(strict_types=1);
require_once __DIR__.'/common.php';
require_once __DIR__.'/vendor/autoload.php';
/** @param list<string> $headers
 * @return array<string,mixed>
 */
function googleRequest(string $url, array $headers, ?string $body = null): array
{
    $curl = curl_init($url);
    if ($curl === false) {
        throw new RuntimeException('HTTP_INIT');
    }
    $raw = '';
    curl_setopt_array($curl, [CURLOPT_HTTPHEADER => $headers, CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_WRITEFUNCTION => function (CurlHandle $handle, string $chunk) use (&$raw): int {
            if (strlen($raw) + strlen($chunk) > 2097152) {
                return 0;
            }
            $raw .= $chunk;
            return strlen($chunk);
        }]);
    if ($body !== null) {
        curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body]);
    }
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    if ($ok === false || $status !== 200) {
        throw new RuntimeException('GOOGLE_HTTP_'.$status);
    }
    return objectValue(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
}
function googleToken(?GuzzleHttp\ClientInterface $client = null): string
{
    $raw = file_get_contents(envRequired('GOOGLE_CREDENTIALS'));
    if ($raw === false) {
        throw new RuntimeException('CREDENTIALS_READ');
    }
    $key = objectValue(json_decode($raw, true, 512, JSON_THROW_ON_ERROR));
    if (($key['type'] ?? '') !== 'service_account') {
        throw new RuntimeException('Expected your service account key');
    }
    $credentials = new Google\Auth\Credentials\ServiceAccountCredentials(
        'https://www.googleapis.com/auth/spreadsheets.readonly',
        $key,
    );
    $handler = Google\Auth\HttpHandler\HttpHandlerFactory::build($client ?? new GuzzleHttp\Client([
        'connect_timeout' => 5, 'timeout' => 20, 'allow_redirects' => false,
    ]), false);
    $result = $credentials->fetchAuthToken($handler);
    return stringValue($result['access_token'] ?? null);
}
/** @return array<string,mixed> */
function readSheet(): array
{
    $id = envRequired('SHEET_ID');
    if (!preg_match('/^[A-Za-z0-9_-]+$/D', $id)) {
        throw new RuntimeException('SHEET_ID');
    }
    // Лишняя строка — сторож: переполнение не должно обрезать каталог молча.
    return googleRequest('https://sheets.googleapis.com/v4/spreadsheets/'.$id.'/values/'.rawurlencode("'Catalog'!A1:E1002").
        '?majorDimension=ROWS&valueRenderOption=UNFORMATTED_VALUE', ['Authorization: Bearer '.googleToken()]);
}
