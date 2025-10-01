<?php

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__.'/../../../../config.php');
require_once($CFG->libdir . '/formslib.php');


class local_greet_translate_form extends moodleform {
    public function definition() {
        $mform = $this->_form;

        // Поле для ввода текста
        $mform->addElement('textarea', 'text', 'Введите название фильма', 
            ['rows' => 3, 'cols' => 20]);
        $mform->setType('text', PARAM_TEXT);
        $mform->addRule('text', get_string('required'), 'required', null, 'client');

        // Кнопка отправки
        $this->add_action_buttons(false, 'Искать');
    }
}