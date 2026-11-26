<?php

declare(strict_types=1);
require_once __DIR__.'/answer.php';

function handoff(PDO $db, int $chatId, string $reason): int
{
    $db->beginTransaction();
    try {
        $db->prepare("UPDATE conversations SET state='operator',version=version+1 WHERE chat_id=?")->execute([$chatId]);
        $db->prepare("INSERT OR IGNORE INTO tickets(chat_id,question,reason,created_at) SELECT chat_id,last_question,?,? FROM conversations WHERE chat_id=?")
            ->execute([$reason, time(), $chatId]);
        $query = $db->prepare("SELECT id FROM tickets WHERE chat_id=? AND state='open'");
        $query->execute([$chatId]);
        $id = (int) $query->fetchColumn();
        $db->commit();
        return $id;
    } catch (Throwable $error) {
        $db->rollBack();
        throw $error;
    }
}

/**
 * @param array<string,mixed> $update
 */
function handleUpdate(PDO $db, array $update): void
{
    $message = $update['message'] ?? null;
    // Учебный бот отвечает только в личном чате и принимает текст.
    if (!is_array($message) || !is_array($message['chat'] ?? null) || ($message['chat']['type'] ?? null) !== 'private'
        || !is_int($message['chat']['id'] ?? null) || !is_string($message['text'] ?? null)) {
        return;
    }
    $chatId = $message['chat']['id'];
    $question = trim($message['text']);
    $db->prepare('INSERT OR IGNORE INTO conversations(chat_id) VALUES(?)')->execute([$chatId]);
    if ($question === '/start') {
        telegram('sendMessage', ['chat_id' => $chatId,'text' => 'Задайте вопрос о настройке сервиса. Если в справке нет подтверждённого ответа, передам вопрос сотруднику. /operator — вызвать сотрудника самостоятельно.']);
        return;
    }
    $query = $db->prepare('SELECT * FROM conversations WHERE chat_id=?');
    $query->execute([$chatId]);
    $conversation = objectValue($query->fetch(PDO::FETCH_ASSOC));
    if ($question === '/operator') {
        $id = handoff($db, $chatId, 'requested');
        telegram('sendMessage', ['chat_id' => $chatId,'text' => 'Обращение №'.$id.' передано специалисту.']);
        return;
    }
    if ($conversation['state'] === 'operator') {
        $db->prepare("UPDATE tickets SET question=substr(question || char(10) || ?,1,12000) WHERE chat_id=? AND state='open'")
            ->execute([mb_substr($question, 0, 2000),$chatId]);
        return;
    }
    if ($question === '' || mb_strlen($question) > 2000) {
        telegram('sendMessage', ['chat_id' => $chatId,'text' => 'Напишите вопрос текстом до 2000 символов.']);
        return;
    }
    $db->prepare('UPDATE conversations SET last_question=?,version=version+1 WHERE chat_id=?')->execute([$question,$chatId]);
    $version = intValue($conversation['version']) + 1;
    $reason = 'no_sources';
    try {
        $answer = answerQuestion($question);
    } catch (Throwable) {
        $answer = null;
        $reason = 'ai_unavailable';
    }
    $query->execute([$chatId]);
    $latest = objectValue($query->fetch(PDO::FETCH_ASSOC));
    if ($latest['state'] !== 'bot' || intValue($latest['version']) !== $version) {
        return;
    }
    if ($answer === null) {
        $id = handoff($db, $chatId, $reason);
        $answer = 'Не удалось подготовить подтверждённый ответ. Передал вопрос специалисту: №'.$id.'.';
    }
    telegram('sendMessage', ['chat_id' => $chatId,'text' => $answer]);
}
