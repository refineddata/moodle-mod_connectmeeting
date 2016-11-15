<?php // $Id: connect.php,v 1.00 2008/04/07 09:37:58 terryshane Exp $
require_once('../../config.php');
require_once($CFG->dirroot . '/mod/connectmeeting/lib.php');
require_once($CFG->dirroot.'/course/lib.php');
global $CFG, $DB;

$type = optional_param('type', 0, PARAM_INT);
$cid = optional_param('cid', 0, PARAM_INT);

$courseid = 0;

if( !$cid ){
	redirect( "$CFG->wwwroot", '', 0 );
}

if( $type == 1 ){
    $recur = $DB->get_record( 'connectmeeting_recurring', array( 'id' => $cid ) );
    if( $recur ){
        $connectmeeting = $DB->get_record( 'connectmeeting', array( 'id' => $recur->connectmeetingid ) );
        if( $connectmeeting ){
            $connectmeeting->url = $recur->url;
            $connectmeeting->start = $recur->start;
            $connectmeeting->duration = $recur->duration;
            $startdaterange = $recur->start;
            $enddaterange = $recur->start + $recur->duration + ( 60*60*2 );
        }
    }
}else{
    $connectmeeting = $DB->get_record( 'connectmeeting', array( 'id' => $cid ) );
    $startdaterange = $connectmeeting->start;
    $enddaterange = $connectmeeting->start + $connectmeeting->compdelay + ( 60*60*2 );
}

if( isset( $connectmeeting ) && $connectmeeting && $startdaterange && $enddaterange ){
    $courseid = $connectmeeting->course;
    connectmeeting_complete_meeting($connectmeeting, $startdaterange, $enddaterange);
}

$fromurl  = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER'] : '';
if( !$courseid && $fromurl ){
	if( preg_match( '/course\/view.php\?id=(\d+)/', $fromurl, $match ) ){
		if( isset( $match[1] ) && $match[1] && is_numeric( $match[1] ) ){
			$courseid = $match[1];	
		}
	}
}

if( !$courseid ){
	redirect( "$CFG->wwwroot", '', 0 );
}

// require_login();
$context = context_system::instance();

redirect( "$CFG->wwwroot/course/view.php?id=$courseid", '', 0 );
