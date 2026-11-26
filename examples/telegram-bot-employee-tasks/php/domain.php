<?php

declare(strict_types=1);
function deadline(string $value): int
{
    $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value, new DateTimeZone('Europe/Moscow'));
    if (!$d || $d->format('Y-m-d H:i') !== $value) {
        throw new DomainException('Срок: YYYY-MM-DD HH:MM по Москве');
    }return $d->getTimestamp();
}
/**
 * @return array<string,mixed>
 */
function employee(PDO $db, int $user): array
{
    return row($db, 'SELECT * FROM employees WHERE user_id=? AND active=1', [$user]) ?? throw new DomainException('Доступ не выдан. Обратитесь к руководителю');
}
/**
 * @param array<string,mixed> $task
 * @return list<list<array{text:string,callback_data:string}>>
 */
function taskButtons(array $task, int $user, bool $manager): array
{
    $prefix = 'task:'.intValue($task['id']).':'.intValue($task['version']).':';
    $buttons = [];
    if (intValue($task['assignee']) === $user) {
        if ($task['status'] === 'new') {
            $buttons[] = ['text' => 'В работу','callback_data' => $prefix.'working'];
        }
        if ($task['status'] === 'working') {
            $buttons[] = ['text' => 'Готово','callback_data' => $prefix.'done'];
        }
    }
    if ($manager && in_array($task['status'], ['new','working'], true)) {
        $buttons[] = ['text' => 'Отменить','callback_data' => $prefix.'cancelled'];
    }
    return $buttons === [] ? [] : [$buttons];
}
/**
 * @param array<string,mixed> $t
 */
function taskText(array $t): string
{
    $labels = ['new' => 'Новая','working' => 'В работе','done' => 'Выполнена','cancelled' => 'Отменена'];
    $date = (new DateTimeImmutable('@'.intValue($t['due_at'])))->setTimezone(new DateTimeZone('Europe/Moscow'))->format('d.m.Y H:i');
    return '#'.intValue($t['id']).' '.stringValue($t['title'])."\n".($labels[stringValue($t['status'])] ?? 'Неизвестный статус')."\nСрок: ".$date.' МСК · версия '.intValue($t['version']);
}
function audit(PDO $db, int $task, int $actor, string $action, int $now): void
{
    run($db, 'INSERT INTO history(task_id,actor,action,created_at) VALUES(?,?,?,?)', [$task,$actor,$action,$now]);
}
function handle(PDO $db, int $user, string $action, bool $callback, int $update, int $now): void
{
    $person = employee($db, $user);
    $manager = $person['role'] === 'manager';
    if ($action === '/start') {
        run($db, 'UPDATE employees SET started=1 WHERE user_id=?', [$user]);
    }
    $text = 'Команды: /tasks — личные активные задачи; /task ID — карточка и история. Руководителю: /new USER|YYYY-MM-DD HH:MM|Название';
    $buttons = [];
    if (str_starts_with($action, '/new ')) {
        if (!$manager) {
            throw new DomainException('Создавать задачи может руководитель');
        }
        $parts = explode('|', substr($action, 5), 3);
        if (count($parts) !== 3) {
            throw new DomainException('Нужны исполнитель, срок и название через |');
        }
        $assignee = positive(trim($parts[0]), PHP_INT_MAX);
        $target = employee($db, $assignee);
        if (intValue($target['started']) !== 1) {
            throw new DomainException('Исполнитель должен сначала отправить /start');
        }
        $due = deadline(trim($parts[1]));
        $title = trim($parts[2]);
        if ($due <= $now || $due > $now + 366 * 86400 || mb_strlen($title) < 3 || mb_strlen($title) > 200) {
            throw new DomainException('Проверьте будущий срок до года и название 3–200 символов');
        }
        run($db, 'INSERT INTO tasks(title,creator,assignee,due_at) VALUES(?,?,?,?)', [$title,$user,$assignee,$due]);
        $id = (int)$db->lastInsertId();
        audit($db, $id, $user, 'Создана, назначена '.$assignee, $now);
        $t = row($db, 'SELECT * FROM tasks WHERE id=?', [$id]) ?? throw new RuntimeException('Task missing');
        enqueue($db, $assignee, 'assigned:'.$id, taskText($t), taskButtons($t, $assignee, $target['role'] === 'manager'), 'task', $id, 1);
        $text = 'Создана задача #'.$id.'. Постановка уведомления в очередь ещё не подтверждает прочтение.';
    } elseif (preg_match('~^/tasks(?: ([1-9][0-9]*))?$~D', $action, $m)) {
        $before = isset($m[1]) ? positive($m[1], PHP_INT_MAX) : PHP_INT_MAX;
        $items = run($db, "SELECT * FROM tasks WHERE assignee=? AND status IN ('new','working') AND id<? ORDER BY id DESC LIMIT 6", [$user,$before])->fetchAll();
        $more = count($items) > 5;
        $items = array_slice($items, 0, 5);
        $lines = [];
        $last = null;
        foreach ($items as $value) {
            $t = objectValue($value);
            $lines[] = taskText($t).' · /task '.intValue($t['id']);
            $last = intValue($t['id']);
        }
        $text = $lines === [] ? 'Активных личных задач нет.' : implode("\n\n", $lines).($more ? "\n\nДалее: /tasks ".$last : '');
    } elseif (preg_match('~^/task ([1-9][0-9]*)$~D', $action, $m)) {
        $id = positive($m[1]);
        $t = row($db, 'SELECT * FROM tasks WHERE id=?', [$id]);
        if (!$t || (!$manager && intValue($t['assignee']) !== $user)) {
            throw new DomainException('Задача недоступна');
        }
        $text = taskText($t);
        $buttons = taskButtons($t, $user, $manager);
        foreach (run($db, 'SELECT * FROM history WHERE task_id=? ORDER BY id DESC LIMIT 10', [$id])->fetchAll() as $value) {
            $h = objectValue($value);
            $text .= "\n".gmdate('d.m H:i', intValue($h['created_at'])).' UTC · '.intValue($h['actor']).': '.stringValue($h['action']);
        }
    } elseif ($callback && preg_match('/^task:([1-9][0-9]*):([1-9][0-9]*):(working|done|cancelled)$/D', $action, $m)) {
        $id = positive($m[1]);
        $version = positive($m[2]);
        $next = $m[3];
        $t = row($db, 'SELECT * FROM tasks WHERE id=?', [$id]);
        if (!$t || (!$manager && intValue($t['assignee']) !== $user)) {
            throw new DomainException('Задача недоступна');
        }
        if (intValue($t['version']) !== $version) {
            throw new DomainException('Карточка устарела. Откройте /task '.$id);
        }
        $allowed = $next === 'cancelled' ? ($manager && in_array($t['status'], ['new','working'], true)) :
            (intValue($t['assignee']) === $user && (($next === 'working' && $t['status'] === 'new') || ($next === 'done' && $t['status'] === 'working')));
        if (!$allowed) {
            throw new DomainException('Этот переход статуса недоступен');
        }
        run($db, 'UPDATE tasks SET status=?,version=version+1 WHERE id=? AND version=?', [$next,$id,$version]);
        audit($db, $id, $user, 'Статус: '.$next, $now);
        $t = row($db, 'SELECT * FROM tasks WHERE id=?', [$id]) ?? throw new RuntimeException('Task missing');
        $text = taskText($t);
        $buttons = taskButtons($t, $user, $manager);
        foreach (array_unique([intValue($t['creator']),intValue($t['assignee'])]) as $recipient) {
            if ($recipient !== $user) {
                enqueue($db, $recipient, 'change:'.$update.':'.$recipient, $text, [], 'task', $id, $version + 1);
            }
        }
    } elseif ($callback) {
        throw new DomainException('Неизвестная кнопка. Откройте актуальную карточку');
    }
    enqueue($db, $user, 'reply:'.$update, $text, $buttons);
}
function remind(PDO $db, int $now): void
{
    foreach (run($db, "SELECT t.* FROM tasks t JOIN employees e ON e.user_id=t.assignee WHERE t.status IN ('new','working') AND t.due_at<=? AND e.active=1 AND e.started=1 ORDER BY t.id LIMIT 1000", [$now])->fetchAll() as $value) {
        $t = objectValue($value);
        $id = intValue($t['id']);
        $key = 'late:'.$id.':'.gmdate('Y-m-d', $now);
        if (row($db, 'SELECT id FROM outbox WHERE event_key=?', [$key])) {
            continue;
        }
        enqueue($db, intValue($t['assignee']), $key, 'Просрочена задача: '.taskText($t)."\nОткройте /task ".$id, [], 'late', $id, intValue($t['version']));
        audit($db, $id, 0, 'Напоминание поставлено в очередь', $now);
    }
}
/**
 * @param array<string,mixed> $r
 */
function canDeliver(PDO $db, array $r, int $now): bool
{
    if (!row($db, 'SELECT user_id FROM employees WHERE user_id=? AND active=1 AND started=1', [intValue($r['chat_id'])])) {
        return false;
    }
    if (!in_array($r['guard_kind'], ['task','late'], true)) {
        return true;
    }
    $t = row($db,'SELECT * FROM tasks WHERE id=? AND version=?',[intValue($r['guard_id']),intValue($r['guard_version'])]);
    if (!$t) {
        return false;
    }
    return $r['guard_kind'] !== 'late' || (in_array($t['status'],['new','working'],true) && intValue($t['due_at']) <= $now && str_ends_with(stringValue($r['event_key']),gmdate('Y-m-d',$now)));
}
