<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_gptassistant', get_string('pluginname', 'local_gptassistant'));
    $ADMIN->add('localplugins', $settings);

    // YandexGPT API settings section
    $settings->add(new admin_setting_heading('local_gptassistant/yandexgpt', 
        get_string('yandexgptsettings', 'local_gptassistant'), ''));

    // IAM Token for YandexGPT API
    $settings->add(new admin_setting_configtext('local_gptassistant/iam_token',
        get_string('iamtoken', 'local_gptassistant'),
        get_string('iamtoken_desc', 'local_gptassistant'), '', PARAM_RAW_TRIMMED));

    // Optional: Add temperature setting
    $settings->add(new admin_setting_configtext('local_gptassistant/temperature',
        get_string('temperature', 'local_gptassistant'),
        get_string('temperature_desc', 'local_gptassistant'), '0.6', PARAM_FLOAT));
}