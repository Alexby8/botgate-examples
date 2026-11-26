<?php

declare(strict_types=1);

/** @param array<string, int|string|null> $r */
function requestText(array $r): string
{
    $text = 'Заявка №'.$r['id'].' · версия '.$r['version']."\n".$r['title']."\n"
        .number_format((int) $r['amount_minor'] / 100, 2, ',', ' ')." ₽\n";
    return $text.match ($r['status']) {
        'approved' => 'Одобрена',
        'rejected' => 'Отклонена. Причина: '.$r['reason'],
        default => 'Ожидает решения',
    };
}

function sendCard(PDO $db, int $id): void
{
    $r = row(sql($db, "SELECT * FROM requests WHERE id=? AND status='pending'", [$id]));
    if ($r === null) {
        return;
    }
    $version = (int) $r['version'];
    menu($db, (int) $r['approver'], requestText($r), [[
        ['text'=>'Одобрить','callback_data'=>"allow:$id:$version"],
        ['text'=>'Отклонить','callback_data'=>"deny:$id:$version"],
    ]], "card:$id");
}

/** @return array<string, int|string|null>|null */
function permitted(PDO $db, int $id, int $version, int $actor): ?array
{
    return row(sql($db, "SELECT r.* FROM requests r JOIN approvers a ON a.user_id=r.approver
        WHERE r.id=? AND r.version=? AND r.status='pending' AND r.approver=?
        AND a.active=1 AND a.limit_minor>=r.amount_minor AND r.author<>r.approver", [$id,$version,$actor]));
}

/** @param array<string, mixed> $update */
function handle(PDO $db, array $update, int $now): void
{
    $a = privateAction($update);
    if ($a === null) {
        return;
    }
    $actor = $a['user'];
    if (!$a['callback']) {
        if ($a['text'] === '/start') {
            say($db, $actor, 'Откройте карточку заявки или используйте /request НОМЕР.');
            return;
        }
        if (preg_match('~^/request ([0-9]+)$~D', $a['text'], $m)) {
            $r = row(sql($db, 'SELECT * FROM requests WHERE id=? AND (author=? OR approver=?)', [(int) $m[1],$actor,$actor]));
            say($db, $actor, $r === null ? 'Заявка недоступна.' : requestText($r));
            return;
        }
        if ($a['reply'] !== null) {
            acceptReason($db, $actor, $a['reply'], $a['text'], $now);
        }
        return;
    }
    if (!preg_match('/^(allow|deny):([0-9]+):([0-9]+)$/D', $a['text'], $m)) {
        return;
    }
    $id = (int) $m[2];
    $version = (int) $m[3];
    $r = permitted($db, $id, $version, $actor);
    if ($r === null || (int) $r['card_message_id'] !== $a['message']) {
        say($db, $actor, 'Карточка устарела или заявка недоступна. Статус: /request '.$id);
        return;
    }
    if ($m[1] === 'allow') {
        decide($db, $r, $actor, 'approved', null, $now);
        return;
    }

    $nonce = bin2hex(random_bytes(12));
    sql($db, 'INSERT INTO drafts(request_id, version, approver, expires_at, nonce) VALUES(?,?,?,?,?)
        ON CONFLICT(request_id) DO UPDATE SET version=excluded.version, approver=excluded.approver,
        expires_at=excluded.expires_at, nonce=excluded.nonce, prompt_id=NULL', [$id,$version,$actor,$now+900,$nonce]);
    enqueue($db, 'sendMessage', ['chat_id'=>$actor,
        'text'=>"Причина отказа · №$id. Ответьте на это сообщение текстом до 240 символов.",
        'reply_markup'=>['force_reply'=>true,'input_field_placeholder'=>'Краткая причина отказа']],
        "draft:$id:$nonce");
}

function acceptReason(PDO $db, int $actor, int $reply, string $text, int $now): void
{
    $d = row(sql($db, 'SELECT * FROM drafts WHERE approver=? AND prompt_id=?', [$actor,$reply]));
    if ($d === null) {
        return;
    }
    $id = (int) $d['request_id'];
    $r = permitted($db, $id, (int) $d['version'], $actor);
    if ($r === null || (int) $d['expires_at'] <= $now) {
        say($db, $actor, 'Запрос причины устарел. Проверьте /request '.$id);
        return;
    }
    if ($text === '' || mb_strlen($text) > 240) {
        say($db, $actor, 'Причина должна содержать от 1 до 240 символов. Ответьте на исходный запрос.');
        return;
    }
    decide($db, $r, $actor, 'rejected', $text, $now);
}

/** @param array<string, int|string|null> $r */
function decide(PDO $db, array $r, int $actor, string $decision, ?string $reason, int $now): void
{
    $id = (int) $r['id'];
    $version = (int) $r['version'];
    $changed = sql($db, "UPDATE requests SET status=?,reason=? WHERE id=? AND version=? AND status='pending'",
        [$decision,$reason,$id,$version])->rowCount();
    if ($changed !== 1) {
        return;
    }
    sql($db, 'INSERT INTO decisions(request_id,version,actor,status,reason,decided_at) VALUES(?,?,?,?,?,?)',
        [$id,$version,$actor,$decision,$reason,$now]);
    sql($db, 'DELETE FROM drafts WHERE request_id=?', [$id]);

    $r['status'] = $decision;
    $r['reason'] = $reason;
    enqueue($db, 'editMessageText', [
        'chat_id'=>$actor, 'message_id'=>(int) $r['card_message_id'],
        'text'=>requestText($r),'reply_markup'=>['inline_keyboard'=>[]],
    ]);
    say($db, (int) $r['author'], requestText($r));
}

function rememberDelivery(PDO $db, string $binding, int $message): void
{
    [$kind,$id,$nonce] = array_pad(explode(':', $binding, 3), 3, '');
    if ($kind === 'card') {
        sql($db, 'UPDATE requests SET card_message_id=? WHERE id=?', [$message,(int) $id]);
        return;
    }
    sql($db, 'UPDATE drafts SET prompt_id=? WHERE request_id=? AND nonce=?', [$message,(int) $id,$nonce]);
}
