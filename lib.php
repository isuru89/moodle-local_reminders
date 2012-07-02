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

global $CFG;

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/site_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/user_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/course_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/group_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/due_reminder.class.php');

require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->dirroot . '/group/lib.php');
require_once($CFG->libdir . '/accesslib.php');

/// CONSTANTS ///////////////////////////////////////////////////////////

DEFINE('LOCAL_REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS', 2);
DEFINE('LOCAL_REMINDERS_MAX_REMINDERS_FOR_CRON_CYCLE', 100);

/// FUNCTIONS ///////////////////////////////////////////////////////////

/**
 * Function to be run periodically according to the moodle cron
 * Finds all events due for a reminder and send them out to the users.
 *  
 */
function local_reminders_cron() {
    global $CFG, $DB;
    
    if (!isset($CFG->local_reminders_enable) || !$CFG->local_reminders_enable) return;

    $aheaddaysindex = array(7 => 0, 3 => 1, 1 => 2);
    
    // gets last local reminder cron log record
    $params = array();
    $selector = "l.course = 0 AND l.module = 'local_reminders' AND l.action = 'cron'";
    $logrows = get_logs($selector, $params, 'l.time DESC', '', 1);
    
    $timewindowstart = time();
    if (!$logrows) {  // this is the first cron cycle, after plugin is just installed
        mtrace("   [Local Reminder] This is the first cron cycle");
        $timewindowstart = $timewindowstart - LOCAL_REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS * 24 * 3600;
    } else {
        // info field includes that starting time of last cron cycle.
        $timewindowstart = $logrows[0]->info + 1;               
    }
    
    //mtrace("======= retrieved log info...");
    
    // end of the time window will be set as current
    $timewindowend = time();
    
    // now lets filter appropiate events to send reminders
    
    $secondsaheads = array(7 * 24 * 3600, 3 * 24 * 3600, 24 * 3600);
    
    $whereclause = '(timestart > '.$timewindowend.') AND (';
    $count = 0;
    foreach ($secondsaheads as $sahead) {
        if($count > 0) {
            $whereclause .= ' OR ';
        }
        $whereclause .= '(timestart - '.$sahead.' >= '.$timewindowstart.' AND '.
                        'timestart - '.$sahead.' <= '.$timewindowend.')';
        $count++;
    }
    
    $whereclause .= ')';
    
    if (isset($CFG->local_reminders_only_visible) && $CFG->local_reminders_only_visible == 1) {
        $whereclause .= 'AND visible = 1';
    }
    
    //mtrace($whereclause);
    
    $upcomingevents = $DB->get_records_select('event', $whereclause);
    if ($upcomingevents === false) {     // no upcoming events, so let's stop.
        mtrace("   [Local Reminder] No upcming events. Aborting...");
        add_to_log(0, 'local_reminders', 'cron', '', $timewindowend);
        return;
    }
    
    mtrace("   [Local Reminder] Found ".count($upcomingevents)." upcoming events. Continuing...");
    
    $fromuser = get_admin();
    
    // iterating through each event...
    foreach ($upcomingevents as $event) {
        $event = new calendar_event($event);
        mtrace("   [Local Reminder] Processing event".$event->id.  "...");
        
        $aheadday = 0;
        
        if ($event->timestart-24*3600 >= $timewindowstart && $event->timestart-24*3600 <= $timewindowend) {
            $aheadday = 1;
        } else if ($event->timestart-3*24*3600 >= $timewindowstart && $event->timestart-3*24*3600 <= $timewindowend) {
            $aheadday = 3;
        } else if ($event->timestart-7*24*3600 >= $timewindowstart && $event->timestart-7*24*3600 <= $timewindowend) {
            $aheadday = 7;
        }
        
        if ($aheadday == 0) continue;
        
        $optionstr = 'local_reminders_'.$event->eventtype.'_rdays';
        if (!isset($CFG->$optionstr)) continue;
        $options = $CFG->$optionstr;
        
        if (empty($options) || $options == null) continue;
        
        // this reminder will not be set up to send by configurations
        if ($options[$aheaddaysindex[$aheadday]] == '0') continue;
        
        $reminder = null;
        $eventdata = null;
        $sendusers = array();
        
        switch ($event->eventtype) {
            case 'site':
                $reminder = new site_reminder($event, $aheadday);
                $eventdata = $reminder->create_reminder_message_object($fromuser);
                
                $sql = "SELECT u.id
                            FROM {user} u 
                            WHERE u.deleted = 0 AND u.suspended = 0 AND confirmed = 1";
                $sendusers = $DB->get_records_sql($sql);
                
                break;
            
            case 'user':
                $user = $DB->get_record('user', array('id' => $event->userid));
            
                if (!empty($user)) {
                    $reminder = new user_reminder($event, $user, $aheadday);
                    $eventdata = $reminder->create_reminder_message_object($fromuser);
                    $sendusers[] = $user->id;
                }
                
                break;
                
            case 'course':
                $course = $DB->get_record('course', array('id' => $event->courseid));
            
                if (!empty($course)) {
                    $context = get_context_instance(CONTEXT_COURSE, $course->id);
                    $sendusers = get_enrolled_users($context, '', $event->groupid, 'u.*');
                    $reminder = new course_reminder($event, $course, $aheadday);
                    $eventdata = $reminder->create_reminder_message_object($fromuser);
                }
                
                break;
                
            case 'open':
                // if we dont want to send reminders for activity openings...
                if (!isset($CFG->local_reminders_due_send_openings) || !$CFG->local_reminders_due_send_openings) break;  
            case 'due':
            case 'close':
                $course = $DB->get_record('course', array('id' => $event->courseid));
                $cm = get_coursemodule_from_instance($event->modulename, $event->instance, $event->courseid);

                if (!empty($course) && !empty($cm)) {
                    $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                    $sendusers = get_enrolled_users($context, '', $event->groupid, 'u.*');
                    $reminder = new due_reminder($event, $course, $context, $aheadday);
                    $eventdata = $reminder->create_reminder_message_object($fromuser);
                }
                
                break;
                
            case 'group':
                $group = $DB->get_record('groups', array('id' => $event->groupid));
            
                if (!empty($group)) {
                    $reminder = new group_reminder($event, $group, $aheadday);
                    $eventdata = $reminder->create_reminder_message_object($fromuser);

                    $groupmemberroles = groups_get_members_by_role($group->id, $group->courseid, 'u.id');
                    if ($groupmemberroles) {
                        foreach($groupmemberroles as $roleid=>$roledata) {
                            foreach($roledata->users as $member) {
                                $sendusers[] = $member->id;
                            }
                        }
                    }

                }
                
                break;
        }

        if ($eventdata == null) {
            mtrace("  [Local Reminder] Event object is not set for the event ".$event->id. " [type: ".$event->eventtype."]");
            continue;
        }
        
        $usize = count($sendusers);
        if ($usize == 0) {
            mtrace("  [Local Reminder] No users found to send reminder for the event ".$event->id);
            continue;
        }
        
        mtrace("  [Local Reminder] Starting sending reminders for ".$event->id. " [type: ".$event->eventtype."]");
        $failedcount = 0;
        
        foreach ($sendusers as $touser) {
            $eventdata->userto = $touser;

            $mailresult = message_send($eventdata);

            if (!$mailresult) {
                $failedcount++;
                mtrace("Error: local/reminders/lib.php local_reminders_cron(): Could not send out message 
                        for eventid $event->id to user $eventdata->userto");
                mtrace($mailresult);
            } else {
                //mtrace(" MESSAGE IS SUCCESSFULLY SENT!!!!");
            }
        }
        
        if ($failedcount > 0) {
            mtrace("  [Local Reminder] Failed to send ".$failedcount." reminders for event ".$event->id);
        } else {
            mtrace("  [Local Reminder] All reminders was sent successfully for event ".$event->id."!");
        }
        
        unset($sendusers);
        
    }
    
    add_to_log(0, 'local_reminders', 'cron', '', $timewindowend);
}
