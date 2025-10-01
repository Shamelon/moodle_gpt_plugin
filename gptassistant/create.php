<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/create_form.php');

require_login();
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/gptassistant/create.php'));

$form = new local_gptassistant_create_form();

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/gptassistant/index.php'));
} else if ($data = $form->get_data()) {
    global $DB;
    
    // Сохраняем основное задание
    $task = new stdClass();
    $task->title = $data->title;
    $task->content = $data->content['text'];
    $task->timecreated = time();
    $task->timemodified = time();
    $taskid = $DB->insert_record('local_gptassistant_tasks', $task);
    
    // Сохраняем ответы
    if (!empty($data->answer)) {
        foreach ($data->answer as $answertext) {
            if (!empty(trim($answertext))) {
                $answer = new stdClass();
                $answer->taskid = $taskid;
                $answer->answer = $answertext;
                $answer->timecreated = time();
                $DB->insert_record('local_gptassistant_answers', $answer);
            }
        }
    }
    
    redirect(new moodle_url('/local/gptassistant/view.php', ['id' => $taskid]));
}

$PAGE->set_title(get_string('createtask', 'local_gptassistant'));
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();