<?php

declare(strict_types=1);

final class DraftConflict extends RuntimeException
{
}

/**
 * @param array<string,mixed> $input
 * @return array{topic:string,question:string}
 */
function draftFields(array $input): array
{
    if (!in_array($input['topic'] ?? null, ['consultation','migration'], true)
        || !is_string($input['question'] ?? null) || mb_strlen($input['question']) > 10000) {
        throw new InvalidArgumentException('Выберите тему и ограничьте вопрос 10000 символами');
    }
    return ['topic' => $input['topic'],'question' => $input['question']];
}

/**
 * @return array<string,mixed>
 */
function currentDraft(PDO $db, int $userId): array
{
    $q = $db->prepare('SELECT * FROM drafts WHERE user_id=?');
    $q->execute([$userId]);
    $row = $q->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $db->prepare("INSERT INTO drafts(user_id,draft_id,revision,state,fields,expires_at) VALUES(?,?,0,'editing',?,?)")
            ->execute([$userId,bin2hex(random_bytes(16)),json_encode(['topic' => 'consultation','question' => ''], JSON_THROW_ON_ERROR),time() + 604800]);
        $q->execute([$userId]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
    }
    $row = objectValue($row);
    if ($row['state'] === 'editing' && intValue($row['expires_at']) < time()) {
        $db->prepare("UPDATE drafts SET state='expired',fields='{}',revision=revision+1 WHERE user_id=?")->execute([$userId]);
        $q->execute([$userId]);
        $row = objectValue($q->fetch(PDO::FETCH_ASSOC));
    }
    return ['draftId' => $row['draft_id'],'revision' => intValue($row['revision']),'state' => $row['state'],
        'fields' => objectValue(json_decode(stringValue($row['fields']), true, 16, JSON_THROW_ON_ERROR)),'expiresAt' => intValue($row['expires_at']),
        'applicationId' => $row['application_id'] === null ? null : intValue($row['application_id'])];
}

/**
 * @param array<string,mixed> $request
 * @return array<string,mixed>
 */
function mutateDraft(PDO $db, int $userId, array $request): array
{
    $operation = $request['operationId'] ?? '';
    $action = $request['action'] ?? '';
    if (!is_string($operation) || !preg_match('/^[0-9a-f-]{36}$/D', $operation)
        || !in_array($action, ['save','submit','new'], true)
        || !is_string($request['draftId'] ?? null) || !is_int($request['revision'] ?? null)) {
        throw new InvalidArgumentException('Некорректная операция');
    }
    $fields = $action === 'new' ? [] : draftFields(is_array($request['fields'] ?? null) ? objectValue($request['fields']) : []);
    $hash = hash('sha256', json_encode([$action,$request['draftId'],$request['revision'],$fields], JSON_THROW_ON_ERROR));
    $db->exec('BEGIN IMMEDIATE');
    try {
        $q = $db->prepare('SELECT * FROM operations WHERE user_id=? AND operation_id=?');
        $q->execute([$userId,$operation]);
        $previous = $q->fetch(PDO::FETCH_ASSOC);
        if (is_array($previous)) {
            if (!hash_equals(stringValue($previous['request_hash']), $hash)) {
                throw new DraftConflict('Ключ уже использован с другими данными');
            }
            $result = objectValue(json_decode(stringValue($previous['response']), true, 32, JSON_THROW_ON_ERROR));
            $db->exec('COMMIT');
            return $result;
        }
        $draft = currentDraft($db, $userId);
        if ($draft['draftId'] !== $request['draftId'] || $draft['revision'] !== $request['revision']) {
            throw new DraftConflict('Форма изменена на другом устройстве');
        }
        if ($action === 'new') {
            if ($draft['state'] === 'editing') {
                throw new DraftConflict('Сначала завершите текущую заявку');
            }
            $db->prepare("UPDATE drafts SET draft_id=?,revision=0,state='editing',fields=?,expires_at=?,application_id=NULL WHERE user_id=?")
                ->execute([bin2hex(random_bytes(16)),json_encode(['topic' => 'consultation','question' => ''], JSON_THROW_ON_ERROR),time() + 604800,$userId]);
        } else {
            if ($draft['state'] !== 'editing') {
                throw new DraftConflict('Черновик уже закрыт или просрочен');
            }
            if ($action === 'submit' && mb_strlen(trim($fields['question'] ?? '')) < 10) {
                throw new InvalidArgumentException('Опишите вопрос: минимум 10 символов');
            }
            $body = json_encode($fields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            $applicationId = null;
            if ($action === 'submit') {
                $db->prepare('INSERT INTO applications(user_id,draft_id,fields,created_at) VALUES(?,?,?,?)')->execute([$userId,$draft['draftId'],$body,time()]);
                $applicationId = (int)$db->lastInsertId();
            }
            $db->prepare('UPDATE drafts SET fields=?,revision=revision+1,state=?,expires_at=?,application_id=? WHERE user_id=?')
                ->execute([$action === 'submit' ? '{}' : $body,$action === 'submit' ? 'submitted' : 'editing',time() + 604800,$applicationId,$userId]);
        }
        $result = currentDraft($db, $userId);
        $db->prepare('INSERT INTO operations(user_id,operation_id,request_hash,response) VALUES(?,?,?,?)')
            ->execute([$userId,$operation,$hash,json_encode($result, JSON_THROW_ON_ERROR)]);
        $db->exec('COMMIT');
        return $result;
    } catch (Throwable $error) {
        $db->exec('ROLLBACK');
        throw $error;
    }
}
