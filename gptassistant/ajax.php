<?php
// local/gptassistant/ajax.php
require_once(__DIR__ . '/../../config.php');
require_login();

$action = required_param('action', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);

switch ($action) {
    case 'hint':
        $answer = required_param('answer', PARAM_TEXT);
        $result = give_hint($id, $answer);
        echo json_encode($result);
        break;
    default:
        throw new moodle_exception('invalidaction', 'local_gptassistant');
}