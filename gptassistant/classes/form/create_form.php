<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class local_gptassistant_create_form extends moodleform {
    public function definition() {
        $mform = $this->_form;
        
        // Добавляем скрытое поле для id
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'title', get_string('title', 'local_gptassistant'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        
        $mform->addElement('editor', 'content', get_string('content', 'local_gptassistant'));
        $mform->setType('content', PARAM_RAW);

        // Изменяем тип кнопки на 'button' и добавляем JavaScript
        $repeatoptions = [
            $mform->createElement('text', 'answer', get_string('correctanswer', 'local_gptassistant'), 
                ['size' => '60', 'class' => 'form-control'])
        ];
        
        $repeateloptions = [
            'answer' => [
                'type' => PARAM_TEXT,
                'label' => get_string('correctanswer', 'local_gptassistant'),
            ]
        ];

        $this->repeat_elements(
            $repeatoptions,
            1,
            $repeateloptions,
            'answers_repeats',
            'answers_add_fields',
            1,
            get_string('addmoreanswers', 'local_gptassistant'),
            true,
            'answer_add_button'
        );

        // Добавляем JavaScript для сохранения id в URL
        $mform->addElement('static', 'script', '', 
            '<script>
                document.getElementById("answer_add_button").type = "button";
                document.getElementById("answer_add_button").onclick = function() {
                    const form = document.querySelector("form.task-form");
                    const url = new URL(form.action);
                    url.searchParams.set("id", "' . optional_param('id', 0, PARAM_INT) . '");
                    form.action = url.toString();
                    form.submit();
                };
            </script>'
        );

        $this->add_action_buttons(true, get_string('createtask', 'local_gptassistant'));
    }
}