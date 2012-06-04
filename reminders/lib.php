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
require_once($CFG->dirroot . '/local/reminders/contents/course_reminder.class.php');
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
    $aheaddaysindex = array(7 => 0, 3 => 1, 1 => 2);
    
    // gets all upcoming events for next 7 days.
    $upcomingevents = calendar_get_upcoming(true, true, true, 7, LOCAL_REMINDERS_MAX_REMINDERS_FOR_CRON_CYCLE, $now);
    
    // gets all log records for reminder sents
    $params = array();
    $selector = "l.course = 0 AND l.module = 'local_reminders' AND l.action = 'sent reminder' AND l.time >= :cutofftime";
    $params['cutofftime'] = $now - 48 * 3600;
    $logrows = get_logs($selector, $params);
    
    // re-defining structure of log records for faster fetch in next steps
    $logrecs = array();
    if ($logrows) {
        foreach ($logrows as $lr) {
            $logrecs[$lr->url.'&'.$lr->info] = $lr;
        }
    }
    
    // no upcoming events, so let's stop.
    if (empty($upcomingevents)) {
        return;
    }
    
    $fromuser = get_admin();
    
    // iterating through each event...
    foreach ($upcomingevents as $event) {
        $event = new calendar_event($event);
        
        $timediff = $event->timestart - $now;
        $aheadday = 0;
        
        if ($timediff <= 24 * 3600) {
            $aheadday = 1;
        } else if ($timediff <= 24 * 3 * 3600) {
            $aheadday = 3;
        } else if ($timediff <= 24 * 7 * 3600) {
            $aheadday = 7;
        }
        
        if ($aheadday == 0) continue;
        
        // reminders has been already sent for this event for this ahead of date
        // by a previous cron cycle.
        if (isset($logrecs['event.php?id='.$event->id.'&'.$aheadday])) {
            continue;
        }
        
        $options = null;
        
        if ($event->eventtype == 'site') {
            $options = $CFG->local_reminders_site_rdays;
            //$globalreminder = new global_reminder($event);
        } else if ($event->eventtype == 'user') {
            $options = $CFG->local_reminders_user_rdays;
            //$user = $DB->get_record('user', array('id' => $event->userid));
            //$userreminder = new user_reminder($user, $event);
        } else if ($event->eventtype == 'due' || $event->eventtype == 'course') {
            $options = $CFG->local_reminders_course_rdays;
            //$user = $DB->get_record('course', array('id' => $event->courseid));
            //$coursereminder = new course_reminder($course, $event);
        } else if ($event->eventtype == 'group') {
            $options = $CFG->local_reminders_group_rdays;
            //$group_reminder = new group_reminder($event);
        }
        
        if ($options == null) continue;
        
        // this reminder will not be set up to send by configurations
        if ($options[$aheaddaysindex[$aheadday]] == '0') continue;
        
        $reminder = null;
        if ($event->eventtype == 'site') {
            $reminder = new global_reminder($event);
        } else if ($event->eventtype == 'user') {
            $user = $DB->get_record('user', array('id' => $event->userid));
            $reminder = new user_reminder($user, $event);
        } else if ($event->eventtype == 'due' || $event->eventtype == 'course') {
            $user = $DB->get_record('course', array('id' => $event->courseid));
            $reminder = new course_reminder($course, $event);
        } else if ($event->eventtype == 'group') {
            $reminder = new group_reminder($event);
        }
        if ($reminder == null) continue;
        
        $eventdata = $reminder->create_reminder_message_object($fromuser);
        if (isset($eventdata->userto)) {
            $mailresult = message_send($eventdata);
            
            if (!$mailresult) {
                mtrace("Error: local/reminders/lib.php local_reminders_cron(): Could not send out message 
                        for eventid $event->id to user $eventdata->userto");
            } else {
                add_to_log(0, 'local_reminders', 'sent reminder', 'event.php?id='.$event->id, $aheadday);
            }
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