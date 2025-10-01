<?php
require_once(__DIR__ . '/../../config.php');

$id = required_param('id', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

require_login();
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/gptassistant/delete.php', ['id' => $id]));

global $DB;
$task = $DB->get_record('local_gptassistant_tasks', ['id' => $id]);

if (!$task) {
    throw new moodle_exception('tasknotfound', 'local_gptassistant');
}

// Если подтверждение получено
if ($confirm && confirm_sesskey()) {
    $DB->delete_records('local_gptassistant_tasks', ['id' => $id]);
    
    redirect(
        new moodle_url('/local/gptassistant/index.php'),
        get_string('taskdeleted', 'local_gptassistant'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Вывод страницы подтверждения
$PAGE->set_title(get_string('deletetask', 'local_gptassistant'));
$PAGE->set_heading(get_string('deletetask', 'local_gptassistant'));

echo $OUTPUT->header();

// Подтверждающее сообщение
$message = get_string('deleteconfirm', 'local_gptassistant', format_string($task->title));
$continueurl = new moodle_url('/local/gptassistant/delete.php', ['id' => $id, 'confirm' => 1]);
$cancelurl = new moodle_url('/local/gptassistant/index.php');

echo $OUTPUT->confirm($message, $continueurl, $cancelurl);

echo $OUTPUT->footer();