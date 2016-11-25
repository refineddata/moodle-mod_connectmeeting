<?php  // $Id: view.php
require_once('../../config.php');
global $USER, $SITE, $DB, $OUTPUT;

$acurl  = optional_param( 'acurl', '', PARAM_RAW );  // URL to Adobe Resource
$course = optional_param( 'course', 0, PARAM_INT );

if ( empty( $acurl ) ) error( 'Must provide an Adobe Custom URL.' );

require_login();

require_once( "$CFG->dirroot/mod/connectmeeting/lib.php" );

$PAGE->set_url('/mod/connectmeeting/past_sessions.php');
$PAGE->set_pagelayout('standard');
$PAGE->set_context( context_course::instance($course) );

$button = $OUTPUT->single_button( new moodle_url( '/course/view.php', array( 'id' => $course ) ), get_string( 'backtocourse', 'filter_connect' ) );

$PAGE->set_title( get_string( 'past_sessions_title', 'mod_connectmeeting' ) );
$PAGE->set_heading( get_string( 'past_sessions_heading', 'mod_connectmeeting' ) );
$PAGE->set_button( $button );

echo $OUTPUT->header();

echo "<div class='tab-content'>";

$table = new html_table();
	
$table->head = array();
$table->align = array();
$table->size = array();
	
$table->head[] = 'URL';
$table->align[] = 'LEFT';
$table->size[] = '20%';

$table->head[] = 'Start Time';
$table->align[] = 'LEFT';
$table->size[] = '30%';

$table->head[] = 'End Time';
$table->align[] = 'LEFT';
$table->size[] = '30%';

$table->head[] = '';
$table->align[] = 'LEFT';
$table->size[] = '20%';

$table->width = "100%";

// get the connect id based on the url and the course
$connectmeeting = $DB->get_record( 'connectmeeting', array( 'url'=>$acurl, 'course' => $course ), '*', IGNORE_MULTIPLE );
if( !$connectmeeting ){
    echo 'Could not find connect activity';
    exit(1);
}

$vpurl = 'https://vantagepoint.com/';
$principalid = connect_get_current_user_pid();

$sessions = $DB->get_records( 'connectmeeting_recurring', array( 'connectmeetingid' => $connectmeeting->id, 'record_used' => 1 ), 'start DESC' );

$returnurl = $CFG->wwwroot.'/course/view.php?id='.$course;

date_default_timezone_set('UTC');

if( $sessions ){
    foreach( $sessions as $session ){	
        $data = array();
        $data[] = $session->url;

        $startdate = $session->start;
        $enddate = $session->start+$session->duration;

        try {
            $dt = new DateTime('@'. $startdate );
            $dt->setTimeZone(new DateTimeZone( $USER->timezone ));
            $date1 = $dt->format('Y/m/d h:i a' );

            $dt = new DateTime('@'. $enddate );
            $dt->setTimeZone(new DateTimeZone( $USER->timezone ));
            $date2 = $dt->format('Y/m/d h:i a' );
        } catch( Exception $e ){
            $date1 = date( 'Y/m/d h:i a', $startdate );
            $date2 = date( 'Y/m/d h:i a', $enddate );
        }
        $data[] = $date1;
        $data[] = $date2;

        // need start date, end date, scoid, prinicple id of logged in user
        $start = date( 'Y-m-d H:i:s', $session->start );
        $end   = date( 'Y-m-d H:i:s', $session->start + $session->duration );
        $scoid = $session->scoid;

        $link = "<a href='{$vpurl}?startdate=$start&enddate=$end&scoid=$scoid&principalid=$principalid'>View in Vantage Point</a>";

        $regradeurl = $CFG->wwwroot.'/mod/connectmeeting/domeetingregrade.php?type=1&cid='.$session->id;

        $linkform = '<form name="myform'.$session->id.'" action="http://vantagepoint.refineddata.com/sessionhistory/" method="post">';
        $linkform.= '<input type="hidden" name="scoId" value="'.$scoid.'" />';
        $linkform.= '<input type="hidden" name="userLMSLogin" value="'.$USER->username.'" />';
        $linkform.= '<input type="hidden" name="userFullName" value="'.fullname($USER).'" />';
        $linkform.= '<input type="hidden" name="startDate" value="'.$start.'" />';
        $linkform.= '<input type="hidden" name="endDate" value="'.$end.'" />';
        $linkform.= '<input type="hidden" name="regradeUrl" value="'.$regradeurl.'" />';
        $linkform.= '<input type="hidden" name="returnUrl" value="'.$returnurl.'" />';
        $linkform.= '<a href="javascript: document.myform'.$session->id.'.submit()">View in Vantage Point</a>';
        $linkform.= '</form>';

        $data[] = $linkform;

        $table->data[] = $data;
    }
}else{
    $data = array();
    $data[] = $connectmeeting->url;

    $startdate = $connectmeeting->start;
    $enddate = $connectmeeting->start+$connectmeeting->duration;

    try {
        $dt = new DateTime('@'. $startdate );
        $dt->setTimeZone(new DateTimeZone( $USER->timezone ));
        $date1 = $dt->format('Y/m/d h:i a' );

        $dt = new DateTime('@'. $enddate );
        $dt->setTimeZone(new DateTimeZone( $USER->timezone ));
        $date2 = $dt->format('Y/m/d h:i a' );
    }catch( Exception $e ){
        $date1 = date( 'Y/m/d h:i a', $startdate );
        $date2 = date( 'Y/m/d h:i a', $enddate );
    }
    $data[] = $date1;
    $data[] = $date2;

    $sco = connect_get_sco_by_url($connectmeeting->url);
    $start = $connectmeeting->start;
    $end   = $connectmeeting->start + $connectmeeting->compdelay + (60*60*2);
    // need start date, end date, scoid, prinicple id of logged in user
    $start = date( 'Y-m-d H:i:s', $start );
    $end   = date( 'Y-m-d H:i:s', $end );
    $scoid = $sco->id;

    $linkform = "<a href='{$vpurl}?startdate=$start&enddate=$end&scoid=$scoid&principalid=$principalid'>View in Vantage Point</a>";

    $regradeurl = $CFG->wwwroot.'/mod/connectmeeting/domeetingregrade.php?cid='.$connectmeeting->id;

    $linkform = '<form name="myform'.$connectmeeting->id.'" action="http://vantagepoint.refineddata.com/sessionhistory/" method="post">';
    $linkform.= '<input type="hidden" name="scoId" value="'.$scoid.'" />';
    $linkform.= '<input type="hidden" name="userLMSLogin" value="'.$USER->username.'" />';
    $linkform.= '<input type="hidden" name="userFullName" value="'.fullname($USER).'" />';
    $linkform.= '<input type="hidden" name="startDate" value="'.$start.'" />';
    $linkform.= '<input type="hidden" name="endDate" value="'.$end.'" />';
    $linkform.= '<input type="hidden" name="regradeUrl" value="'.$regradeurl.'" />';
    $linkform.= '<input type="hidden" name="returnUrl" value="'.$returnurl.'" />';
    $linkform.= '<a href="javascript: document.myform'.$connectmeeting->id.'.submit()">View in Vantage Point</a>';
    $linkform.= '</form>';

    $data[] = $linkform;

    $table->data[] = $data;
}

echo html_writer::table( $table );

echo "</div>";

echo $OUTPUT->footer();
