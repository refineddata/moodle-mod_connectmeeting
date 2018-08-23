<?php // $Id: mod_form.php
require_once($CFG->dirroot . '/course/moodleform_mod.php');
require_once("$CFG->dirroot/mod/connectmeeting/lib.php");
require_once($CFG->libdir . '/filelib.php');

class mod_connectmeeting_mod_form extends moodleform_mod {
    var $_gradings;
    protected $_fmoptions = array(
        // 3 == FILE_EXTERNAL & FILE_INTERNAL
        // These two constant names are defined in repository/lib.php
        'return_types' => 3,
        'accepted_types' => 'images',
        'maxbytes' => 0,
        'maxfiles' => 1
    );

    function definition() {

        global $COURSE, $CFG, $DB, $USER, $PAGE;

//        $PAGE->requires->js('/local/connect/js/jQueryFileTree.min.js');
//        $PAGE->requires->js('/local/connect/js/local_connect.js');
        $PAGE->requires->js_init_code('window.browsetitle = "' . get_string( 'browsetitle', 'connectmeeting' ) . '";');
        $PAGE->requires->css('/local/connect/css/jQueryFileTree.css');

        $mform =& $this->_form;
        // this hack is needed for different settings of each subtype
        if (!empty($this->_instance)) {
            $new = true;
            //$type = $DB->get_field('connectmeeting', 'type', array('id' => $this->_instance));
        } else {
            $new = false;
            //$type = required_param('type', PARAM_ALPHANUM);
        }

        $PAGE->requires->string_for_js('notfound', 'connectmeeting');
        $PAGE->requires->string_for_js('typelistmeeting', 'connectmeeting');
        if (!empty($CFG->connect_update) && $CFG->connect_update) {
            $PAGE->requires->string_for_js('whensaved', 'connectmeeting');
        } else {
            $PAGE->requires->string_for_js('connect_not_update', 'connectmeeting');
        }

        //$PAGE->requires->js_init_code('window.connect_type = "' . $type . '";');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'type', 'meeting');
        $mform->setType('type', PARAM_RAW);
        $mform->addElement('hidden', 'newurl', '');
        $mform->setType('newurl', (!empty($CFG->formatstringstriptags)) ? PARAM_TEXT : PARAM_CLEAN);
        $mform->addElement('hidden', 'eventid', 0);
        $mform->setType('eventid', PARAM_INT);
        $mform->addElement('hidden', 'scoid', 0);
        $mform->setType('scoid', PARAM_INT);
        //$mform->setDefault('type', $type);
        $url = optional_param('url', '', PARAM_RAW);
        $name = optional_param('name', '', PARAM_RAW);
        if (is_numeric(substr($url, 0, 1))) $url = 'INVALID';
        if ($url != clean_text($url, PARAM_ALPHAEXT)) $url = 'INVALID';
        if (strpos($url, '/') OR strpos($url, ' ')) $url = 'INVALID';
        if (!empty($url)) $mform->setDefault('newurl', $url);

        $mform->addElement('header', 'general', get_string('typelistmeeting', 'connectmeeting') . ' ' . get_string('connect_details', 'connectmeeting'));

//-------------------------------------------------------------------------------
        $options = array();
        $options[0] = get_string('none');

        for ($i = 720; $i >= 120; $i -= 60) {
            $options[$i] = get_string('numhours', '', $i / 60);
        }
        for ($i = 115; $i >= 5; $i -= 5) {
            $options[$i] = get_string('numminutes', '', $i );
        }
                $formgroup = array();
                $formgroup[] =& $mform->createElement('text', 'url', '', array('maxlength' => 255, 'size' => 48, 'class' => 'ignoredirty'));
                $mform->setType('url', (!empty($CFG->formatstringstriptags)) ? PARAM_TEXT : PARAM_CLEAN);
                if (empty($_REQUEST['update'])) {
                    $formgroup[] =& $mform->createElement('button', 'browse', get_string('browse', 'connectmeeting'));
                    //if ($CFG->connect_update) {
                        $formgroup[] =& $mform->createElement('button', 'generate', get_string('generate', 'connectmeeting'));
                    //}
                }
                $mform->addElement('group', 'urlgrp', get_string('url', 'connectmeeting'), $formgroup, array(' '), false);
                $mform->setDefault('url', $url);
                if (empty($_REQUEST['update'])) {
                    $mform->addRule( 'urlgrp', null, 'required' );

                    $mform->addGroupRule( 'urlgrp', array(
                        'url' => array(
                            array( null, 'required', null, 'client' )
                        ),
                    ) );
                }
                if (!empty($_REQUEST['update'])) {
                    $mform->hardFreeze('urlgrp');
                }
                

        $goptions = array();
        for ($i = 100; $i >= 1; $i--) {
            $goptions[$i] = $i . '%';
        }

//-------------------------------------------------------------------------------
        
        
            $mform->addElement('text', 'name', get_string('connect_name', 'connectmeeting'), array(
                'class' => 'ignoredirty',
                'size' => '64',
                'maxlength' => '60',
                'style' => 'width:412px;'
            ));
            $mform->setDefault('name', $name);

        
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');

//-------------------------------------------------------------------------------
        // Duration
        $doptions = array();
        $doptions[60 * 15 * 01] = '15 ' . get_string('mins');
        $doptions[60 * 30 * 01] = '30 ' . get_string('mins');
        $doptions[60 * 45 * 01] = '45 ' . get_string('mins');
        $doptions[60 * 60 * 01] = '1 ' . get_string('hour');
        for ($i = 1; $i <= 51; $i++) $doptions[60 * 15 * $i] = GMDATE('H:i', 60 * 15 * $i);

        
            // Start Date
            $mform->addElement('date_time_selector', 'start', get_string('start', 'connectmeeting'));
            $mform->setDefault('start', floor(time() / 3600) * 3600 + 3600);

            $mform->addElement('select', 'duration', get_string('duration', 'connectmeeting'), $doptions);
            $default = !empty($CFG->connect_meeting_duration) ?  $CFG->connect_meeting_duration : 60 * 60;
            $mform->setDefault('duration', $default);
        

        $this->standard_intro_elements(false, get_string('summary', 'connectmeeting'));

        $mform->addElement('text', 'credit', get_string('credit', 'connectmeeting'), 'size="50"');
        $mform->setType('credit', PARAM_TEXT );

//-------------------------------------------------------------------------------
        
        	
        	if( isset( $CFG->connect_adobe_addin ) && $CFG->connect_adobe_addin ){
        		$addinroles = array();
        		
        		$availableroles = get_assignable_roles(context_course::instance( $COURSE->id ));
        		if (is_array($availableroles)) {
        			foreach ($availableroles as $roleid => $rolename) {
        				$addinroles[$roleid] = $rolename;
        			}
        		}
//         		$addinroles['student'] = 'student';
        		$mform->addElement('select', 'addinroles', get_string('addinroles', 'connectmeeting'), $addinroles);
        		$mform->getElement('addinroles')->setMultiple(true);
        		$mform->setType('addinroles', PARAM_TEXT);
        		$mform->addHelpButton('addinroles', "addinroles", 'connectmeeting');
        	}
        	
            if (isset($CFG->connect_icondisplay) AND $CFG->connect_icondisplay) {
                $mform->addElement('header', 'disphdr', get_string('disphdr', 'connectmeeting'));
                
                $displayoncoursedefault = isset( $CFG->connect_displayoncourse ) ? $CFG->connect_displayoncourse : 1;
                $mform->addElement('checkbox', 'displayoncourse', get_string('displayoncourse', 'connectmeeting'));
                $mform->setDefault('displayoncourse', $displayoncoursedefault);

                $szopt = array();
                //$szopt['none'] = get_string('none');
                $szopt['large'] = get_string('large', 'connectmeeting');
                $szopt['medium'] = get_string('medium', 'connectmeeting');
                $szopt['small'] = get_string('small', 'connectmeeting');
                $szopt['block'] = get_String('block', 'connectmeeting');
                $szopt['custom'] = get_String('custom', 'connectmeeting');
                $mform->addElement('select', 'iconsize', get_string('iconsize', 'connectmeeting'), $szopt);
                $default = isset( $CFG->connect_iconsize ) ? $CFG->connect_iconsize : 'medium';
                $mform->setDefault('iconsize', $default);

                $posopt = array();
                $posopt['l'] = get_string('left', 'connectmeeting');
                $posopt['c'] = get_string('center', 'connectmeeting');
                $mform->addElement('select', 'iconpos', get_string('iconpos', 'connectmeeting'), $posopt);
                $default = isset( $CFG->connect_iconpos ) ? $CFG->connect_iconpos : 'left';
                $mform->setDefault('iconpos', $default);

                if (isset($CFG->connect_maxviews) AND $CFG->connect_maxviews >= 0) {
                    $vstr = get_string('views', 'connectmeeting');
                    $viewopts = array(0 => get_string('disabled', 'connectmeeting'), 1 => '1' . get_string('view', 'connectmeeting'));
                    for ($i = 2; $i <= 100; $i++) {
                        $viewopts[$i] = $i . $vstr;
                    }
                    $mform->addElement('select', 'maxviews', get_string('maxviews', 'connectmeeting'), $viewopts);
                    $mform->setDefault('maxviews', $CFG->connect_maxviews);
                }

                $mform->addElement('checkbox', 'iconsilent', get_string('iconsilent', 'connectmeeting'));
                $default = isset( $CFG->connect_iconsilent ) ? $CFG->connect_iconsilent : 0;
                $mform->setDefault('iconsilent', $default);
                //$mform->setAdvanced('iconsilent', 'processing');
               
                    $mform->addElement('checkbox', 'iconphone', get_string('iconphone', 'connectmeeting'));
                    //$mform->setAdvanced('iconphone', 'icon');
                
                //$mform->addElement('checkbox', 'iconmouse', get_string('iconmouse', 'connectmeeting'));
                //$default = !empty( $CFG->connect_mouseovers ) ? 0 : 1;
                //$mform->setDefault('iconmouse', $default);
                //$mform->setAdvanced('iconmouse', 'icon');
               
                    $mform->addElement('checkbox', 'iconguests', get_string('iconguests', 'connectmeeting'));
                    $default = isset( $CFG->connect_iconguests ) ? $CFG->connect_iconguests : 0;
                    $mform->setDefault('iconguests', $default);
                    //$mform->setAdvanced('iconguests', 'icon');
                    $mform->addElement('checkbox', 'iconnorec', get_string('iconnorec', 'connectmeeting'));
                    $default = isset( $CFG->connect_iconnorec ) ? $CFG->connect_iconnorec : 0;
                    $mform->setDefault('iconnorec', $default);
                    //$mform->setAdvanced('iconnorec', 'icon');
               
                $mform->addElement('htmleditor', 'extrahtml', get_string('extrahtml', 'connectmeeting'), array('cols' => '64', 'rows' => '8'));
                //$mform->setAdvanced('extrahtml', 'icon');

                $mform->addElement('filemanager', 'forceicon_filemanager', get_string('forceicon', 'connectmeeting'), null, $this->_fmoptions);
                //$mform->setAdvanced('forceicon_filemanager', 'icon');

                $mform->disabledIf('iconpos', 'iconsize', 'eq', 'none');
                $mform->disabledIf('iconsilent', 'iconsize', 'eq', 'none');
                $mform->disabledIf('iconphone', 'iconsize', 'eq', 'none');
                //$mform->disabledIf('iconmouse', 'iconsize', 'eq', 'none');
                $mform->disabledIf('iconguests', 'iconsize', 'eq', 'none');
                $mform->disabledIf('iconnorec', 'iconsize', 'eq', 'none');
                $mform->disabledIf('extrahtml', 'iconsize', 'eq', 'none');
                $mform->disabledIf('forceicon_filemanager', 'iconsize', 'ne', 'custom');
                $mform->disabledIf('iconphone', 'iconsilent', 'checked');
                //$mform->disabledIf('iconmouse', 'iconsilent', 'checked');
                //$mform->disabledIf('extrahtml', 'iconsilent', 'checked');
            }
        

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'grading', get_string('gradinghdr', 'connectmeeting'));
//        $mform->addHelpButton('grading', 'grading', 'connectmeeting');

        
            $dgoptions = array(
                0 => get_string('off', 'connectmeeting'),
                1 => get_string('fromadobe', 'connectmeeting')
            );
        
        if (!empty($CFG->ft_server))
            $dgoptions += array(2 => get_string('fasttrack', 'connectmeeting'));
        $vp_license_active = connect_check_vp_license_active();
        if (!empty($vp_license_active)) {
            $dgoptions += array( 3 => get_string( 'vantagepoint', 'connectmeeting' ) );
        }
        $mform->addElement('select', 'detailgrading', get_string("detailgradingmeeting", 'connectmeeting'), $dgoptions);
        $vp_license_active = connect_check_vp_license_active();
        if (!empty($vp_license_active)) {
            $mform->addHelpButton( 'detailgrading', "detailgradingmeeting", 'connectmeeting' );
        } else {
            $mform->addHelpButton( 'detailgrading', "detailgradingmeetingnovp", 'connectmeeting' );
        }
        $pluginconfig = get_config("mod_connectmeeting", "detailgrading");
        $default = !empty( $pluginconfig ) ? $pluginconfig : 0;
        $mform->setDefault('detailgrading', $default);
        //$mform->setAdvanced('detailgrading', 'grade');

        $mform->addElement('html', '<div id="regular-gradings">');
        $formgroup = array();
        $formgroup[] =& $mform->createElement('select', 'threshold[1]', '', $options);
        $mform->setDefault('threshold[1]', 0);
        $mform->disabledIf('threshold[1]', 'detailgrading', 'eq', 0);
        $mform->disabledIf('threshold[1]', 'detailgrading', 'eq', 3);
        $formgroup[] =& $mform->createElement('select', 'grade[1]', '', $goptions);
        $mform->setDefault('grade[1]', 0);
        $mform->disabledIf('grade[1]', 'detailgrading', 'eq', 0);
        $mform->disabledIf('grade[1]', 'detailgrading', 'eq', 3);
        $mform->addElement('group', 'tg1', get_string("tgmeeting", 'connectmeeting') . ' 1', $formgroup, array(' '), false);
        $mform->addHelpButton('tg1', "tgmeeting", 'connectmeeting');
        //$mform->setAdvanced('tg1', 'grade');

        $formgroup = array();
        $formgroup[] =& $mform->createElement('select', 'threshold[2]', '', $options);
        $mform->setDefault('threshold[2]', 0);
        $mform->disabledIf('threshold[2]', 'detailgrading', 'eq', 0);
        $mform->disabledIf('threshold[2]', 'detailgrading', 'eq', 3);
        $formgroup[] =& $mform->createElement('select', 'grade[2]', '', $goptions);
        $mform->setDefault('grade[2]', 0);
        $mform->disabledIf('grade[2]', 'detailgrading', 'eq', 0);
        $mform->disabledIf('grade[2]', 'detailgrading', 'eq', 3);
        $mform->addElement('group', 'tg2', get_string("tgmeeting", 'connectmeeting') . ' 2', $formgroup, array(' '), false);
        $mform->addHelpButton('tg2', "tgmeeting", 'connectmeeting');
        //$mform->setAdvanced('tg2', 'grade');

        $formgroup = array();
        $formgroup[] =& $mform->createElement('select', 'threshold[3]', '', $options);
        $mform->setDefault('threshold[3]', 0);
        $mform->disabledIf('threshold[3]', 'detailgrading', 'eq', 0);
        $mform->disabledIf('threshold[3]', 'detailgrading', 'eq', 3);
        $formgroup[] =& $mform->createElement('select', 'grade[3]', '', $goptions);
        $mform->setDefault('grade[3]', 0);
        $mform->disabledIf('grade[3]', 'detailgrading', 'eq', 0);
        $mform->disabledIf('grade[3]', 'detailgrading', 'eq', 3);
        $mform->addElement('group', 'tg3', get_string("tgmeeting", 'connectmeeting') . ' 3', $formgroup, array(' '), false);
        $mform->addHelpButton('tg3', "tgmeeting", 'connectmeeting');
        //$mform->setAdvanced('tg3', 'grade');
        $mform->addElement('html', '</div>');

        
        $vpoptions = array();
        $vpoptions[0] = get_string('none');
        for ($i = 100; $i >= 1; $i -= 1) {
            $vpoptions[$i] = $i . '%';
        }

        $mform->addElement('html', '<div id="vp-gradings">');
        $formgroup = array();
        $formgroup[] =& $mform->createElement('select', 'vpthreshold[1]', '', $vpoptions);
        $mform->setDefault('vpthreshold[1]', 0);
        $mform->disabledIf('vpthreshold[1]', 'detailgrading', 'ne', 3);
        $formgroup[] =& $mform->createElement('select', 'vpgrade[1]', '', $goptions);
        $mform->setDefault('vpgrade[1]', 0);
        $mform->disabledIf('vpgrade[1]', 'detailgrading', 'ne', 3);
        $mform->addElement('group', 'tg1vp', get_string("tgmeetingvp", 'connectmeeting') . ' 1', $formgroup, array(' '), false);
        $mform->addHelpButton('tg1vp', "tgmeetingvp", 'connectmeeting');
        //$mform->setAdvanced('tg1', 'grade');

        $formgroup = array();
        $formgroup[] =& $mform->createElement('select', 'vpthreshold[2]', '', $vpoptions);
        $mform->setDefault('vpthreshold[2]', 0);
        $mform->disabledIf('vpthreshold[2]', 'detailgrading', 'ne', 3);
        $formgroup[] =& $mform->createElement('select', 'vpgrade[2]', '', $goptions);
        $mform->setDefault('vpgrade[2]', 0);
        $mform->disabledIf('vpgrade[2]', 'detailgrading', 'ne', 3);
        $mform->addElement('group', 'tg2vp', get_string("tgmeetingvp", 'connectmeeting') . ' 2', $formgroup, array(' '), false);
        $mform->addHelpButton('tg2vp', "tgmeetingvp", 'connectmeeting');
        //$mform->setAdvanced('tg2', 'grade');

        $formgroup = array();
        $formgroup[] =& $mform->createElement('select', 'vpthreshold[3]', '', $vpoptions);
        $mform->setDefault('vpthreshold[3]', 0);
        $mform->disabledIf('vpthreshold[3]', 'detailgrading', 'ne', 3);
        $formgroup[] =& $mform->createElement('select', 'vpgrade[3]', '', $goptions);
        $mform->setDefault('vpgrade[3]', 0);
        $mform->disabledIf('vpgrade[3]', 'detailgrading', 'ne', 3);
        $mform->addElement('group', 'tg3vp', get_string("tgmeetingvp", 'connectmeeting') . ' 3', $formgroup, array(' '), false);
        $mform->addHelpButton('tg3vp', "tgmeetingvp", 'connectmeeting');
        //$mform->setAdvanced('tg3', 'grade');
        $mform->addElement('html', '</div>');
//-------------------------------------------------------------------------------
       
            $mform->addElement('header', 'comphdr', get_string('comphdr', 'connectmeeting'));

            $mform->addElement('select', 'compdelay', get_string('compdelay', 'connectmeeting'), $doptions);
            $pluginconfig = get_config("mod_connectmeeting", "compdelay");
            $default = !empty( $pluginconfig ) ? $pluginconfig : 3600;
            $mform->setDefault('compdelay', $default);
            $mform->addHelpButton('compdelay', "compdelay", 'connectmeeting');

            $mform->addElement('text', 'email', get_string('email', 'connectmeeting'), 'size="50"');
            $mform->setType('email', (!empty($CFG->formatstringstriptags)) ? PARAM_TEXT : PARAM_CLEAN);

            $uoptions = array();
            $uoptions[0] = get_string('connect_never', 'connectmeeting');
            $uoptions[1] = get_string('all', 'connectmeeting');
            $uoptions[2] = get_string('attended', 'connectmeeting');
            $uoptions[3] = get_string('absent', 'connectmeeting');
            $mform->addElement('select', 'unenrol', get_string('unenrol', 'connectmeeting'), $uoptions);
            $mform->addHelpButton('unenrol', 'unenrol', 'connectmeeting');

            $coptions = array();
            $dbman = $DB->get_manager();
            if ($dbman->table_exists('certificate')) {
                $coptions = $DB->get_records_menu('certificate', array('course' => $COURSE->id), 'name', 'id,name');
            }
            $coptions = array(0 => get_string('none', 'connectmeeting')) + $coptions;
            $mform->addElement('select', 'autocert', get_string('autocert', 'connectmeeting'), $coptions);
        

//-------------------------------------------------------------------------------
        if (isset($CFG->local_reminders) AND $CFG->local_reminders) {
            require_once($CFG->dirroot . '/local/reminders/lib.php');
            reminders_form($mform, true);
        }

//-------------------------------------------------------------------------------
        


//-------------------------------------------------------------------------------
        $this->standard_coursemodule_elements();

//-------------------------------------------------------------------------------
        // buttons
        $this->add_action_buttons();
    }

    function definition_after_data() {
        global $CFG, $COURSE, $DB, $USER, $AC;        

        //if (empty($USER->aclogin)) $USER->aclogin = _connect_new_login($USER);

        $mform =& $this->_form;
        // this hack is needed for different settings of each subtype
        if (!empty($this->_instance)) {
            $connectmeeting = $DB->get_record('connectmeeting', array('id' => $this->_instance));
            
            $eventid = $connectmeeting->eventid;
        } 

        
            $urlgrp = $mform->getElementValue('urlgrp');
            $url = !empty($urlgrp['url']) ? $urlgrp['url'] : '';
            $name = $mform->getElementValue('name');
            if (!empty($url)) {
                if (is_numeric(substr($url, 0, 1))) $url = 'INVALID';
                if ($url != clean_text($url, PARAM_ALPHAEXT)) $url = 'INVALID';
                if (strpos($url, '/') OR strpos($url, ' ')) $url = 'INVALID';
            }

            if (!empty($url) AND $url != 'INVALID') {
                if (!empty($this->_instance)) {
                    $info = connect_get_sco($this->_instance, 0, 'meeting');
                } else {
                    $info = connect_get_sco_by_url($url);
                }

                if (isset($info->type)) {

                    $mform->setDefault('urlgrp', $url);

                    //Make URL field uneditable if editing existing activity
                    if (!empty($_REQUEST['update'])) {
                        $element =& $mform->createElement('text', 'url', get_string('url', 'connectmeeting'));
                        $mform->setType('url', (!empty($CFG->formatstringstriptags)) ? PARAM_TEXT : PARAM_CLEAN);
                        $mform->insertElementBefore($element, 'urlgrp');
                        $mform->hardFreeze('url');
                        $mform->setDefault('url', $url);

                        $mform->removeElement('urlgrp', true);
                    }

                    if ((isset($CFG->connect_update) && $CFG->connect_update) || empty($_REQUEST['update'])) {
                        $mform->setDefault('name', $info->name);
                        $mform->setDefault('introeditor', array('text' => $info->desc));
                    }
                    if ($info->type == 'meeting') {
                        if (isset($CFG->connect_updatedts) AND $CFG->connect_updatedts) {
                            $mform->setDefault('start', $info->start);
                            $mform->setDefault('duration', $info->end - $info->start);
                        }

                        if (isset($eventid) AND $eventid AND $event = $DB->get_record('event', array('id' => $eventid))) {
                            $mform->setDefault('reminders', 1);
                            reminders_get($event->id, $mform);
                        }
                        
                        if( isset( $connectmeeting->start ) ){
                            $mform->setDefault('start', $connectmeeting->start);
                            $mform->setDefault('duration', $connectmeeting->duration);
                        }elseif (isset($CFG->local_reminders) AND $CFG->local_reminders) {
                            if ( isset( $event ) && $event ) {
                                if (!isset($CFG->connect_updatedts) OR !$CFG->connect_updatedts) {
                                    $mform->setDefault('start', $event->timestart);
                                    $mform->setDefault('duration', $event->timeduration);
                                }
                            }
                        }
                    }
                } else {
                    //Make URL field uneditable if editing existing activity
                    if (!empty($_REQUEST['update'])) {
//                         $element =& $mform->createElement('text', 'urltmp', get_string('url', 'connect'));
//                         $mform->insertElementBefore($element, 'urlgrp');
//                         $mform->hardFreeze('urltmp');
//                         $mform->setDefault('urltmp', $url);
//                         $mform->removeElement('urlgrp');
//                         $mform->addElement('hidden', 'url');
//                         $mform->setDefault('url', $url);
                    }

                    $last_ac_code = !is_string($info) || $info == 'no-data' ? $info : 'no-access';

                    if (isset($CFG->connect_update) AND $CFG->connect_update) {
//                        $element =& $mform->createElement( 'text', 'info', '');
//                        $mform->insertElementBefore( $element, 'name' );
//                        $mform->setDefault( 'info', get_string('notfound','connect').': '.get_string('typelist'.$type,'connect').get_string('whensaved','connect') );
//                        $mform->hardFreeze('info');

                        if ($last_ac_code == 'no-data') {
                            $element =& $mform->createElement('html', '<div class="fitem"><div class="felement fstatic alert alert-info">'
                                . get_string('notfound', 'connectmeeting') . ': '
                                . get_string('typelistmeeting', 'connectmeeting')
                                . get_string('whensaved', 'connectmeeting')
                                . '</div></div>', '');
                        } else {
                            $mform->setDefault('url', '');
                            $element =& $mform->createElement('html', '<div class="fitem "><div class="felement fstatic error alert alert-danger">'
                                . get_string('no-access', 'connectmeeting') . ': <b>' . $url . '</b>'
                                . '</div></div>', '');
                        }

                        $mform->insertElementBefore($element, 'name');

                        // Name 
                        /*if (!empty($name)) {
                            if (connect_find_meeting($name)) {
                                $mform->setDefault('name', '');
                                $element =& $mform->createElement('html', '<div class="fitem"><div class="felement fstatic alert alert-info">'
                                    . get_string('meetingnamefound', 'connect', $name)
                                    . '</div></div>', '');
                                $mform->insertElementBefore($element, 'start');
                            }


                        }*/


                    }
                } 
            } elseif ($url == 'INVALID') {
                $mform->setDefault('url', 'INVALID');
            }

            //if ($type == 'meeting' && (empty($url) || !isset($info) || (isset($info) && $info == 'no-data'))) {
            if ((empty($_REQUEST['update']))) {
                // Template
                $tploptions = connect_get_templates();
                $element =& $mform->createElement('select', 'template', get_string('template', 'connectmeeting'), $tploptions);
                if (isset($CFG->connect_icondisplay) AND $CFG->connect_icondisplay) {
                    $mform->insertElementBefore($element, 'disphdr');
                } else {
                    $mform->insertElementBefore($element, 'grading');
                }
                if (isset($CFG->connect_template)) {
                    $mform->setDefault('template', $CFG->connect_template);
                }

                // Telephony Info
                if (empty($this->_instance)) {
//                     $teloptions = array('none' => get_string('none'), 'other' => get_string('other'));
                	$teloptions = array('none' => get_string('none'));
                	$moreteloptions = connect_telephony_profiles($USER->id);
                    if( $moreteloptions ) $teloptions += $moreteloptions;
                    $element =& $mform->createElement('select', 'telephony', get_string('telephony', 'connectmeeting'), $teloptions);
                    $mform->insertElementBefore($element, 'template');
                    //$mform->disabledIf('template', 'url', 'ne', '');
                    //$mform->disabledIf('telephony', 'url', 'ne', '');
//                     $element =& $mform->createElement('text', 'conference', get_string('conference', 'connect'), array('size' => '15'));
//                     $mform->insertElementBefore($element, 'template');
//                     $element =& $mform->createElement('text', 'moderator', get_string('moderator', 'connect'), array('size' => '15'));
//                     $mform->insertElementBefore($element, 'template');
//                     $element =& $mform->createElement('text', 'participant', get_string('participant', 'connect'), array('size' => '15'));
//                     $mform->insertElementBefore($element, 'template');
//                     $mform->disabledIf('conference', 'telephony', 'ne', 'other');
//                     $mform->disabledIf('moderator', 'telephony', 'ne', 'other');
//                     $mform->disabledIf('participant', 'telephony', 'ne', 'other');
                }
            }elseif( !empty($_REQUEST['update']) ){
                 $tploptions = connect_get_templates();
                $element =& $mform->createElement('select', 'template_start', get_string('template', 'connectmeeting'), $tploptions);
                if (isset($CFG->connect_icondisplay) AND $CFG->connect_icondisplay) {
                    $mform->insertElementBefore($element, 'disphdr');
                } else {
                    $mform->insertElementBefore($element, 'grading');
                }
                
                $teloptions = array('none' => get_string('none'));
                $moreteloptions = connect_telephony_profiles($USER->id);
                if( $moreteloptions ) $teloptions += $moreteloptions;
                $element =& $mform->createElement('select', 'telephony_start', get_string('telephony', 'connectmeeting'), $teloptions);
                $mform->insertElementBefore($element, 'template_start');

                $mform->disabledIf('telephony_start', 'template_start', 'ne', '99frank');
                $mform->disabledIf('template_start', 'telephony_start', 'ne', '99frank');//hack, should never equel this, always be disabled
            }

            if (isset($CFG->connect_icondisplay) AND $CFG->connect_icondisplay) {
                if (!empty($this->_instance)) $disp = $DB->get_field('connectmeeting', 'display', array('id' => $this->_instance));
                if (!empty($disp)) {

                    preg_match('/data-options="([^"]+)"/', $disp, $matches);
                    if( isset( $matches[1] ) ){
                        $options = explode('~', $matches[1] );
                        $tags = explode('-', strtolower($options[0]));
                        $size = empty($tags[0]) ? 'large' : (($tags[0] == 'large' OR $tags[0] == 'medium' OR $tags[0] == 'small' OR $tags[0] == 'block') ? $tags[0] : 'large');
                        $silent = isset($tags[1]) ? strpos($tags[1], 's') !== false : false;
                        $norec = isset($tags[1]) ? strpos($tags[1], 'a') !== false : false;
                        $phone = isset($tags[1]) ? strpos($tags[1], 'p') !== false : false;
                        $guest = isset($tags[1]) ? strpos($tags[1], 'g') !== false : false;
                        $mouse = isset($tags[1]) ? strpos($tags[1], 'm') !== false : false;
                        $pos = isset($tags[1]) ? strpos($tags[1], 'l') !== false ? 'l' : 'c' : 'l';
    
                        $xhtml = isset($options[3]) ? $options[3] : '';
                        $force = isset($options[4]) ? basename($options[4]) : '';
                        $size = empty($force) ? $size : 'custom';

                        $mform->setDefault('iconsize', $size);
                        $mform->setDefault('iconpos', $pos);
                        $mform->setDefault('iconsilent', $silent);
                        $mform->setDefault('iconphone', $phone);
                        //$mform->setDefault('iconmouse', $mouse);
                        $mform->setDefault('iconguests', $guest);
                        $mform->setDefault('iconnorec', $norec);
                        $xhtml = preg_replace( '/%%quote%%/', '"', $xhtml );
                        $mform->setDefault('extrahtml', $xhtml);

                    }

                    $draftitemid = file_get_submitted_draft_itemid('forceicon');
                    file_prepare_draft_area($draftitemid, $this->context->id, 'mod_connectmeeting', 'content', 0, $this->_fmoptions);
                    $mform->setDefault('forceicon_filemanager', $draftitemid);
                }
            }

        
        parent::definition_after_data();
    }

    function data_preprocessing(&$data) {
        global $DB;

        parent::data_preprocessing($data);

        if (isset($data['id']) && is_numeric($data['id'])) {
            if ($gradings = $DB->get_records('connectmeeting_grading', array('connectmeetingid' => $data['id']), 'threshold desc')) {
                $key = 1;
                foreach ($gradings as $grading) {
                    if ($data['detailgrading'] == 3) {
                        $data['vpthreshold[' . $key . ']'] = $grading->threshold;
                        $data['vpgrade[' . $key . ']'] = $grading->grade;
                    } else {
                        $data['threshold[' . $key . ']'] = $grading->threshold;
                        $data['grade[' . $key . ']'] = $grading->grade;
                    }
                    $key++;
                }
            }
        }
    }

    function validation($data, $files) {
        $errors = parent::validation($data, $files);

//        if ( empty( $data->url ) AND empty( $data->newurl ) ) {
//            $errors['urlgrp'] = get_string('required');
//        }

        if (count($errors) == 0) {
            return true;
        } else {
            return $errors;
        }
    }
}
