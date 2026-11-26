<?php

declare(strict_types=1);

final class UploadConflict extends RuntimeException
{
}

/**
 * @param array<string,mixed> $file
 *
 * @return array{tmp:string,mime:string,extension:string,bytes:int,sha256:string}
 */
function inspectUpload(array $file): array
{
    if (in_array($file['error'] ?? null, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new InvalidArgumentException('Файл превышает допустимый размер');
    }
    if (($file['error'] ?? null) !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Файл не загружен целиком');
    }
    $tmp = stringValue($file['tmp_name'] ?? null);
    if (!is_uploaded_file($tmp)) {
        throw new InvalidArgumentException('Ожидается HTTP-загрузка');
    }
    $bytes = filesize($tmp);
    if ($bytes === false || $bytes < 1 || $bytes > 5 * 1024 * 1024) {
        throw new InvalidArgumentException('Файл должен быть от 1 байта до 5 МиБ');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $allowed = ['image/jpeg' => 'jpg','image/png' => 'png','application/pdf' => 'pdf'];
    if (!is_string($mime) || !isset($allowed[$mime])) {
        throw new InvalidArgumentException('Разрешены JPEG, PNG и PDF');
    }
    if (str_starts_with($mime, 'image/')) {
        $size = getimagesize($tmp);
        if ($size === false || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > 20000000) {
            throw new InvalidArgumentException('Изображение повреждено или больше 20 мегапикселей');
        }
    }
    return ['tmp' => $tmp,'mime' => $mime,'extension' => $allowed[$mime],'bytes' => $bytes,'sha256' => stringValue(hash_file('sha256', $tmp))];
}

/**
 * @return array{id:int,attachments:list<array{id:string,mime:string,bytes:int}>}|null
 */
function requestStatus(PDO $db, int $userId, string $operation): ?array
{
    $q = $db->prepare('SELECT id FROM requests WHERE user_id=? AND operation_id=?');
    $q->execute([$userId,$operation]);
    $id = $q->fetchColumn();
    if ($id === false) {
        return null;
    }
    $q = $db->prepare('SELECT id,mime,bytes FROM attachments WHERE request_id=? ORDER BY id');
    $q->execute([$id]);
    $files = [];
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
        $r = objectValue($row);
        $files[] = ['id' => stringValue($r['id']),'mime' => stringValue($r['mime']),'bytes' => intValue($r['bytes'])];
    }
    return ['id' => intValue($id),'attachments' => $files];
}

/**
 * @param list<array{tmp:string,mime:string,extension:string,bytes:int,sha256:string}> $files
 *
 * @return array{id:int,attachments:list<array{id:string,mime:string,bytes:int}>}
 */
function saveRequest(PDO $db, int $userId, string $operation, string $description, array $files): array
{
    if (!preg_match('/^[a-f0-9-]{36}$/D', $operation) || mb_strlen(trim($description)) < 10 || mb_strlen($description) > 2000
        || count($files) < 1 || count($files) > 3) {
        throw new InvalidArgumentException('Проверьте описание и число файлов');
    }
    $manifest = array_map(static fn (array $f): array => [$f['mime'],$f['bytes'],$f['sha256']], $files);
    $hash = hash('sha256', json_encode([$description,$manifest], JSON_THROW_ON_ERROR));
    $directory = realpath(envRequired('UPLOAD_DIR'));
    if ($directory === false || !is_writable($directory)) {
        throw new RuntimeException('Private storage unavailable');
    }
    $paths = [];
    $db->exec('BEGIN IMMEDIATE');
    try {
        $q = $db->prepare('SELECT request_hash FROM requests WHERE user_id=? AND operation_id=?');
        $q->execute([$userId,$operation]);
        $previous = $q->fetchColumn();
        if ($previous !== false) {
            if (!hash_equals(stringValue($previous), $hash)) {
                throw new UploadConflict('Ключ использован с другим содержимым');
            }
            $result = requestStatus($db, $userId, $operation);
            if ($result === null) {
                throw new RuntimeException('Request missing');
            }
            $db->exec('COMMIT');
            return $result;
        }
        $db->prepare('INSERT INTO requests(user_id,operation_id,request_hash,description,created_at) VALUES(?,?,?,?,?)')
            ->execute([$userId,$operation,$hash,$description,time()]);
        $requestId = intValue($db->lastInsertId());
        foreach ($files as $file) {
            $id = bin2hex(random_bytes(16));
            $name = $id.'.'.$file['extension'];
            $path = $directory.DIRECTORY_SEPARATOR.$name;
            if (!move_uploaded_file($file['tmp'], $path)) {
                throw new RuntimeException('Cannot store upload');
            }
            $paths[] = $path;
            if (!chmod($path, 0600)) {
                throw new RuntimeException('Cannot restrict file permissions');
            }
            $db->prepare('INSERT INTO attachments(id,request_id,storage_name,mime,bytes,sha256) VALUES(?,?,?,?,?,?)')
                ->execute([$id,$requestId,$name,$file['mime'],$file['bytes'],$file['sha256']]);
        }
        $result = requestStatus($db, $userId, $operation);
        if ($result === null) {
            throw new RuntimeException('Request missing');
        }
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        foreach ($paths as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        throw $e;
    }
}
