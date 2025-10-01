<?php
require_once(__DIR__ . '/../../config.php');

require_login();
$id = required_param('id', PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/gptassistant/view.php', ['id' => $id]));
$PAGE->set_title(get_string('viewtask', 'local_gptassistant'));

global $DB;
$task = $DB->get_record('local_gptassistant_tasks', ['id' => $id], '*', MUST_EXIST);

// Инициализация переменных
$useranswer = '';
$hint = '';
$validationresult = null;

// Обработка отправки формы
$formsubmitted = optional_param('submitanswer', false, PARAM_BOOL);
$gethint = optional_param('gethint', false, PARAM_BOOL);

if ($formsubmitted || $gethint) {
    $useranswer = optional_param('answer', '', PARAM_TEXT);

    if ($formsubmitted) {
        // Валидация ответа
        $validationresult = validate_answer($task->id, $useranswer);
    } elseif ($gethint) {
        // Получение подсказки
        $hintresult = give_hint($task->id, $useranswer);
        $hint = $hintresult['hint'];
    }
}

echo $OUTPUT->header();

echo html_writer::tag('h2', format_string($task->title));
echo format_text($task->content, FORMAT_HTML);

// Отображение подсказки, если есть
if (!empty($hint)) {
    echo html_writer::div(
        html_writer::tag('strong', get_string('hinttitle', 'local_gptassistant') . ': ') . $hint,
        'alert alert-info mt-3'
    );
}

// Форма для ввода ответа
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $PAGE->url,
    'class' => 'mt-3'
]);
echo html_writer::tag('h4', get_string('youranswer', 'local_gptassistant'));
echo html_writer::tag('textarea', htmlspecialchars($useranswer), [
    'name' => 'answer',
    'class' => 'form-control',
    'rows' => 3,
    'required' => 'required'
]);

// Скрытые поля для действий
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'id',
    'value' => $id
]);

// Кнопки
echo html_writer::start_div('mt-2');
echo html_writer::tag('button', get_string('submitanswer', 'local_gptassistant'), [
    'type' => 'submit',
    'name' => 'submitanswer',
    'value' => 1,
    'class' => 'btn btn-primary'
]);

echo html_writer::tag('button', get_string('gethint', 'local_gptassistant'), [
    'type' => 'submit',
    'name' => 'gethint',
    'value' => 1,
    'class' => 'btn btn-info ml-2'
]);
echo html_writer::end_div();

echo html_writer::end_tag('form');

// Отображение результатов валидации
if ($validationresult) {
    if ($validationresult['is_correct']) {
        \core\notification::success(get_string('answercorrect', 'local_gptassistant'));
    } else {
        \core\notification::error(get_string('answerincorrect', 'local_gptassistant'));
        if (!empty($validationresult['feedback'])) {
            \core\notification::info($validationresult['feedback']);
        }
    }
}

echo html_writer::link(
    new moodle_url('/local/gptassistant/index.php'),
    get_string('backtolist', 'local_gptassistant'),
    ['class' => 'btn btn-secondary mt-3']
);

echo $OUTPUT->footer();

function validate_answer($taskid, $useranswer)
{
    global $DB;

    // Получаем данные задания и правильные ответы
    $task = $DB->get_record('local_gptassistant_tasks', ['id' => $taskid], '*', MUST_EXIST);
    $correctanswers = $DB->get_fieldset_select(
        'local_gptassistant_answers',
        'answer',
        'taskid = ?',
        [$taskid]
    );

    // Подготавливаем данные для промта
    $data = [
        'task' => $task->content,
        'correct_answers' => implode("\n", $correctanswers),
        'user_answer' => $useranswer
    ];


    $prompttemplate = "Задача: {{task}}
    Правильные варианты ответа:
    {{correct_answers}}
    
    Ответ пользователя: {{user_answer}}";

    // Загружаем промт из файла
    global $CFG;
    $promptfile = $CFG->dirroot . '/local/gptassistant/prompts/validation_prompt.txt';
    if (!file_exists($promptfile)) {
        throw new moodle_exception('promptfilenotfound', 'local_gptassistant');
    }

    $globalprompt = file_get_contents($promptfile);

    // Заменяем плейсхолдеры в промте
    $prompt = str_replace(
        ['{{task}}', '{{correct_answers}}', '{{user_answer}}'],
        [$data['task'], $data['correct_answers'], $data['user_answer']],
        $prompttemplate
    );

    // Вызываем Yandex GPT API
    $gptresponse = call_yandex_gpt_api($globalprompt, $prompt, 3);

    // Обрабатываем ответ от GPT
    $gptdecision = process_gpt_response($gptresponse);

    // Если GPT не смог принять решение, используем стандартную проверку
    if ($gptdecision === null) {
        return validate_with_fallback($useranswer, $correctanswers);
    }

    return [
        'is_correct' => $gptdecision,
        'feedback' => $gptdecision ? '' : get_string('tryagain', 'local_gptassistant')
    ];
}


function call_yandex_gpt_api($globalprompt, $prompt, $maxTokens)
{
    $apiurl = 'https://llm.api.cloud.yandex.net/foundationModels/v1/completion';

    $data = [
        'modelUri' => 'gpt://b1gkmcna776tdrjrgd7e/yandexgpt',
        "completionOptions" => [
            "stream" => false,
            "temperature" => get_config('local_gptassistant', 'temperature'),
            "maxTokens" => "2000",
            "reasoningOptions" => [
                "mode" => "DISABLED"
            ]
        ],
        'messages' => [
            [
                'role' => 'system',
                'text' => $globalprompt
            ],
            [
                'role' => 'user',
                'text' => $prompt
            ]
        ],
        'temperature' => 0.1,
        'maxTokens' => $maxTokens
    ];

    $IAM_TOKEN = get_config('local_gptassistant', 'iam_token');
    if (empty($IAM_TOKEN)) {
        throw new moodle_exception('IAM token not configured');
    }

    $ch = curl_init($apiurl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $IAM_TOKEN,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function give_hint($taskid, $useranswer) {
    global $DB, $CFG;

    // Получаем данные задания
    $task = $DB->get_record('local_gptassistant_tasks', ['id' => $taskid], '*', MUST_EXIST);

    // Подготавливаем данные для промта
    $data = [
        'task' => $task->content,
        'user_answer' => $useranswer
    ];

    // Загружаем глобальный промт из файла
    $promptfile = $CFG->dirroot . '/local/gptassistant/prompts/hint_prompt.txt';
    if (!file_exists($promptfile)) {
        throw new moodle_exception('promptfilenotfound', 'local_gptassistant');
    }
    $globalprompt = file_get_contents($promptfile);

    // Создаем промт с контекстом задания
    $prompttemplate = "Задание: {{task}}\nОтвет пользователя: {{user_answer}}";

    // Заменяем плейсхолдеры в промте
    $prompt = str_replace(
        ['{{task}}', '{{user_answer}}'],
        [$data['task'], $data['user_answer']],
        $prompttemplate
    );

    // Вызываем Yandex GPT API
    $gptresponse = call_yandex_gpt_api($globalprompt, $prompt, 100);

    // Обрабатываем ответ от GPT
    return [
        'hint' => process_hint_response($gptresponse)
    ];
}

function process_hint_response($response) {
    if (empty($response['result']['alternatives'][0]['message']['text'])) {
        return get_string('hinterror', 'local_gptassistant');
    }

    $hint = trim($response['result']['alternatives'][0]['message']['text']);
    
    $hint = trim($hint, '"\'');

    return $hint;
}


function process_gpt_response($response)
{
    if (empty($response['result']['alternatives'][0]['message']['text'])) {
        return null;
    }

    $text = strtolower(trim($response['result']['alternatives'][0]['message']['text']));

    if (strpos($text, 'да') !== false) {
        return true;
    } elseif (strpos($text, 'нет') !== false) {
        return false;
    }

    return null;
}


function validate_with_fallback($useranswer, $correctanswers)
{
    $useranswer = normalize_answer($useranswer);
    $correctanswers = array_map('normalize_answer', $correctanswers);

    if (in_array($useranswer, $correctanswers)) {
        return [
            'is_correct' => true,
            'feedback' => ''
        ];
    }

    if (is_numeric($useranswer)) {
        foreach ($correctanswers as $correct) {
            if (is_numeric($correct) && abs(floatval($useranswer) - floatval($correct)) < 0.01) {
                return [
                    'is_correct' => true,
                    'feedback' => ''
                ];
            }
        }
    }

    return [
        'is_correct' => false,
        'feedback' => get_string('tryagain', 'local_gptassistant')
    ];
}

function normalize_answer($answer)
{
    // Приведение к нижнему регистру
    $answer = mb_strtolower(trim($answer), 'UTF-8');

    // Замена запятых на точки для десятичных чисел
    $answer = str_replace(',', '.', $answer);

    // Удаление лишних пробелов
    $answer = preg_replace('/\s+/', ' ', $answer);

    // Заменяем дробные формы
    if (strpos($answer, '/') !== false) {
        $parts = explode('/', $answer);
        if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
            $answer = (float) $parts[0] / (float) $parts[1];
        }
    }

    // Заменяем текстовые представления чисел
    $textnumbers = [
        'один' => 1,
        'два' => 2,
        'три' => 3,
        'четыре' => 4,
        'пять' => 5,
        'шесть' => 6,
        'семь' => 7,
        'восемь' => 8,
        'девять' => 9,
        'ноль' => 0
    ];

    foreach ($textnumbers as $text => $num) {
        $answer = str_replace($text, $num, $answer);
    }

    return $answer;
}