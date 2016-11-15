<?php

defined( 'MOODLE_INTERNAL' ) || die;

function xmldb_connectmeeting_install() {
	global $DB, $CFG;
	$dbman = $DB->get_manager();
        
    // not proceed if mod connect is not installed.

    $connect = $DB->get_record('config_plugins', array('plugin' => 'mod_connect', 'name' => 'version'));
    if (empty($connect))
        return true;

	// copy from connect table for meeting type to connectmeeting table

	$sql = "insert into {connectmeeting}
(id,course,name,intro,introformat,url,start,display,displayoncourse,type,email,eventid,unenrol,compdelay,complete,autocert,detailgrading,initdelay,
loops,loopdelay,maxviews,addinroles,ac_archive,ac_type,ac_phone,ac_pphone,ac_id,ac_views,template_start,telephony_start,timemodified,duration)
select id,course,name,intro,introformat,url,start,display,displayoncourse,type,email,eventid,unenrol,compdelay,complete,autocert,detailgrading,initdelay,
loops,loopdelay,maxviews,addinroles,ac_archive,ac_type,ac_phone,ac_pphone,ac_id,ac_views,template_start,telephony_start,timemodified,duration
from {connect} where type='meeting'";

	$DB->execute( $sql );

	// copy from connect_entries table for meeting type to connectmeeting_entries table

	$sql = "insert into {connectmeeting_entries}
(id, connectmeetingid, userid, score, slides, minutes, positions, `type`, views, grade, rechecks, rechecktime, timemodified)
select e.id, e.connectid, e.userid, e.score, e.slides, e.minutes, e.positions, e.`type`, e.views, e.grade, e.rechecks, e.rechecktime, e.timemodified
from {connect_entries} e join
{connect} c on e.connectid = c.id 
where c.type = 'meeting'";

	$DB->execute( $sql );

	// copy from connect_grading table for meeting type to connectmeeting_grading table

	$sql = "insert into {connectmeeting_grading}
(id, connectmeetingid, threshold, grade, timemodified)
select g.id, g.connectid, g.threshold, g.grade, g.timemodified
from {connect_grading} g join
{connect} c on g.connectid = c.id 
where c.type = 'meeting' order by g.id";

	$DB->execute( $sql );

	// copy from connect_recurring table for meeting type to connectmeeting_recurring table
	if ( $dbman->table_exists( 'connect_recurring' ) && $dbman->table_exists( 'connectmeeting_recurring' ) && $dbman->field_exists('connect_recurring', 'scoid') ) {
		$sql = "insert into {connectmeeting_recurring}
(id, connectmeetingid, groupingid, `start`, url, email, eventid, unenrol, compdelay, autocert, record_used, duration, scoid)
select r.id, r.connectid, r.groupingid, r.`start`, r.url, r.email, r.eventid, r.unenrol, r.compdelay, r.autocert, r.record_used, r.duration, r.scoid
from {connect_recurring} r join
{connect} c on r.connectid = c.id
where c.type = 'meeting' order by r.id";

		$DB->execute( $sql );
	}

	// alter course module

	$module = $DB->get_record( 'modules', array( 'name' => 'connectmeeting' ) );

	$sql = "update {course_modules} cm
join {modules} m on
    cm.module = m.id and m.name = 'connect' 
join {connect} c on c.id = cm.instance and c.`type` = 'meeting'    
set module = $module->id";

	$DB->execute( $sql );

	//alter grade_items

	$sql = "update {grade_items} gi
join {connect} c on gi.itemmodule = 'connect' and gi.iteminstance = c.id and c.`type` = 'meeting'  
set itemmodule = 'connectmeeting'";

	$DB->execute( $sql );

	/* //delete connect_recurring for meeting

	$sql = "delete from {connect_recurring} where id in (select id from {connectmeeting_recurring} )";

	$DB->execute($sql);

	//delete connect_recurring for meeting

	$sql = "delete from {connect_grading} where id in (select id from {connectmeeting_grading} )";

	$DB->execute($sql);

	//delete connect_recurring for meeting

	$sql = "delete from {connect_entries} where id in (select id from {connectmeeting_entries} )";

	$DB->execute($sql);

	//delete connect for meeting

	$sql = "delete from {connect} where id in (select id from {connectmeeting} )";

	$DB->execute($sql);*/

	//Update refined service
	require_once( $CFG->dirroot . '/mod/connectmeeting/connectlib.php' );
	$external_connect_ids = $DB->get_fieldset_select( 'connectmeeting', 'id', '', array() );
	if ( ! empty( $external_connect_ids ) ) {
		connect_update_connect_meetings( $external_connect_ids, 'meeting' );
	}

	//Hide connect activity
	$module = $DB->get_record( 'modules', array( 'name' => 'connect' ) );

	if ( ! empty( $module ) && ( $module->visible == 1 ) ) {
		$module->visible = 0;
		$DB->update_record( 'modules', $module );
	}

	// diable Mod Connect Cron
	$cron = $DB->get_record( 'task_scheduled', array( 'component' => 'mod_connect' ) );

	if ( ! empty( $cron ) && ( $cron->disabled == 0 ) ) {
		$cron->disabled = 1;
		$DB->update_record( 'task_scheduled', $cron );
	}
}

