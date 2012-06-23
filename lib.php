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
 * @return boolean 
 */
function local_reminders_cron() {
    global $CFG, $DB;

    $aheaddaysindex = array(7 => 0, 3 => 1, 1 => 2);
    
    // gets last local reminder cron log record
    $params = array();
    $selector = "l.course = 0 AND l.module = 'local_reminders' AND l.action = 'cron'";
    $logrows = get_logs($selector, $params, 'l.time DESC', '', 1);
    
    $timewindowstart = time();
    if (!$logrows) {  // this is the first cron cycle, after plugin is just installed
        mtrace("   [Local Reminder] first cron cycle");
        $timewindowstart = $timewindowstart - LOCAL_REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS * 24 * 3600;
    } else {
        // info field includes that starting time of last cron cycle.
        $timewindowstart = $logrows[0]->info + 1;               
    }
    
    mtrace("======= retrieved logs...");

    $now = time();
    
    // now lets filter appropiate events to send reminders
    
    $secondsaheads = array(7*24*3600, 3*24*3600, 24*3600);
    
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
    
    $whereclause .= ') AND visible = 1';
    
    //mtrace($whereclause);
    
    $upcomingevents = $DB->get_records_select('event', $whereclause);
    if ($upcomingevents === false) {     // no upcoming events, so let's stop.
        mtrace("======= no upcming events. Aborting...");
        add_to_log(0, 'local_reminders', 'cron', '', $now);
        return;
    }
    
    mtrace("======= retrieved upcoming events...");
    
    $fromuser = get_admin();
    
    // iterating through each event...
    foreach ($upcomingevents as $event) {
        $event = new calendar_event($event);
        mtrace("======= processing event".$event->id.  "...");
        
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
        
        $optionstr = 'local_reminders_'.$event->eventtype.'_rdays';
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
                
            case 'due':
            case 'open':
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
            mtrace("  [Local Reminders Cron] event object is null for event ".$event->id. ' type is '.$event->eventtype);
            continue;
        }
        
        mtrace("  [Local Reminders Cron] found event type ".$event->eventtype);
        
        foreach ($sendusers as $touser) {
            $eventdata->userto = $touser;

            $mailresult = message_send($eventdata);

            if (!$mailresult) {
                mtrace("Error: local/reminders/lib.php local_reminders_cron(): Could not send out message 
                        for eventid $event->id to user $eventdata->userto");
                mtrace($mailresult);
            } else {
                mtrace(" MESSAGE IS SUCCESSFULLY SENT!!!!");
            }
        }
        
        unset($sendusers);
        
    }
    
    add_to_log(0, 'local_reminders', 'cron', '', $now);
}
