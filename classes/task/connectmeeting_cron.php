<?php
namespace mod_connectmeeting\task;

class connectmeeting_cron extends \core\task\scheduled_task {      
    public function get_name() {
        // Shown in admin screens
        return get_string('connectmeetingcron', 'connectmeeting');
    }
                                                                     
    public function execute() { 
        global $CFG;
        mtrace('++ Connect Meeting Cron Task: start');
        require_once($CFG->dirroot . '/mod/connectmeeting/lib.php');
        connectmeeting_cron_task();
        mtrace('++ Connect Meeting Cron Task: end');
    }                                                                                                                               
} 