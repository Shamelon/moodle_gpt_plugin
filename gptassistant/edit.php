<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/form/create_form.php');

$id = required_param('id', PARAM_INT);
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/gptassistant/edit.php', ['id' => $id]));
$PAGE->set_title(get_string('edittask', 'local_gptassistant'));

global $DB;
$task = $DB->get_record('local_gptassistant_tasks', ['id' => $id], '*', MUST_EXIST);

// Передаем id в форму через customdata
$customdata = ['id' => $id, 'task' => $task];
$form = new local_gptassistant_create_form(null, $customdata, 'post'); // Изменено здесь

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/gptassistant/index.php'));
} else if ($data = $form->get_data()) {
    $task->title = $data->title;
    $task->content = $data->content['text'];
    $task->timemodified = time();
    
    $DB->update_record('local_gptassistant_tasks', $task);
    
    $DB->delete_records('local_gptassistant_answers', ['taskid' => $id]);
    
    if (!empty($data->answer)) {
        foreach ($data->answer as $answertext) {
            if (!empty(trim($answertext))) {
                $answer = new stdClass();
                $answer->taskid = $id;
                $answer->answer = $answertext;
                $answer->timecreated = time();
                $DB->insert_record('local_gptassistant_answers', $answer);
            }
        }
    }
    
    redirect(
        new moodle_url('/local/gptassistant/view.php', ['id' => $id]),
        get_string('changessaved', 'local_gptassistant'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Устанавливаем данные формы (включая id)
$data = new stdClass();
$data->id = $id; // Ключевое изменение
$data->title = $task->title;
$data->content = ['text' => $task->content, 'format' => FORMAT_HTML];
$answers = $DB->get_fieldset_select('local_gptassistant_answers', 'answer', 'taskid = ?', [$id]);
$data->answer = $answers;
$form->set_data($data);

echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();