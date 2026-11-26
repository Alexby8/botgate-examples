<?php
declare(strict_types=1);
require __DIR__.'/answer.php';

foreach (['Как выгрузить отчёт CSV?', 'Как пригласить сотрудника?', 'Когда вернут деньги по заказу?'] as $question) {
    $answer = answerQuestion($question, useModel: false);
    echo $question."\n".($answer ?? "Передать вопрос оператору.")."\n\n";
}
