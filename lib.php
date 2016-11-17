<?php // $Id: lib.php
/**
 * Library of functions and constants for module connect
 *
 * @author  Gary Menezes
 * @version $Id: lib.php
 * @package connect
 **/

require_once($CFG->dirroot . '/mod/connectmeeting/connectlib.php');
require_once($CFG->dirroot . '/lib/completionlib.php');

global $PAGE;
//$PAGE->requires->js('/mod/connectmeeting/js/mod_connectmeeting_coursepage.js');

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $instance An object from the form in mod.html
 * @return int The id of the newly inserted connect record
 **/
function connectmeeting_add_instance($connectmeeting) {
    global $CFG, $USER, $COURSE, $DB;
    require_once($CFG->libdir . '/gdlib.php');

    $cmid = $connectmeeting->coursemodule;

    $connectmeeting->timemodified = time();
    // complete url for video to check
    
    if (empty($connectmeeting->url) and !empty($connectmeeting->newurl)) {
        $connectmeeting->url = $connectmeeting->newurl;
    }
    
    
    	$connectmeeting->url = preg_replace( '/\//', '', $connectmeeting->url ); // if someone tries to save with slashes, get ride of it
    

    $connectmeeting->display = '';
    $connectmeeting->complete = 0;
    
    if( isset( $connectmeeting->addinroles ) && is_array( $connectmeeting->addinroles ) ){
    	$connectmeeting->addinroles = implode( ',', $connectmeeting->addinroles );
    }
    
    if( !isset( $connectmeeting->displayoncourse ) ) $connectmeeting->displayoncourse = 0;

    //insert instance
    if ($connectmeeting->id = $DB->insert_record("connectmeeting", $connectmeeting)) {
        // Update display to include ID and save custom file if needed
        $connectmeeting = connectmeeting_set_forceicon($connectmeeting);
        $display = connectmeeting_translate_display($connectmeeting);
        if ($display != $connectmeeting->display) {
            $DB->set_field('connectmeeting', 'display', $display, array('id' => $connectmeeting->id));
            $connectmeeting->display = $display;
        }

        // Save the grading
        $DB->delete_records('connectmeeting_grading', array('connectmeetingid' => $connectmeeting->id));
        if (isset($connectmeeting->detailgrading) && $connectmeeting->detailgrading) {
            for ($i = 1; $i < 4; $i++) {

                $grading = new stdClass;
                $grading->connectmeetingid = $connectmeeting->id;
                if ($connectmeeting->detailgrading == 3) {
                    $grading->threshold = $connectmeeting->vpthreshold[$i];
                    $grading->grade = $connectmeeting->vpgrade[$i];
                } else {
                    $grading->threshold = $connectmeeting->threshold[$i];
                    $grading->grade = $connectmeeting->grade[$i];
                }
                if (!$DB->insert_record('connectmeeting_grading', $grading, false)) {
                    return "Could not save connect grading.";
                }
            }
        }

        if (isset($connectmeeting->reminders) && $connectmeeting->reminders) {
            $event = new stdClass();
            $event->name = $connectmeeting->name;
            $event->description = isset($connectmeeting->intro) ? $connectmeeting->intro : '';
            $event->format = 1;
            $event->courseid = $connectmeeting->course;
            $event->modulename = (empty($CFG->connect_courseevents) OR !$CFG->connect_courseevents) ? 'connectmeeting' : '';
            $event->instance = (empty($CFG->connect_courseevents) OR !$CFG->connect_courseevents) ? $connectmeeting->id : 0;
            $event->eventtype = 'course';
            $event->timestart = $connectmeeting->start;
            $event->timeduration = $connectmeeting->duration;
            $event->uuid = '';
            $event->visible = 1;
            $event->acurl = $connectmeeting->url;
            $event->timemodified = time();

            if ($event->id = $DB->insert_record('event', $event)) {
                $DB->set_field('connectmeeting', 'eventid', $event->id, array('id' => $connectmeeting->id));
                $connectmeeting->eventid = $event->id;
                if (isset($CFG->local_reminders) AND $CFG->local_reminders) {
                    require_once($CFG->dirroot . '/local/reminders/lib.php');
                    reminders_update($event->id, $connectmeeting);
                }
            }
        }
        // Create meeting on connect
            if (isset($CFG->connect_update) AND $CFG->connect_update ) {
                $meeting = new stdClass;
                $meeting->host = "$USER->id";
                $meeting->name = $connectmeeting->name;
                if (!empty($connectmeeting->start)) $meeting->start = $connectmeeting->start;
                if (!empty($connectmeeting->duration)) $meeting->end = $connectmeeting->start + $connectmeeting->duration;
                if (!empty($connectmeeting->url)) $meeting->url = $connectmeeting->url;
                if (!empty($connectmeeting->intro)) $meeting->description = $connectmeeting->intro;
                if (!empty($connectmeeting->telephony)) $meeting->telephony = $connectmeeting->telephony;
                if (!empty($connectmeeting->template)) $meeting->template = $connectmeeting->template;
                elseif (isset($CFG->connect_template)) $meeting->template = $CFG->connect_template;

                $context = context_course::instance($connectmeeting->course);
                if ($hosts = get_users_by_capability($context, 'mod/connectmeeting:host')) {
                    foreach ($hosts as $host) $meeting->host .= ',' . $host->id;
                }
                if ($presenters = get_users_by_capability($context, 'mod/connectmeeting:presenter')) {
                    foreach ($presenters as $presenter) {
                        if (empty($meeting->presenter)) $meeting->presenter = $presenter->id;
                        else $meeting->presenter .= ',' . $presenter->id;
                    }
                }

                if (!empty($COURSE)) $course = $COURSE;
                else $course = $DB->get_record('course', 'id', $connectmeeting->course);
                $meeting->view = $course->id;
                $meeting->type = 'meeting';
                $created_meeting = connect_create_meeting($connectmeeting->id, 'meeting', $meeting);
                if (!$created_meeting) {
                    return false;
                }
            } elseif (!empty($connectmeeting->url)) {
            	if (!empty($COURSE)) $course = $COURSE;
            	else $course = $DB->get_record('course', 'id', $connectmeeting->course);
            	$result = connect_use_sco($connectmeeting->id, $connectmeeting->url, 'meeting', $course->id);
            	if (!$result) {
            		return false;
            	}
            }

        // telephony save
        if( isset( $connectmeeting->telephony ) && $connectmeeting->telephony ){
            $DB->set_field('connectmeeting', 'telephony_start', $connectmeeting->telephony, array('id' => $connectmeeting->id));
        }
        if( isset( $connectmeeting->template ) && $connectmeeting->template ){
            $DB->set_field('connectmeeting', 'template_start', $connectmeeting->template, array('id' => $connectmeeting->id));
        }
    }

    //create grade item for locking
    $entry = new stdClass;
    $entry->grade = 0;
    $entry->userid = $USER->id;
    connectmeeting_gradebook_update($connectmeeting, $entry);

    connectmeeting_update_from_adobe( $connectmeeting );

    return $connectmeeting->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod.html) this function
 * will update an existing instance with new data.
 *
 * @param object $instance An object from the form in mod.html
 * @return boolean Success/Fail
 **/
function connectmeeting_update_instance($connectmeeting) {
    global $CFG, $DB;

    $connectmeeting->timemodified = time();
    

    if (!isset($connectmeeting->detailgrading)) {
        $connectmeeting->detailgrading = 0;
    }

    if (isset($connectmeeting->iconsize) && $connectmeeting->iconsize == 'custom') {
        $connectmeeting = connectmeeting_set_forceicon($connectmeeting);
    } else {
        $connectmeeting->forceicon = '';
    }
    $connectmeeting->display = connectmeeting_translate_display($connectmeeting);
    $connectmeeting->complete = 0;
    
    
    	$connectmeeting->url = preg_replace( '/\//', '', $connectmeeting->url ); // if someone tries to save with slashes, get ride of it
    
    
    if( isset( $connectmeeting->addinroles ) && is_array( $connectmeeting->addinroles ) ){
    	$connectmeeting->addinroles = implode( ',', $connectmeeting->addinroles );
    }
    
    if( !isset( $connectmeeting->displayoncourse ) ) $connectmeeting->displayoncourse = 0;
    
    //update instance
    if (!$DB->update_record("connectmeeting", $connectmeeting)) {
        return false;
    }

    // Save the grading
    $DB->delete_records('connectmeeting_grading', array('connectmeetingid' => $connectmeeting->id));
    if (isset($connectmeeting->detailgrading) && $connectmeeting->detailgrading) {
        for ($i = 1; $i < 4; $i++) {
            $grading = new stdClass;
            $grading->connectmeetingid = $connectmeeting->id;
            if ($connectmeeting->detailgrading == 3) {
                $grading->threshold = $connectmeeting->vpthreshold[$i];
                $grading->grade = $connectmeeting->vpgrade[$i];
            } else {
                $grading->threshold = $connectmeeting->threshold[$i];
                $grading->grade = $connectmeeting->grade[$i];
            }
            $grading->timemodified = time();
            if (!$DB->insert_record('connectmeeting_grading', $grading, false)) {
                return false;
            }
        }
    }

    if (isset($connectmeeting->reminders) && $connectmeeting->reminders) {
        if (isset($connectmeeting->eventid) AND $connectmeeting->eventid){
        	$event = $DB->get_record('event', array('id' => $connectmeeting->eventid));
        }else{
        	$event = new stdClass();
        }

        $event->name = $connectmeeting->name;
        $event->description = isset($connectmeeting->intro) ? $connectmeeting->intro : '';
        $event->format = 1;
        $event->courseid = $connectmeeting->course;
        $event->modulename = (empty($CFG->connect_courseevents) OR !$CFG->connect_courseevents) ? 'connectmeeting' : '';
        $event->instance = (empty($CFG->connect_courseevents) OR !$CFG->connect_courseevents) ? $connectmeeting->id : 0;
        $event->timestart = $connectmeeting->start;
        $event->timeduration = $connectmeeting->duration;
        $event->visible = 1;
        $event->uuid = '';
        $event->sequence = 1;
        $event->acurl = $connectmeeting->url;
        $event->timemodified = time();

        if (isset($event->id) AND $event->id) $DB->update_record('event', $event);
        else $event->id = $DB->insert_record('event', $event);

        if (isset($event->id) AND $event->id) {
            if ($connectmeeting->eventid != $event->id){
                $DB->set_field('connectmeeting', 'eventid', $event->id, array('id' => $connectmeeting->id));
                $connectmeeting->eventid = $event->id;
            }

            if (isset($CFG->local_reminders) AND $CFG->local_reminders) {
                $DB->delete_records('reminders', array('event' => $event->id));
                require_once($CFG->dirroot . '/local/reminders/lib.php');
                reminders_update($event->id, $connectmeeting);
            }
        }
    } elseif (isset($connectmeeting->eventid) AND $connectmeeting->eventid) {
        $DB->delete_records('reminders', array('event' => $connectmeeting->eventid));
        $DB->delete_records('event', array('id' => $connectmeeting->eventid));
    }

    // Update connect
    if (isset($CFG->connect_update) AND $CFG->connect_update AND !empty($connectmeeting->url)) {
        $date_begin = 0;
        $date_end = 0;
        if (isset($CFG->connect_updatedts) AND $CFG->connect_updatedts && isset( $connectmeeting->start ) && isset( $connectmeeting->duration )) {
            $date_begin = $connectmeeting->start;
            $date_end = $connectmeeting->start + $connectmeeting->duration;
        }
        connect_update_sco($connectmeeting->id, $connectmeeting->name, $connectmeeting->intro, $date_begin, $date_end, 'meeting');
    }

    //create grade item for locking
    global $USER;
    $entry = new stdClass;
    $entry->grade = 0; 
    $entry->userid = $USER->id;
    connectmeeting_gradebook_update($connectmeeting, $entry);

    connectmeeting_update_from_adobe( $connectmeeting );

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function connectmeeting_delete_instance($id) {
    global $DB;

    if (!$connectmeeting = $DB->get_record('connectmeeting', array('id' => $id))) {
        return false;
    }

    // Delete area files (must be done before deleting the instance)
    $cm = get_coursemodule_from_instance('connectmeeting', $id);
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $fs->delete_area_files($context->id, 'mod_connectmeeting');

    // Delete dependent records
    if (isset($connectmeeting->eventid) AND $connectmeeting->eventid) $DB->delete_records('reminders', array('event' => $connectmeeting->eventid));
    if (isset($connectmeeting->eventid) AND $connectmeeting->eventid) $DB->delete_records('event', array('id' => $connectmeeting->eventid));

    // Delete connect records
    $DB->delete_records("connectmeeting_grading", array("connectmeetingid" => $id));
    $DB->delete_records("connectmeeting_entries", array("connectmeetingid" => $id));
    $DB->delete_records("connectmeeting_recurring", array("connectmeetingid" => $id));
    $DB->delete_records("connectmeeting", array('id' => $id));

    return true;
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 **/
function connectmeeting_user_outline($course, $user, $mod, $connectmeeting) {
    global $DB;

    if ($grade = $DB->get_record('connectmeeting_entries', array('userid' => $user->id, 'connectmeetingid' => $connectmeeting->id))) {

        $result = new stdClass;
        if ((float)$grade->grade) {
            $result->info = get_string('grade') . ':&nbsp;' . $grade->grade;
        }
        $result->time = $grade->timemodified;
        return $result;
    }
    return NULL;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 **/
function connectmeeting_user_complete($course, $user, $mod, $connectmeeting) {
    global $DB;

    if ($grade = $DB->get_record('connectmeeting_entries', array('userid' => $user->id, 'connectmeetingid' => $connectmeeting->id))) {
        echo get_string('grade') . ': ' . $grade->grade;
        echo ' - ' . userdate($grade->timemodified) . '<br />';
    } else {
        print_string('nogrades', 'connectmeeting');
    }

    return true;
}



/**
 * Runs each time cron runs.
 *  Updates meeting completion and recurring meetings.
 *  Gets and processes entries who's recheck time has elapsed.
 *
 * @return boolean
 **/
function connectmeeting_cron_task() {
    echo '+++++ connectmeeting_cron'."\n";
    global $CFG, $DB;
    $now = time();

    if ($connectmeetings = $DB->get_records_sql("SELECT * FROM {$CFG->prefix}connectmeeting WHERE start > 0 AND (start + compdelay) < $now AND complete = 0")) {
        foreach ($connectmeetings as $connectmeeting) {
            connectmeeting_complete_meeting($connectmeeting);
        }
    }

    
    return true;
}

function connectmeeting_grade_based_on_range( $userid, $connectmeetingid, $startdaterange, $enddaterange, $regrade ){
    if( function_exists( 'local_connect_grade_based_on_range' ) ){
        return local_connect_grade_based_on_range( $userid, $connectmeetingid, $startdaterange, $enddaterange, $regrade, 'connectmeeting' );
    }else{
        return false;
    }
}

function connectmeeting_complete_meeting($connectmeeting, $startdaterange = 0, $enddaterange = 0) {
    global $CFG, $DB;

    $regrade = $startdaterange ? 1 : 0; // if we are passed a date range, this is a regrade
    if( !$startdaterange ){
        $startdaterange = $connectmeeting->start;
        $enddaterange = $connectmeeting->start + $connectmeeting->compdelay + (60*60*2);
    }

    if ($connectmeeting->start > 0 AND ($connectmeeting->start + $connectmeeting->compdelay) < time() AND $connectmeeting->complete == 0) {
        $complete = true;
    } else {
        $complete = false;
    }

    $cm = get_coursemodule_from_instance('connectmeeting', $connectmeeting->id);
    if ($cm && connectmeeting_grade_meeting(0, '', $connectmeeting, $startdaterange, $enddaterange, $regrade)) {
        //echo "connectmeeting " . $connectmeeting->id . "\n";
        $context = context_course::instance($connectmeeting->course);
        $course = $DB->get_record('course', array('id' => $connectmeeting->course));
        if ($users = get_enrolled_users($context)) {
            //Certificate Setup
            if ($DB->get_record('modules', array('name' => 'certificate'))) {
                global $certificate; // To deal with bad code in certificate_issue;
                if ($connectmeeting->autocert AND $certificate = $DB->get_record('certificate', array('id' => $connectmeeting->autocert))) {
                    require_once($CFG->dirroot . '/mod/certificate/lib.php');
                    require_once($CFG->libdir . '/pdflib.php');
                    $cmcert = get_coursemodule_from_instance('certificate', $certificate->id);
                    $certctx = get_context_instance(CONTEXT_MODULE, $cmcert->id);
                }
            }

            //Loop through each user
            foreach ($users as $user) {
                //echo "user " . $user->id . "\n";
                // skip them if they have a grade outside the range
                if( !connectmeeting_grade_based_on_range( $user->id, $connectmeeting->id, $startdaterange, $enddaterange, $regrade ) ) continue;

                if ($grade = $DB->get_field('connectmeeting_entries', 'grade', array('connectmeetingid' => $connectmeeting->id, 'userid' => $user->id)) AND $grade == 100) {
                    // Mark Users Complete
                    if ($cmcomp = $DB->get_record('course_modules_completion', array('coursemoduleid' => $cm->id, 'userid' => $user->id))) {
                        $cmcomp->completionstate = 1;
                        $cmcomp->viewed = 1;
                        $cmcomp->timemodified = time();
                        $DB->update_record('course_modules_completion', $cmcomp);
                    } else {
                        $cmcomp = new stdClass;
                        $cmcomp->coursemoduleid = $cm->id;
                        $cmcomp->userid = $user->id;
                        $cmcomp->completionstate = 1;
                        $cmcomp->viewed = 1;
                        $cmcomp->timemodified = time();
                        $DB->insert_record('course_modules_completion', $cmcomp);
                    }

                    // Issue Certificates
                    if (!empty($certctx) AND !$DB->get_record('certificate_issues', array('certificateid' => $certificate->id, 'userid' => $user->id, 'notified' => 1))) {
                        global $USER, $pdf, $certificate;
                        $session_user = $USER;
                        //TODO: Remove the $USER cloning
                        $USER = clone($user);
                        if ($certrecord = certificate_get_issue($course, $user, $certificate, $cmcert)) {
                            //RT-1458 Certificates not being emailed / Activity completion not updating for certificates when issued from meeting completion.
                            //It is because we remove $certificate->savecert setting not to save pdf file in the file system
                            //if ($certificate->savecert) {

                                $studentname = '';
                                $student = $user;
                                $certrecord->studentname = $student->firstname . ' ' . $student->lastname;

                                $classname = '';
                                $certrecord->classname = $course->fullname;

                                require($CFG->dirroot . '/mod/certificate/type/' . $certificate->certificatetype . '/certificate.php');
                                $file_contents = $pdf->Output('', 'S');
                                $filename = clean_filename($certificate->name . '.pdf');
                                certificate_save_pdf($file_contents, $certrecord->id, $filename, $certctx->id, $user);

                                if ($certificate->delivery == 2) {
                                    certificate_email_student($course, $certificate, $certrecord, $certctx, $user, $file_contents);
                                }
                            
                                // Mark certificate as viewed
                                $cm = get_coursemodule_from_instance('certificate', $certificate->id, $certificate->course);
                                $completion = new completion_info($course);
                                $completion->set_module_viewed($cm, $user->id);
                            //}
                        }
                        $USER = $session_user;
                    }
                } else $grade = 0;

                // Unenrol All(1), Attended(2) or Absent(3)
                if ( !$regrade && ( ($grade == 100 AND $connectmeeting->unenrol == 2) OR ($complete AND ($connectmeeting->unenrol == 1 OR ($grade < 100 AND $connectmeeting->unenrol == 3))))) {
                    if ($enrols = $DB->get_records_sql("SELECT e.* FROM {$CFG->prefix}user_enrolments u, {$CFG->prefix}enrol e WHERE u.enrolid = e.id AND u.userid = {$user->id} AND e.courseid = {$connectmeeting->course}")) {
                        foreach ($enrols as $enrol) {
                            $plugin = enrol_get_plugin($enrol->enrol);
                            $plugin->unenrol_user($enrol, $user->id);
                        }
                    }
                    role_unassign($CFG->studentrole, $user->id, $context->id);
                }
            }
        }

        // Attendance Report
        if ( !$regrade && $complete AND !empty($connectmeeting->email)) {
            require_once($CFG->dirroot . '/filter/connect/lib.php');
            if (!$to = $DB->get_record('user', array('email' => $connectmeeting->email))) {
                $to = new stdClass;
                $to->firstname = 'Attendance';
                $to->lastname = 'Report';
                $to->email = $connectmeeting->email;
                $to->mailformat = 1;
                $to->maildisplay = true;
            }
            $subj = 'Attendance Report for ' . $connectmeeting->url;
            $body = connect_attendance_output($connectmeeting->url);
            $text = html_to_text($body);
            email_to_user($to, 'LMS Admin', $subj, $text, $body);
        }

        if (!$regrade && $complete) {
            // Next instance or mark complete
            if ($instance = $DB->get_record_sql("SELECT * FROM {$CFG->prefix}connectmeeting_recurring WHERE connectmeetingid={$connectmeeting->id} AND record_used=0 ORDER BY start LIMIT 1")) {
                $newurl = false;
                if ($connectmeeting->url != $instance->url) $newurl = true;

                $connectmeeting->start = $instance->start;
                $connectmeeting->display = str_replace($connectmeeting->url, $instance->url, $connectmeeting->display);
                $connectmeeting->url = $instance->url;
                $connectmeeting->email = $instance->email;
                $connectmeeting->eventid = $instance->eventid;
                $connectmeeting->unenrol = $instance->unenrol;
                $connectmeeting->compdelay = $instance->compdelay;
                $connectmeeting->autocert = $instance->autocert;
                $connectmeeting->timemodified = time();

                // Update Adobe
                $date_begin = 0;
                $date_end = 0;
                if (isset($CFG->connect_updatedts) AND $CFG->connect_updatedts) {
                    $date_begin = $connectmeeting->start;
                    $date_end = $connectmeeting->start + $instance->duration;
                }
                connect_update_sco($connectmeeting->id, $connectmeeting->name, $connectmeeting->intro, $date_begin, $date_end, 'meeting');

                if (isset($newurl) AND $newurl) connect_add_access($connectmeeting->id, $course->id, 'group', 'view', false, 'meeting');

                // Update Grouping
                if (isset($instance->groupingid) AND $instance->groupingid AND $cm) {
                    $cm->groupingid = $instance->groupingid;
                    $DB->update_record('course_modules', $cm);
                }

                $instance->record_used = 1;
                $DB->update_record('connectmeeting_recurring', $instance);
            } else $connectmeeting->complete = 1;

            rebuild_course_cache($connectmeeting->course);
            $DB->update_record('connectmeeting', $connectmeeting);
        } 
        connectmeeting_update_from_adobe( $connectmeeting );
    }

    return;
}

function connectmeeting_process_options(&$connectmeeting) {
    return true;
}

function connectmeeting_install() {
    return true;
}

function connectmeeting_get_view_actions() {
    return array('launch', 'view all');
}

function connectmeeting_get_post_actions() {
    return array('');
}

function connectmeeting_supports($feature) {
    switch ($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return false;

        default:
            return null;
    }
}

function connectmeeting_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;

    return $DB->record_exists('connectmeeting_entries', array('connectmeetingid' => $cm->instance, 'userid' => $userid));
}

function connectmeeting_cm_info_dynamic($mod) {
    global $DB, $USER;

    if (!$mod->available) return;

    $connectmeeting = $DB->get_record('connectmeeting', array('id' => $mod->instance));
    if (!empty($connectmeeting->display) && $connectmeeting->displayoncourse) {
        
            $mod->set_content( connectmeeting_create_display( $connectmeeting ) );
        
        // If set_no_view_link is TRUE - it's not showing on Activity Report (https://app.liquidplanner.com/space/73723/projects/show/9961959)
        if( method_exists( $mod, 'rt_set_no_view_link' ) ){
            $mod->rt_set_no_view_link();
        }
    }
    return;
}

function connectmeeting_cm_info_view($mod) {
    global $CFG, $OUTPUT, $DB;

    
    
        $str = '<span class="commands"><a class="editing_recurring" href="' . $CFG->wwwroot . '/mod/connectmeeting/recurring.php?id=' . $mod->instance . '" title="' . get_string('recurring', 'connectmeeting') . '"><img src="' . $OUTPUT->pix_url('i/calendar') . '" class="icon" alt="' . get_string('recurring', 'connectmeeting') . '" /></a></span>';
        $mod->set_after_edit_icons($str);
   
    return;
}

//////////////////////////////////////////////////////////////////////////////////////
/// Any other connect functions go here.  Each of them must have a name that
/// starts with connect_

/**
 * Called from /filters/connect/launch.php each time connect is launched.
 * Works out if it is an activity, and if so, updates the grade or sets up cron to.
 *
 * @param string $acurl The unique connect url for the resource
 * @param boolean $fullupdate Whethr all information should be updated even if max grade reached
 **/
function connectmeeting_launch($acurl, $courseid = 1, $regrade = false, $cm = 0) {
    global $CFG, $USER, $DB, $PAGE;

    if (!$connectmeeting = $DB->get_record('connectmeeting', array('url' => $acurl, 'course' => $courseid), '*', IGNORE_MULTIPLE)) {
        return;
    }

    if (!$entry = $DB->get_record('connectmeeting_entries', array('userid' => $USER->id, 'connectmeetingid' => $connectmeeting->id))) {
        $entry = new stdClass;
        $entry->connectmeetingid = $connectmeeting->id;
        $entry->userid = $USER->id;
        $entry->type = $connectmeeting->type;
        $entry->views = 0;
    }

    if (!is_siteadmin() AND isset($CFG->connect_maxviews) AND $CFG->connect_maxviews >= 0 AND isset($connectmeeting->maxviews) AND $connectmeeting->maxviews > 0 AND $connectmeeting->maxviews <= $entry->views) {
        $PAGE->set_url('/');
        notice(get_string('overmaxviews', 'connectmeeting'), $CFG->wwwroot . '/course/view.php?id=' . $connectmeeting->course);
    }

    $entry->timemodified = time();
    $entry->views++;
    
    $oldgrade = isset( $entry->grade ) ? $entry->grade : 0;

    // Without detail grading, just set the grade to 100 and return
    if (!$connectmeeting->detailgrading) {
        $entry->grade = 100;
        connectmeeting_gradebook_update($connectmeeting, $entry);
    } elseif (!isset($entry->grade) OR $entry->grade < 100) {
        connectmeeting_grade_entry($USER->id, $connectmeeting, $entry);
    }
    
    

    $entry->rechecks = $entry->grade == 100 ? 0 : $connectmeeting->loops;
    $entry->rechecktime = $entry->grade == 100 ? 0 : time() + $connectmeeting->initdelay;

    if (!isset($entry->id)) {
        $DB->insert_record('connectmeeting_entries', $entry);
    } else {
        $DB->update_record('connectmeeting_entries', $entry);
    }

    if ($cm) {
        $course = $DB->get_record('course', array('id' => $courseid));
        //error_log('+++ $course' . json_encode($course));
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm)) {
            if ( $cm->completiongradeitemnumber == null and $cm->completionview == 1){
                $completion->set_module_viewed($cm);
            }
        }
    }

    //if ($regrade) return;

    if ($cm) {
    	$description = '';
    	$action = '';
    	

    			$scores = connect_sco_scores($connectmeeting->id, $USER->id, 'meeting');
    			$description = "Minutes: $scores->minutes";
    			$description.= ", Connect ID: $entry->connectmeetingid";
    			$action = 'connect_meeting';
    			
    	
        $event = \mod_connectmeeting\event\connectmeeting_launch::create(array(
            'objectid' => $connectmeeting->id,
            'other' => array('acurl' => $acurl, 'description' => "$action - $connectmeeting->name ( $acurl ) - $description")
        ));
        $event->trigger();
    }
}


/**
 * returns updated entry record based on grading
 * called from launch and cron
 *
 * @param char $url Custom URL of Adobe connect Resource
 * @param char $userid Login acp_login (Adobe connect Username)
 * @param object $connectmeeting Original connect record
 * @param object $entry Original entry record
 **/
function connectmeeting_grade_entry($userid, $connectmeeting, &$entry, $scores = null) {
    global $CFG, $DB;

    if (!$scores) $scores = connect_sco_scores($connectmeeting->id, $userid, 'meeting');
    //error_log('==== $scores: ' . json_encode($scores));
    
    if (isset($scores->minutes)) {        
        $entry->minutes = (int)$scores->minutes;
        $threshold = (int)$scores->minutes;
    } else $threshold = 0;

    if ($specs = $DB->get_field_sql("SELECT MAX(grade) AS grade FROM {$CFG->prefix}connectmeeting_grading WHERE connectmeetingid = {$connectmeeting->id} AND threshold <= $threshold AND threshold > 0")) {
        $grade = (int)$specs;
    } elseif ($specs = $DB->get_field_sql("SELECT MAX(grade) AS grade FROM {$CFG->prefix}connectmeeting_grading WHERE connectmeetingid = {$connectmeeting->id} AND threshold > 0")) {
        $grade = 0;
    } else $grade = (int)$threshold;

    if (!isset($entry->grade) OR $entry->grade < $grade) {
        $entry->grade = $grade;
        connectmeeting_gradebook_update($connectmeeting, $entry);
    }

    if ($grade == 100) {
        $entry->rechecks = 0;
        $entry->rechecktime = 0;
    }

    return true;
}

/**
 * Update gradebook
 *
 * @param object $entry connect instance
 */
function connectmeeting_gradebook_update($connectmeeting, $entry) {
    if( function_exists( 'local_connect_gradebook_update' ) ){
        return local_connect_gradebook_update( $connectmeeting, $entry, 'connectmeeting' );
    }else{
        return false;
    }
}

function connectmeeting_update_from_adobe( &$connectmeeting ){
    global $DB;

    $sco = connect_get_sco_by_url( $connectmeeting->url, 1 );
    if( $sco ){
        if(isset( $sco->name ))$connectmeeting->name = $sco->name;
        if(isset( $sco->desc ))$connectmeeting->intro = $sco->desc;
        if(isset( $sco->archive ))$connectmeeting->ac_archive = $sco->archive;
        if(isset($sco->type))$connectmeeting->ac_type = $sco->type;
        if(isset($sco->phone))$connectmeeting->ac_phone = $sco->phone;
        if(isset($sco->pphone))$connectmeeting->ac_pphone = $sco->pphone;
        if(isset($sco->id))$connectmeeting->ac_id=$sco->id;
        if(isset($sco->views))$connectmeeting->ac_views = $sco->views;
        $DB->update_record( 'connectmeeting', $connectmeeting );
    }
}

function connectmeeting_translate_display($connectmeeting, $forviewpage = 0) {
    global $CFG;

    
        if ( !$forviewpage && (empty($connectmeeting->url) OR empty($connectmeeting->iconsize) OR $connectmeeting->iconsize == 'none')) return ''; 
        $flags = '-';

        if (!empty($connectmeeting->iconpos) AND $connectmeeting->iconpos) $flags .= $connectmeeting->iconpos;
        if (!empty($connectmeeting->iconsilent) AND $connectmeeting->iconsilent) $flags .= 's';
        if (!empty($connectmeeting->iconphone) AND $connectmeeting->iconphone) $flags .= 'p';
        //if (!empty($connectmeeting->iconmouse) AND $connectmeeting->iconmouse) $flags .= 'm';
        if (!empty($connectmeeting->iconguests) AND $connectmeeting->iconguests) $flags .= 'g';
        if (!empty($connectmeeting->iconnorec) AND $connectmeeting->iconnorec) $flags .= 'a';

        $start = ''; //TODO - get start and end from Restrict Access area
        $end = ''; 
        $extrahtml = empty($connectmeeting->extrahtml) ? '' : $connectmeeting->extrahtml;

        if( !isset( $connectmeeting->iconsize ) )$connectmeeting->iconsize = 'large';
        $options = $connectmeeting->iconsize . $flags . '~' . $start . '~' . $end . '~' . $extrahtml . '~' . $connectmeeting->forceicon . '~' . $connectmeeting->id;

        $display = '<div class="connectmeeting_display_block" ';
        $display.= 'data-courseid="' . $connectmeeting->course . '" ';
        $display.= 'data-acurl="' . $connectmeeting->url . '" ';
        $display.= 'data-sco="' . json_encode(false) . '" ';
        $display.= 'data-options="' . preg_replace( '/"/', '%%quote%%', $options ) . '" ';
        $display.= 'data-frommymeetings="0" ';
        $display.= 'data-frommyrecordings="0" >'
            . '<div id="id_ajax_spin" class="rt-loading-image"></div>'
            . '</div>';
        
//        $display = '[[connect#' . $connectmeeting->url . '#' . $connectmeeting->iconsize . $flags . '#' . $start . '#' . $end . '#' . $extrahtml . '#' . $connectmeeting->forceicon . '#' . $connectmeeting->id . ']]';

        return $display;
    
}

function connectmeeting_create_display( $connectmeeting ){
    global $USER, $CFG, $PAGE, $DB, $OUTPUT;

    if( !$connectmeeting ){
        return '<div style="text-align:center;"><img src="' . $CFG->wwwroot
            . '/mod/connectmeeting/images/notfound.gif"/><br/>'
            . get_string('notfound', 'connectmeeting')
            . '</div>';
    }

    if( !$connectmeeting->ac_id ){ // no ac id, probably first load of this activity after upgrade, lets update
        connectmeeting_update_from_adobe( $connectmeeting );
        if( !$connectmeeting->ac_id ){// must no longer exist in AC
            return '<div style="text-align:center;"><img src="' . $CFG->wwwroot
            . '/mod/connectmeeting/images/notfound.gif"/><br/>'
            . get_string('notfound', 'connectmeeting')
            . '</div>';
        }
    }

    if( !$connectmeeting->display || preg_match( '/\[\[/', $connectmeeting->display ) ){
        $connectmeeting = connectmeeting_set_forceicon($connectmeeting);
        $connectmeeting->display = connectmeeting_translate_display( $connectmeeting, 1 );
        $DB->update_record( 'connectmeeting', $connectmeeting );
    }   
    preg_match('/data-options="([^"]+)"/', $connectmeeting->display, $matches);
    if( isset( $matches[1] ) ){
        $element = explode('~', $matches[1] );
    }

    $sizes = array(
        "medium" => "_md",
        "med" => "_md",
        "md" => "_md",
        "_md" => "_md",
        "small" => "_sm",
        "sml" => "_sm",
        "sm" => "_sm",
        "_sm" => "_sm",
        "block" => "_sm",
        "sidebar" => "_sm"
    );
    $types = array("meeting" => "meeting", "content" => "presentation");
    $breaks = array("_md" => "<br/>", "_sm" => "<br/>");

    $thisdir = $CFG->wwwroot . '/mod/connectmeeting';


    $iconsize = '';
    $iconalign = 'center';
    $silent = false;
    $telephony = true;
    $mouseovers = true;
    $allowguests = false;
    $viewlimit = '';    

    if (isset($element[0])) {
        $iconopts = explode("-", strtolower($element[0]));
        $iconsize = empty($iconopts[0]) ? '' : $iconopts[0];
        if (isset($iconopts[1])) {
            $silent = strpos($iconopts[1], 's') !== false; // no text output
            $autoarchive = strpos($iconopts[1], 'a') === false; // point to the recording unless the 'a' is included
            $telephony = strpos($iconopts[1], 'p') === false; // no phone info
            $allowguests = strpos($iconopts[1], 'g') !== false; // allow guest user access
            //$mouseovers = strpos($iconopts[1], 'm') === false; // no mouseover
            if (strpos($iconopts[1], 'l') !== false) $iconalign = 'left';
            elseif (strpos($iconopts[1], 'r') !== false) $iconalign = 'right';
        }
    }
    if (empty($CFG->connect_telephony))
        $telephony = false;
    //if (empty($CFG->connect_mouseovers))
        //$mouseovers = false;

    $startdate = empty($element[1]) ? '' : $element[1];
    $enddate = empty($element[2]) ? '' : $element[2];
    $extra_html = empty($element[3]) ? '' : $element[3];
    $extra_html = preg_replace( '/%%quote%%/', '"', $extra_html );
    $force_icon = empty($element[4]) ? '' : $element[4];
    $connectmeetingid = empty($element[5]) ? 0 : $element[5];
    $grouping = '';

    if (!(!empty($PAGE->context) && $PAGE->user_allowed_editing())) {
        if (!empty($startdate) and time() < strtotime($startdate)) return;
        if (!empty($enddate) and time() > strtotime($enddate)) return;
    } else $nomouseover = false;

    if ($connectmeeting->start) {
        $connectmeeting->end = $connectmeeting->start + $connectmeeting->duration;
    }elseif ($connectmeeting->eventid AND $event = $DB->get_record('event', array('id' => $connectmeeting->eventid))) {
        $connectmeeting->start = $event->timestart;
        $connectmeeting->end = $event->timestart + $event->timeduration;
    }else{
        $connectmeeting->end = 0;
    }
    if ($connectmeeting->end > time()) unset($connectmeeting->ac_archive);
    if ($connectmeeting->maxviews) {
        if (!$views = $DB->get_field('connectmeeting_entries', 'views', array('connectmeetingid' => $connectmeeting->id, 'userid' => $USER->id))) $views = 0;
        $viewlimit = get_string('viewlimit', 'connectmeeting') . $views . '/' . $connectmeeting->maxviews . '<br/>';
    }

    // Check for grouping
    $grouping = '';
    $mod = get_coursemodule_from_instance('connectmeeting', $connectmeeting->id, $connectmeeting->course);
    if (!empty($mod->groupingid) && has_capability('moodle/course:managegroups', context_course::instance($mod->course))) {
        $groupings = groups_get_all_groupings($mod->course);
        $textclasses = isset( $textclasses ) ? $textclasses : '';
        $grouping = html_writer::tag('span', '('.format_string($groupings[$mod->groupingid]->name).')',
                array('class' => 'groupinglabel '.$textclasses));
    }

    // check for addin launch settings
    if( isset( $CFG->connect_adobe_addin ) && $CFG->connect_adobe_addin && isset( $connectmeeting->addinroles ) && $connectmeeting->addinroles ){
        $forceaddin = 1;
        $roleids = explode( ',', $connectmeeting->addinroles );
        $userroles = get_user_roles( context_course::instance( $connectmeeting->course ), $USER->id );
        foreach( $userroles as $userrole ){
            if( in_array( $userrole->roleid, $roleids ) ){
                $forceaddin = 2; // one of there roles is marked to launch from browser
                break;
            }
        }
    }

    // Custom icon from activity settings
    if (!empty($force_icon)) {
        // get the custom icon file url
        // TODO consider storing file name in display so as not to fetch it from the database here
        if ($cm = get_coursemodule_from_instance('connectmeeting', $connectmeeting->id, $connectmeeting->course, false)) {
            $context = context_module::instance($cm->id);
            $fs = get_file_storage();
            if ($files = $fs->get_area_files($context->id, 'mod_connectmeeting', 'content', 0, 'sortorder', false)) {
                $iconfile = reset($files);

                $filename = $iconfile->get_filename();
                $path = "/$context->id/mod_connectmeeting/content/0";
                $iconurl = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
                $iconsize = '';
                $icondiv = 'force_icon';
            }
        }

        // Custom icon from editor has the url in the force icon but no connect id
    } else if (!$connectmeeting->id and !empty($force_icon)) {
        $iconurl = $force_icon;
        $iconsize = '';
        $icondiv = 'force_icon';
    }

    // No custom icon, see if there is a custom default for this type
    if (empty($iconurl)) {
        $icontype = 'meeting';
        
        $iconsize = isset($sizes[$iconsize]) ? $sizes[$iconsize] : '';

        $context = context_system::instance();
        $fs = get_file_storage();
        if ($files = $fs->get_area_files($context->id, 'mod_connectmeeting', $icontype . '_icon', 0, 'sortorder', false)) {
            $iconfile = reset($files);

            $filename = $iconfile->get_filename();
            $path = "/$context->id/mod_connectmeeting/{$icontype}_icon/0";
            $iconurl = moodle_url::make_file_url('/pluginfile.php', "$path/$filename");
            $icondiv = $icontype . '_icon' . $iconsize;

            if ($iconsize == '_md') {
                $iconforcewidth = 120;
            } elseif ($iconsize == '_sm') {
                $iconforcewidth = 60;
            } else {
                $iconforcewidth = 180;
            }

        }
    }

    // No custom icon so just display the default icon
    if (empty($iconurl)) {
        $scotype = 'meeting';
        $icontype = isset($types[$scotype]) ? $types[$scotype] : 'misc';
        if ($autoarchive AND !empty($connectmeeting->ac_archive)) $icontype = 'archive';
        $iconsize = isset($sizes[$iconsize]) ? $sizes[$iconsize] : '';
        $iconurl = new moodle_url("/mod/connectmeeting/images/$icontype$iconsize.jpg");
        $icondiv = $icontype . '_icon' . $iconsize;
    }

    $strtime = '';
    if ($connectmeeting->ac_type == 'meeting' AND $connectmeeting->end > time() AND isset($USER->timezone)) {
        $strtime .= userdate($connectmeeting->start, '%a %b %d, %Y', $USER->timezone);
        if ($iconsize == '_md' OR $iconsize == '_sm') $strtime .= "<br/>";
        $strtime .= userdate($connectmeeting->start, "@ %I:%M%p") . ' - ';
        $strtime .= userdate($connectmeeting->end, "%I:%M%p ") . connectmeeting_mod_tzabbr() . '<br/>';
    }

    $strtele = '';
    if ($connectmeeting->ac_type == 'meeting' AND $telephony AND $connectmeeting->end > time()) {
        $strtele .= '<b>';
        if (!empty($connectmeeting->ac_phone)) {
            $strtele .= get_string('tollfree', 'connectmeeting') . ' ' . $connectmeeting->ac_phone;
            if ($iconsize == '_md' OR $iconsize == '_sm') $strtele .= "<br/>";
        }
        if (!empty($connectmeeting->ac_pphone)){
            $strtele .= " (";
            $strtele .= get_string('pphone', 'connectmeeting');
            $strtele .= $connectmeeting->ac_pphone . ')';
        }
        $strtele .= '</b><br/>';
    }

    if (!$silent) {
        $font = '<font>';
        if ($iconsize == '_sm') {
            $font = '<font size="1">';
        }
        $instancename = html_writer::tag('span', $connectmeeting->name, array('class' => 'instancename')) . '<br/>';
        $aftertext = $font . $instancename . $strtime . $strtele . $viewlimit . $grouping . $extra_html . '</font>';
    } else {
        $aftertext = $extra_html;
    }

    $archive = '';
    if ($autoarchive AND !empty($connectmeeting->ac_archive)) $archive = '&archive=' . $connectmeeting->ac_archive;

    if( !isset( $forceaddin ) || !$forceaddin ){
        $forceaddin = 0;
    }
    $linktarget = $forceaddin == 1 ? '_self' : '_blank';

    $link = $thisdir . '/launch.php?acurl='.$connectmeeting->url.'&connect_id=' . $connectmeeting->id . $archive . '&guests=' . ($allowguests ? 1 : 0) . '&course=' . $connectmeeting->course.'&forceaddin='.$forceaddin;

    $overtext = '';
    if ($mouseovers || is_siteadmin($USER)) {
        $overtext = '<div align="right"><br /><br /><br />';
        /*$overtext .= '<a href="' . $link . '" target="'.$linktarget.'" >';
        if (!empty($archive)) {
            //$overtext .= '<b>' . get_string('launch_archive', 'connectmeeting') . '</a></b><br/>';
            $overtext .= "<img src='" . $OUTPUT->pix_url('t/save)', 'mod_connectmeeting') . "' class='iconsmall' title='" . get_string('launch_archive', 'connectmeeting')  ."' />". "</a>";
        }
        else {
            //$overtext .= '<b>' . get_string('launch_' . $connectmeeting->ac_type, 'connectmeeting') . '</a></b><br/>';
            $overtext .= "<img src='" . $OUTPUT->pix_url('e/redo') . "' class='iconsmall' title='" . get_string('launch_' . $connectmeeting->ac_type, 'connectmeeting')  ."' />". "</a>";
        }*/



        if (!empty($connectmeeting->intro)) {
            $search = '/\[\[user#([^\]]+)\]\]/is';
            $connectmeeting->intro = preg_replace_callback($search, 'mod_connectmeeting_user_callback', $connectmeeting->intro);
            $overtext .= str_replace("\n", "<br />", $connectmeeting->intro) . '<br/>';
        }
        //$overtext .= $strtime . $strtele;

        if (!empty($PAGE->context) && $PAGE->user_allowed_editing()) {
            if( $course = $DB->get_record( 'course', array( 'id' => $connectmeeting->course ) ) ){
                $editcontext = context_course::instance($course->id);
            }else{
                $editcontext = context_system::instance();
            }
            if (has_capability('filter/connect:editresource', $editcontext)) {
                $overtext .= '<a href="' . $link . '&edit=' . $connectmeeting->ac_id . '&type=' . $connectmeeting->ac_type . '" target="'.$linktarget.'" >';
                //$overtext .= '<img src="' . $CFG->wwwroot . '/mod/connectmeeting/images/adobe.gif" border="0" align="middle"> ';
                //$overtext .= get_string('launch_edit', 'connectmeeting') . '</a><br/>';
                $overtext .= "<img src='" . $OUTPUT->pix_url('/t/edit') . "' class='iconsmall' title='" . get_string('launch_edit', 'connectmeeting')  ."' />". "</a>";

                $overtext .= '<a href="#" id="connectmeeting-update-from-adobe" data-connectmeetingid="'.$connectmeeting->id.'">';
                //$overtext .= '<img src="' . $CFG->wwwroot . '/mod/connectmeeting/images/adobe.gif" border="0" align="middle"> ';
                //$overtext .= get_string('update_from_adobe', 'connectmeeting') . '</a><br/>';
                $overtext .= "<img src='" . $OUTPUT->pix_url('/i/return') . "' class='iconsmall' title='" . get_string('update_from_adobe', 'connectmeeting')  ."' />". "</a>";
            }

            if ($connectmeeting->ac_type == 'meeting') {
                if ($connectmeeting->start > time()) {
                } else {
                    if( file_exists( $CFG->dirroot.'/filter/connect/attendees.php' ) ){
                        $overtext .= '<a href="' . $CFG->wwwroot . '/filter/connect/attendees.php?acurl=' . $connectmeeting->url . '&course=' . $connectmeeting->course . '">';
                        //$overtext .= '<img src="' . $CFG->wwwroot . '/filter/connect/images/attendee.gif" border="0" align="middle"> ' . get_string('viewattendees', 'filter_connect') . '</a>';
                        $overtext .= "<img src='" . $OUTPUT->pix_url('/t/groups') . "' class='iconsmall' title='" . get_string('viewattendees', 'filter_connect') ."' />". "</a>";
                    }
                    $overtext .= '<a href="' . $CFG->wwwroot . '/mod/connectmeeting/past_sessions.php?acurl=' . $connectmeeting->url . '&course=' . $connectmeeting->course . '">';
                    //$overtext .= '<br /><img src="' . $CFG->wwwroot . '/mod/connectmeeting/images/attendee.gif" border="0" align="middle"> ' . get_string('viewpastsessions', 'connectmeeting') . '</a>';
                    $overtext .= "<img src='" . $OUTPUT->pix_url('/t/calendar') . "' class='iconsmall' title='" . get_string('viewpastsessions', 'connectmeeting') ."' />". "</a>";
                }
            }
        }
        $overtext .= '</div>';
    }

    $clock = '';
    if ($connectmeeting->ac_type == 'meeting' AND time() > ($connectmeeting->start - 1800) AND $connectmeeting->end > time()) {
        $clock = '<img id="tooltipimage" class="clock" src="' . $CFG->wwwroot . '/mod/connectmeeting/images/clock';
        if ($iconsize == '_sm') $clock .= '-s';
        $clock .= '.gif" border="0" id="clock"' . $link . '>';
        // do qtip here
    }

    $height = (isset($CFG->connect_popup_height) ? 'height=' . $CFG->connect_popup_height . ',' : '');
    $width = (isset($CFG->connect_popup_width) ? 'width=' . $CFG->connect_popup_width . ',' : '');

    $font = '';
    if ($iconsize == '_sm') $font = '<font size="1">';

    $onclick = $link;
    $onclick = str_replace("'", "\'", htmlspecialchars($link));
    $onclick = str_replace('"', '\"', $onclick);
    if( $linktarget == '_self' ){
        $onclick = "window.location.href='$onclick'";
    }else{
        $onclick = ' onclick="return window.open(' . "'" . $onclick . "' , 'connectmeeting', '{$height}{$width}menubar=0,location=0,scrollbars=0,resizable=1' , 0);" . '"';
    }

    $iconwidth = (isset($iconforcewidth)) ? "width=\"$iconforcewidth\" " : "";
    $iconheight = (isset($iconforceheight)) ? "height=\"$iconforceheight\" " : "";



    $display = '<div id="connectmeetingcontent'.$connectmeeting->id.'" style="text-align: '.$iconalign.'; width: 100%;">
        <div class="connect-course-icon-'.$iconalign.'" id="'.$icondiv.'">
            <a href="'.$link.'" 
                '.($mouseovers || is_siteadmin($USER) ? 'class="mod_connectmeeting_tooltip"' : '').'
                style="display: inline-block;" target="'.$linktarget.'">
                <img src="'.$iconurl.'" border="0"/>
                '.$clock.'
            </a>
        </div>
        <div class="connect-course-aftertext-'.$iconalign.'">
        '.$aftertext.'
        </div>
        <div class="mod_connectmeeting_popup" style="display: block;">
                '.$overtext.'
            </div>
    </div>';

    return $display;
}

// User substitutions
function mod_connectmeeting_user_callback($link) {
    global $CFG, $USER, $PAGE;
    $disallowed = array('password', 'aclogin', 'ackey');

    $PAGE->set_cacheable(false);
    // don't show any content to users who are not logged in using an authenticated account
    if (!isloggedin()) return;

    if (!isset($USER->{$link[1]}) || in_array($link[1], $disallowed)) return;

    return $USER->{$link[1]};
}

function connectmeeting_mod_tzabbr() {
    global $USER, $CFG;
    if (!isset($USER->timezone) || empty($USER->timezone) || $USER->timezone == 99) {
        $userTimezone = $CFG->timezone;
    } else {
        $userTimezone = $USER->timezone;
    }
    $dt = new DateTime("now", new DateTimeZone($userTimezone));
    return $dt->format('T');
}

function connectmeeting_grade_meeting($courseid, $url, $connectmeeting = null, $startdaterange, $enddaterange, $regrade) {
    global $CFG, $DB, $USER;

    if (!$connectmeeting AND !$connectmeeting = $DB->get_record('connectmeeting', array('course' => $courseid, 'url' => $url))) return false;

    if ($connectmeeting->detailgrading == 2) {
        //Fast-Track
        if ($scores = ft_get_scores($connectmeeting->url)) {
            foreach ($scores as $userid => $grade) {
                
                // skip them if they have a grade outside the range
                if( !connectmeeting_grade_based_on_range( $userid, $connectmeeting->id, $startdaterange, $enddaterange, $regrade ) ) continue;

                if (empty($userid)) continue;
                $field = 'id';
                if (!$user = $DB->get_record('user', array($field => $userid, 'deleted' => 0))) continue;
                if (!$entry = $DB->get_record('connectmeeting_entries', array('connectmeetingid' => $connectmeeting->id, 'userid' => $user->id))) {
                    $entry = new stdClass();
                    $entry->connectmeetingid = $connectmeeting->id;
                    $entry->userid = $user->id;
                    $entry->type = 'meeting';
                    $entry->minutes = 0;
                    $entry->slides = 0;
                    $entry->positions = 0;
                    $entry->score = 0;
                    $entry->timemodified = time();
                }

                if (!isset($entry->grade) OR $entry->grade < $grade) $entry->grade = $grade;
                if (!isset($entry->id)) $entry->id = $DB->insert_record('connectmeeting_entries', $entry);
                else $DB->update_record('connectmeeting_entries', $entry);
                connectmeeting_gradebook_update($connectmeeting, $entry);
            }
        }
    } elseif ($connectmeeting->detailgrading == 3) { //Vantage Point
        $context = context_course::instance($connectmeeting->course);
        $course = $DB->get_record('course', array('id' => $connectmeeting->course));
        $users = get_enrolled_users($context);
        if (!$users) return true; // no enroled users, nothing to grade

        foreach ($users as $user) {

            // skip them if they have a grade outside the range
            if( !connectmeeting_grade_based_on_range( $user->id, $connectmeeting->id, $startdaterange, $enddaterange, $regrade ) ) continue;

            $grade = connectmeeting_vp_get_score($connectmeeting, $user);

            if ($grade == -1) {
                return false; // scores not ready yet, return false so meeting won't be completed yet and will check again next cron
            } elseif ($grade == -2) {
                return true; // vantage point couldn't find any grades, meeting will complete without it
            } elseif ($grade > 0) { // woo, we have a grade!!
                if (!$entry = $DB->get_record('connectmeeting_entries', array('connectmeetingid' => $connectmeeting->id, 'userid' => $user->id))) {
                    $entry = new stdClass();
                    $entry->connectmeetingid = $connectmeeting->id;
                    $entry->userid = $user->id;
                    $entry->type = 'meeting';
                    $entry->minutes = 0;
                    $entry->slides = 0;
                    $entry->positions = 0;
                    $entry->score = 0;
                    $entry->timemodified = time();
                }

                $scores = new stdClass;
                $scores->minutes = $grade;
                connectmeeting_grade_entry('', $connectmeeting, $entry, $scores);
                if (!isset($entry->id)) $entry->id = $DB->insert_record('connectmeeting_entries', $entry);
                else $DB->update_record('connectmeeting_entries', $entry);
            }
        }
    } else {
        //Adobe Connect
        if (!$sco = connect_get_sco_by_url($connectmeeting->url, 1)) return false;
        if ( !isset( $sco->type ) || $sco->type != 'meeting') return false;

        if (isset($sco->times)) {
            foreach ($sco->times as $userid => $time) {
                if (empty($userid)) continue;
                // Bug fix - $field is aclogin by default for table user.
                //$field = 'email';
                $field = 'id';

                // skip them if they have a grade outside the range
                if( !connectmeeting_grade_based_on_range( $userid, $connectmeeting->id, $startdaterange, $enddaterange, $regrade ) ) continue;
                
                if (!$user = $DB->get_record('user', array($field => $userid, 'deleted' => 0))) continue;
                if (!$entry = $DB->get_record('connectmeeting_entries', array('connectmeetingid' => $connectmeeting->id, 'userid' => $user->id))) {
                    $entry = new stdClass();
                    $entry->connectmeetingid = $connectmeeting->id;
                    $entry->userid = $user->id;
                    $entry->type = 'meeting';
                    $entry->grade = 0;
                    $entry->minutes = 0;
                    $entry->score = 0;
                    $entry->slides = 0;
                    $entry->positions = 0;
                    $entry->timemodified = time();
                }

                $scores = new stdClass;
                $scores->minutes = $time;
                connectmeeting_grade_entry($userid, $connectmeeting, $entry, $scores);
                if (!isset($entry->id)) $DB->insert_record('connectmeeting_entries', $entry);
                else $DB->update_record('connectmeeting_entries', $entry);
            }
        }
    }

    return true;
}

function connectmeeting_vp_get_score($connectmeeting, $user){
    $connect_instance = _connect_get_instance();
    $params = array(
        'external_connect_id' => $connectmeeting->id,
        'external_user_id'    => $user->id,
        'start'               => $connectmeeting->start,
        'duration'            => $connectmeeting->duration
    );    
    $result =  $connect_instance->connect_call('vp-get-score', $params);  
    return $result;
}

// Called when about to be locked out based on a Connect Activity
// Called from locklib
// Requires $CFG->connect_instant_grade > 0;
function connectmeeting_regrade_one($connectmeetingid, $userid) {
    global $CFG, $DB, $USER;

    if (!$user = $DB->get_record('user', array('id' => $userid))) return false;
    if (!$connectmeeting = $DB->get_record('connectmeeting', array('id' => $connectmeetingid))) return false;
    if (!$entry = $DB->get_record('connectmeeting_entries', array('userid' => $user->id, 'connectmeetingid' => $connectmeetingid))) return false;
    if ( !connectmeeting_grade_entry($user->id, $connectmeeting, $entry)) return false;
    elseif (!connectmeeting_grade_entry($user->id, $connectmeeting, $entry)) return false;
    $DB->update_record('connectmeeting_entries', $entry);
    return $entry->grade;
}

function connectmeeting_set_forceicon($connectmeeting) {
    if( function_exists( 'local_connect_set_forceicon' ) ){
        return local_connect_set_forceicon( $connectmeeting, 'connectmeeting' );
    }else{
        return false;
    }
}

/**
 * Serves the resource files.
 * @param object $course
 * @param object $cm
 * @param object $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @return bool false if file not found, does not return if found - just send the file
 */
function connectmeeting_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if( function_exists( 'local_connect_pluginfile' ) ){
        return local_connect_pluginfile( $course, $cm, $context, $filearea, $args, $forcedownload, $options, 'connectmeeting' );
    }else{
        return false;
    }
}



/**
 * Return a list of page types
 * @param string $pagetype current page type
 * @param stdClass $parentcontext Block's parent context
 * @param stdClass $currentcontext Current context of block
 */
function connectmeeting_page_type_list($pagetype, $parentcontext, $currentcontext) {
    $module_pagetype = array('mod-connectmeeting-*' => 'Any connect page type');
    return $module_pagetype;
}
