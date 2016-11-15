<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package moodlecore
 * @subpackage backup-moodle2
 * @copyright 2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Define all the restore steps that will be used by the restore_connect_activity_task
 */

/**
 * Structure step to restore one connect activity
 */
class restore_connectmeeting_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('connectmeeting', '/activity/connectmeeting');
        $paths[] = new restore_path_element('connectmeeting_grade', '/activity/connectmeeting/grades/grade');
        if ($userinfo) {
            $paths[] = new restore_path_element('connectmeeting_entry', '/activity/connectmeeting/entries/entry');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_connectmeeting($data) {
        global $DB, $CFG, $USER;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->start = $this->apply_date_offset($data->start);
//        $data->timeclose = $this->apply_date_offset($data->timeclose);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        if( $data->autocert ){
            $data->autocert = $this->get_mappingid('certificate', $data->autocert);
        }

        // insert the connect record
        $newitemid = $DB->insert_record('connectmeeting', $data);
        // immediately after inserting "activity" record, call this
        $this->apply_activity_instance($newitemid);
        // RT-394 Assign enrolled users to adobe group
        $connectmeeting = $DB->get_record('connectmeeting', array( 'id' => $newitemid));
        if ( $connectmeeting->type != 'video' AND !empty( $connectmeeting->url ) ) {
            require_once($CFG->dirroot . '/mod/connectmeeting/lib.php');

            // update display so it has new connectid
            $connectmeeting->display = preg_replace( "/~$oldid/", "~$newitemid", $connectmeeting->display );
            $DB->update_record( 'connectmeeting', $connectmeeting );

            
                $meeting = new stdClass;
                $meeting->host = "$USER->id";
                $meeting->name = $connectmeeting->name;

                // update display so it has new connectid
                require_once( $CFG->dirroot . '/mod/connectmeeting/lib.php' );

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

                //if (!empty($COURSE)) $course = $COURSE;
                //else $course = $DB->get_record('course', 'id', $connectmeeting->course);
                $meeting->view = $data->course;
                $created_meeting = connect_create_meeting($connectmeeting->id, $connectmeeting->type, $meeting);
                //if (!$created_meeting) {
                    //return false;
                //}
            
            //$result = connect_use_sco($newitemid, $connectmeeting->url, $connectmeeting->type, $data->course);
            connect_add_access( $newitemid, $data->course, 'group' , 'view', false, 'meeting');
        }
    }

    protected function process_connectmeeting_grade($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->connectmeetingid = $this->get_new_parentid('connectmeeting');
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('connectmeeting_grading', $data);
        $this->set_mapping('connectmeeting_grading', $oldid, $newitemid);
    }

    protected function process_connectmeeting_entry($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->connectmeetingid = $this->get_new_parentid('connectmeeting');
        //$data->gradeid = $this->get_mappingid('connectmeeting_grading', $oldid);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        $newitemid = $DB->insert_record('connectmeeting_entries', $data);
        // No need to save this mapping as far as nothing depend on it
        // (child paths, file areas nor links decoder)
    }

    protected function after_execute() {
        // Add connect related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_connectmeeting', 'intro', null);
        // Add force icon related files, matching by item id (connect)
        $this->add_related_files('mod_connectmeeting', 'content', null);
    }
}
