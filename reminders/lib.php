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

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/global_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/user_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/site_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/group_reminder.class.php');

require_once($CFG->dirroot . '/calendar/lib.php');

/// CONSTANTS ///////////////////////////////////////////////////////////

//DEFINE('LOCAL_REMINDERS_CUTOFF_DAYS', 2);
DEFINE('LOCAL_REMINDERS_MAX_REMINDERS_FOR_CRON_CYCLE', 1000);

/// FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Function to be run periodically according to the moodle cron
 * Finds all events due for a reminder and send them out to the users.
 * 
 * @return boolean 
 */
function local_reminders_cron() {
    global $CFG, $DB;
    
    $now = time();
    
    // gets all upcoming events for next 7 days.
    $upcomingevents = calendar_get_upcoming(true, true, true, 7, LOCAL_REMINDERS_MAX_REMINDERS_FOR_CRON_CYCLE, $now);
    
    // no upcoming events, so let's stop.
    if (empty($upcomingevents)) {
        return;
    }
    
    $fromuser = get_admin();
    
    // iterating through each event...
    foreach ($upcomingevents as $event) {
        
        if ($event->eventtype == 'site') {
            $globalreminder = new global_reminder($event);
        } else if ($event->eventtype == 'user') {
            $userreminder = new user_reminder($event);
        } else if ($event->eventtype == 'due' || $event->eventtype == 'course') {
            $coursereminder = new course_reminder($event);
        } else if ($event->eventtype == 'group') {
            $group_reminder = new group_reminder($event);
        }
        
    }
    
    $eventdata = new stdClass();
    $eventdata->component        = 'local_reminders';   // plugin name
    $eventdata->name             = 'reminders_user';     // message interface name
    $eventdata->userfrom         = $fromuser;
    $eventdata->userto           = 3;
    $eventdata->subject          = 'This is a test message from Moodle';    // message title
    $eventdata->fullmessage      = 'Hello Moodle message interface! I am your content'; 
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml  = 'Hello Moodle message interface! I am your content';
    $eventdata->notification = 1;
    
    $mailresult = message_send($eventdata);
    
    mtrace($mailresult);
    
}