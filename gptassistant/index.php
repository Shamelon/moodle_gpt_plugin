<?php
require_once(__DIR__ . '/../../config.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->requires->css(__DIR__ . '/styles.css');
$PAGE->set_url(new moodle_url('/local/gptassistant/index.php'));
$PAGE->set_title(get_string('pluginname', 'local_gptassistant'));
$PAGE->set_heading(get_string('taskslist', 'local_gptassistant'));

echo $OUTPUT->header();

// Кнопка "Create new task"
echo html_writer::link(
    new moodle_url('/local/gptassistant/create.php'),
    get_string('createtask', 'local_gptassistant'),
    ['class' => 'btn btn-primary mb-3']
);

global $DB;
$tasks = $DB->get_records('local_gptassistant_tasks');

if (empty($tasks)) {
    echo html_writer::div(
        get_string('notasks', 'local_gptassistant'),
        'alert alert-info'
    );
} else {
    echo html_writer::start_div('list-group');
    foreach ($tasks as $task) {
        $taskcontent = html_writer::tag('h5', format_string($task->title), ['class' => 'mb-1']);
        
        // Кнопки действий
        $buttons = html_writer::link(
            new moodle_url('/local/gptassistant/edit.php', ['id' => $task->id]),
            get_string('edit'),
            ['class' => 'btn btn-sm btn-outline-primary mr-2']
        );
        $buttons .= html_writer::link(
            new moodle_url('/local/gptassistant/delete.php', ['id' => $task->id]),
            get_string('delete'),
            ['class' => 'btn btn-sm btn-outline-danger']
        );
        
        $taskcontent .= $buttons;
        
        echo html_writer::div(
            html_writer::link(
                new moodle_url('/local/gptassistant/view.php', ['id' => $task->id]),
                $taskcontent,
                [
                    'class' => 'list-group-item list-group-item-action d-block',
                    'style' => 'text-decoration: none;'
                ]
            ),
            'task-item list-group-item'
        );
    }
    echo html_writer::end_div();
}

echo $OUTPUT->footer();