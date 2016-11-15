<?php

//$Id: settings.php,v 1.1.2.2 2007/12/19 17:38:41 skodak Exp $
//$settings = new admin_settingpage( 'mod_connect', get_string( 'settings', 'mod_connect' ) );
// Warning message if local_refined_services not installed or missing username or password
$message = '';
if (!$DB->get_record('config_plugins', array('plugin' => 'local_refinedservices', 'name' => 'version'))) {
    $message .= get_string('localrefinedservicesnotinstalled', 'connectmeeting') . '<br />';
}
$rs_plugin_link = new moodle_url('/admin/settings.php?section=local_refinedservices');
if (empty($CFG->connect_service_username)) {
    $message .= get_string('connectserviceusernamenotgiven', 'connectmeeting', array('url' => $rs_plugin_link->out())) . '<br />';
}

if (empty($CFG->connect_service_password)) {
    $message .= get_string('connectservicepasswordnotgiven', 'connectmeeting', array('url' => $rs_plugin_link->out())) . '<br />';
}

if (!empty($message)) {
    $caption = html_writer::tag('div', $message, array('class' => 'notifyproblem'));
    $setting = new admin_setting_heading('refined_services_warning', $caption, '<strong>' . get_string('connectsettingsrequirement', 'connectmeeting') . '</strong>');
    $settings->add($setting);
}

if ($hassiteconfig && !empty($CFG->connect_service_username) && !empty($CFG->connect_service_password)) {
    $settings->add(new admin_setting_configcheckbox('connect_telephony', get_string('telephony', 'connectmeeting'), get_string('telephony_hint', 'connectmeeting'), '1'));

    // Logo file setting.
    $name = 'mod_connectmeeting/meeting_icon';
    $title = get_string('meetingicon', 'connectmeeting');
    $description = get_string('meetingicondesc', 'connectmeeting');
    $setting = new admin_setting_configstoredfile($name, $title, $description, 'meeting_icon');
    $settings->add($setting);

    $setting = new admin_setting_configcheckbox('connect_adobe_addin', get_string('adobe_addin', 'connectmeeting'), get_string('configadobe_addin', 'connectmeeting'), 0);
    $setting->set_updatedcallback('connect_update_config');
    $settings->add($setting);

    $setting = new admin_setting_configtext('connect_template', get_string('template', 'connectmeeting'), get_string('configtemplate', 'connectmeeting'), 0, PARAM_INT, 50);
    $setting->set_updatedcallback('connect_update_config');
    $settings->add($setting);

    $setting = new admin_setting_configtext('connect_autofolder', get_string('autofolder', 'connectmeeting'), get_string('configautofolder', 'connectmeeting'), 0, PARAM_INT, 50);
    $setting->set_updatedcallback('connect_update_config');
    $settings->add($setting);


    if (isset($CFG->local_reminders)) {
        $settings->add(new admin_setting_configtext('local_reminders', get_string('num', 'local_reminders'), get_string('confignum', 'local_reminders'), 3, PARAM_INT, 50));
    }
    
    // Duration
    $options = array();    
      for ($i = 1; $i <= 51; $i++) $options[60 * 15 * $i] = GMDATE('H:i', 60 * 15 * $i);
      $settings->add(new admin_setting_configselect('connect_meeting_duration', new lang_string('connect_meeting_duration', 'connectmeeting'),
      '',60*60, $options)); 

    $setting = new admin_setting_configcheckbox('connect_iconguests', get_string('iconguests', 'connectmeeting'), '', 0);
    $setting->set_updatedcallback('connect_update_config');
    $settings->add($setting);

    $setting = new admin_setting_configcheckbox('connect_iconnorec', get_string('iconnorec', 'connectmeeting'), '', 0);
    $setting->set_updatedcallback('connect_update_config');
    $settings->add($setting);

    $dgoptions = array(
        0 => get_string('off', 'connectmeeting'),
        1 => get_string('fromadobe', 'connectmeeting'));
    if (!empty($CFG->ft_server))
        $dgoptions += array(2 => get_string('fasttrack', 'connectmeeting'));

    $dgoptions += array(3 => get_string('vantagepoint', 'connectmeeting'));
    $settings->add(new admin_setting_configselect('mod_connectmeeting/detailgrading', new lang_string('detailgradingmeeting', 'connectmeeting'), '', 'off', $dgoptions));
    
    $doptions = array();
    for ($i = 1; $i <= 51; $i++) $doptions[60 * 15 * $i] = GMDATE('H:i', 60 * 15 * $i);
    $settings->add(new admin_setting_configselect('mod_connectmeeting/compdelay', new lang_string('compdelay', 'connectmeeting'), '', 60 * 75, $doptions));
}

if (!function_exists('connect_update_config')) {

    function connect_update_config() {
        global $CFG;
        //die('connect_update_config');
        $params = array();
        foreach ($CFG as $name => $value) {
            if (preg_match('/connect\_/', $name) || $name == 'refinedservices_debug') {
                $params[] = array('name' => $name, 'value' => $value);
            }
        }
        //var_dump($params);
        //die('connect_update_config');
        if (!empty($params)) {
            require_once($CFG->dirroot . '/local/connect/lib.php');
            $connect = _connect_get_instance();
            return $connect->connect_call('setconfig', $params);
        }
    }

}
