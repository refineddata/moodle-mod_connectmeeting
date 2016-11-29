<?php

// This file keeps track of upgrades to
// the chat module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

function xmldb_connectmeeting_upgrade($oldversion) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/db/upgradelib.php'); // Core Upgrade-related functions
    $dbman = $DB->get_manager();
    $result = true;

    // Moodle v2.2.0 release upgrade line
    // Put any upgrade step following this

    // Moodle v2.3.0 release upgrade line
    // Put any upgrade step following this


    // Moodle v2.4.0 release upgrade line
    // Put any upgrade step following this


    // Moodle v2.5.0 release upgrade line.
    // Put any upgrade step following this.


    // Moodle v2.6.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016112900) {

        // Define field aftertype to be added to reminders
        $table = new xmldb_table('connectmeeting_entries');
        $field = new xmldb_field('grade', XMLDB_TYPE_FLOAT, null, null, XMLDB_NOTNULL, null, 0 );

        // Conditionally launch add field aftertype
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_default($table, $field);
        }
    }

    return $result;
}


