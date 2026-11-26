<?php

declare(strict_types=1);

/**
 * @return array{start:int,end:int,previous_start:int}
 */
function dayBounds(string $date, string $zone): array
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone($zone));
    if (!$start || $start->format('Y-m-d') !== $date) {
        throw new InvalidArgumentException('Use YYYY-MM-DD');
    }
    return ['start' => $start->getTimestamp(),'end' => $start->modify('+1 day')->getTimestamp(),'previous_start' => $start->modify('-1 day')->getTimestamp()];
}

/**
 * @return array{paid:int,refunds:int,orders:int,leads:int,net:int}
 */
function totals(PDO $db, int $start, int $end): array
{
    $q = $db->prepare("SELECT COALESCE(SUM(CASE WHEN kind='payment' THEN amount_minor ELSE 0 END),0) paid,
        COALESCE(SUM(CASE WHEN kind='refund' THEN amount_minor ELSE 0 END),0) refunds,
        COUNT(DISTINCT CASE WHEN kind='payment' THEN order_id END) orders FROM money_events WHERE happened_at>=? AND happened_at<?");
    $q->execute([$start,$end]);
    $r = objectValue($q->fetch(PDO::FETCH_ASSOC));
    $q = $db->prepare('SELECT COUNT(*) FROM leads WHERE created_at>=? AND created_at<?');
    $q->execute([$start,$end]);
    $paid = intValue($r['paid']);
    $refunds = intValue($r['refunds']);
    return ['paid' => $paid,'refunds' => $refunds,'orders' => intValue($r['orders']),'leads' => intValue($q->fetchColumn()),'net' => $paid - $refunds];
}
function money(int $minor): string
{
    return number_format($minor / 100, 2, ',', ' ').' ₽';
}
function change(int $current, int $previous): string
{
    if ($previous <= 0) {
        return 'процент не рассчитывается: база сравнения ≤ 0';
    }
    $percent = round(($current - $previous) * 100 / $previous, 1);
    return ($percent > 0 ? '+' : '').number_format($percent, 1, ',', ' ').'%';
}
/**
 * @param array{paid:int,refunds:int,orders:int,leads:int,net:int} $today
 *
 * @param array{paid:int,refunds:int,orders:int,leads:int,net:int} $previous
 */
function reportText(string $date, string $zone, array $today, array $previous): string
{
    return "Итоги за $date · $zone\nПериод: 00:00–00:00 следующего дня\n\n"
        .'Оплаченных заказов: '.$today['orders']."\nПоступления: ".money($today['paid'])
        ."\nВозвраты: ".money($today['refunds'])."\nЧистые поступления: ".money($today['net'])
        ."\nНовых заявок: ".$today['leads']."\n\nК предыдущему дню:\nПоступления: ".change($today['paid'], $previous['paid'])
        ."\nЗаявки: ".change($today['leads'], $previous['leads'])."\n\nДанные получены полностью. Валюта: RUB.";
}
function buildReport(PDO $db, string $date, string $zone, string $chat, int $now): int
{
    $b = dayBounds($date, $zone);
    if ($b['end'] > $now) {
        throw new InvalidArgumentException('Period is not closed');
    }
    $db->beginTransaction();
    try {
        // В SQLite транзакция даёт согласованный снимок обеих выборок.
        $q = $db->prepare('SELECT id FROM reports WHERE report_date=? AND timezone=? AND chat_id=?');
        $q->execute([$date,$zone,$chat]);
        $existing = $q->fetchColumn();
        if ($existing !== false) {
            $db->commit();
            return intValue($existing);
        }
        foreach (['money','leads'] as $source) {
            $q = $db->prepare('SELECT available_from,complete_before FROM source_cursors WHERE source=?');
            $q->execute([$source]);
            $cursor = $q->fetch(PDO::FETCH_ASSOC);
            if (!is_array($cursor) || intValue($cursor['available_from']) > $b['previous_start'] || intValue($cursor['complete_before']) < $b['end']) {
                throw new RuntimeException('Source incomplete: '.$source);
            }
        }
        $today = totals($db, $b['start'], $b['end']);
        $previous = totals($db, $b['previous_start'], $b['start']);
        $text = reportText($date, $zone, $today, $previous);
        $db->prepare('INSERT INTO reports(report_date,timezone,chat_id,period_start,period_end,text) VALUES(?,?,?,?,?,?)')
            ->execute([$date,$zone,$chat,$b['start'],$b['end'],$text]);
        $id = intValue($db->lastInsertId());
        $db->commit();
        return $id;
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}
