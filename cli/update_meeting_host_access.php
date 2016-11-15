<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once( $CFG->dirroot.'/mod/connectmeeting/connectlib.php' );


// Now get cli options.
list($options, $unrecognized) = cli_get_params(array('help' => false),
    array('h' => 'help'));

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help =
        "Add enrolled users to AC group.


        Options:
        -h, --help            Print out this help
        ";

    echo $help;
    die;
}

require_once($CFG->dirroot .'/mod/connectmeeting/connectlib.php');

$connects = $DB->get_records_sql( 'SELECT * FROM {connectmeeting} ORDER BY course ASC' );
$courseid = 0;
$context = 0;
foreach( $connects as $connect ){
    if( $courseid != $connect->course ){
        $courseid = $connect->course;
        $context = context_course::instance( $courseid );

        $hosts = get_users_by_capability($context, 'mod/connectmeeting:host');
        $presenters = get_users_by_capability($context, 'mod/connectmeeting:presenter');
        $users = array();

        foreach( $presenters as $presenter ){
            $users[$presenter->id] = 'mini-host';
        }    
        foreach( $hosts as $host ){
            $users[$host->id] = 'host';
        }
    }

    if( isset( $users ) && $users ){
        foreach( $users as $userid => $type ){
            connect_add_access( $connect->id, $userid, 'user', $type, true );
        }
    }    
}

cli_heading('Done');

exit(0);
