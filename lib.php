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

/**
 * Library function for reminders cron function.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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

DEFINE('REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS', 2);
DEFINE('REMINDERS_MAX_REMINDERS_FOR_CRON_CYCLE', 100);

DEFINE('REMINDERS_7DAYSBEFORE_INSECONDS', 7*24*3600);
DEFINE('REMINDERS_3DAYSBEFORE_INSECONDS', 3*24*3600);
DEFINE('REMINDERS_1DAYBEFORE_INSECONDS', 24*3600);

DEFINE('REMINDERS_SEND_ALL_EVENTS', 50);
DEFINE('REMINDERS_SEND_ONLY_VISIBLE', 51);
DEFINE('REMINDERS_SEND_ONLY_HIDDEN', 52);

DEFINE('REMINDERS_ACTIVITY_BOTH', 60);
DEFINE('REMINDERS_ACTIVITY_ONLY_OPENINGS', 61);
DEFINE('REMINDERS_ACTIVITY_ONLY_CLOSINGS', 62);

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
    $totalrecords = 0;
    $params = array();
    $selector = "l.course = 0 AND l.module = 'local_reminders' AND l.action = 'cron'";
    $logrows = get_logs($selector, $params, 'l.time DESC', '', 1, &$totalrecords);
    
    $timewindowstart = time();
    if ($totalrecords == 0 || !$logrows) {  // this is the first cron cycle, after plugin is just installed
        mtrace("   [Local Reminder] This is the first cron cycle");
        $timewindowstart = $timewindowstart - REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS * 24 * 3600;
    } else {
        // info field includes that starting time of last cron cycle.
        $firstrecord = current($logrows);
        $timewindowstart = $firstrecord + 1;
        //$timewindowstart = $logrows[1]->info + 1;               
    }
    
    // end of the time window will be set as current
    $timewindowend = time();
    
    // now lets filter appropiate events to send reminders
    
    $secondsaheads = array(REMINDERS_7DAYSBEFORE_INSECONDS, REMINDERS_3DAYSBEFORE_INSECONDS, 
        REMINDERS_1DAYBEFORE_INSECONDS);
    
    $whereclause = '(timestart > '.$timewindowend.') AND (';
    $flagor = false;
    foreach ($secondsaheads as $sahead) {
        if($flagor) {
            $whereclause .= ' OR ';
        }
        $whereclause .= '(timestart - '.$sahead.' >= '.$timewindowstart.' AND '.
                        'timestart - '.$sahead.' <= '.$timewindowend.')';
        $flagor = true;
    }
    
    $whereclause .= ')';
    
    if (isset($CFG->local_reminders_filterevents)) {
        if ($CFG->local_reminders_filterevents == REMINDERS_SEND_ONLY_VISIBLE) {
            $whereclause .= 'AND visible = 1';
        } else if ($CFG->local_reminders_filterevents == REMINDERS_SEND_ONLY_HIDDEN) {
            $whereclause .= 'AND visible = 0';
        }
    }
    
    mtrace("   [Local Reminder] Time window: ".userdate($timewindowstart)." to ".userdate($timewindowend));
    
    $upcomingevents = $DB->get_records_select('event', $whereclause);
    if ($upcomingevents == false) {     // no upcoming events, so let's stop.
        mtrace("   [Local Reminder] No upcming events. Aborting...");
        add_to_log(0, 'local_reminders', 'cron', '', $timewindowend);
        return;
    }
    
    mtrace("   [Local Reminder] Found ".count($upcomingevents)." upcoming events. Continuing...");
    
    $fromuser = get_admin();
    
    // iterating through each event...
    foreach ($upcomingevents as $event) {
        $event = new calendar_event($event);

        $aheadday = 0;
        
        if ($event->timestart - REMINDERS_1DAYBEFORE_INSECONDS >= $timewindowstart && 
                $event->timestart - REMINDERS_1DAYBEFORE_INSECONDS <= $timewindowend) {
            $aheadday = 1;
        } else if ($event->timestart - REMINDERS_3DAYSBEFORE_INSECONDS >= $timewindowstart && 
                $event->timestart - REMINDERS_3DAYSBEFORE_INSECONDS <= $timewindowend) {
            $aheadday = 3;
        } else if ($event->timestart - REMINDERS_7DAYSBEFORE_INSECONDS >= $timewindowstart && 
                $event->timestart - REMINDERS_7DAYSBEFORE_INSECONDS <= $timewindowend) {
            $aheadday = 7;
        }
        
        if ($aheadday == 0) continue;
        mtrace("   [Local Reminder] Processing event#".$event->id." [Type: $event->eventtype]...");
        
        $optionstr = 'local_reminders_'.$event->eventtype.'rdays';
        if (!isset($CFG->$optionstr)) {
            if ($event->modulename) {
                $optionstr = 'local_reminders_duerdays';
            } else {
                mtrace("   [Local Reminder] Couldn't find option for event ".$event->id." [type: ".$event->eventtype."]");
                continue;
            }
        }
        
        $options = $CFG->$optionstr;
        
        if (empty($options) || $options == null) continue;
        
        // this reminder will not be set up to send by configurations
        if ($options[$aheaddaysindex[$aheadday]] == '0') continue;
        
        $reminder = null;
        $eventdata = null;
        $sendusers = array();
        
        mtrace("   [Local Reminder] Finding out users for event#".$event->id."...");
        
        switch ($event->eventtype) {
            case 'site':
                $reminder = new site_reminder($event, $aheadday);
                $eventdata = $reminder->create_reminder_message_object($fromuser);
                
                $sendusers = $DB->get_records('user', array('deleted' => 0, 'suspended' => 0, 'confirmed' => 1), '', 'u.id');
                
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
                if (isset($CFG->local_reminders_duesend) && $CFG->local_reminders_duesend == REMINDERS_ACTIVITY_ONLY_CLOSINGS) {
                    break; 
                }
            case 'close':
                // if we dont want to send reminders for activity closings...
                if (isset($CFG->local_reminders_duesend) && $CFG->local_reminders_duesend == REMINDERS_ACTIVITY_ONLY_OPENINGS) {
                    break; 
                }
            case 'due':
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
                        foreach($groupmemberroles as $roleid => $roledata) {
                            foreach($roledata->users as $member) {
                                $sendusers[] = $member->id;
                            }
                        }
                    }

                }
                
                break;
            
            default:
                 if ($event->modulename) {
                    $course = $DB->get_record('course', array('id' => $event->courseid));
                    $cm = get_coursemodule_from_instance($event->modulename, $event->instance, $event->courseid);

                    if (!empty($course) && !empty($cm)) {
                        $context = get_context_instance(CONTEXT_MODULE, $cm->id);
                        $sendusers = get_enrolled_users($context, '', $event->groupid, 'u.*');
                        $reminder = new due_reminder($event, $course, $context, $aheadday);
                        $eventdata = $reminder->create_reminder_message_object($fromuser);
                    }
                 }   
        }

        if ($eventdata == null) {
            mtrace("  [Local Reminder] Event object is not set for the event ".$event->id." [type: ".$event->eventtype."]");
            continue;
        }
        
        $usize = count($sendusers);
        if ($usize == 0) {
            mtrace("  [Local Reminder] No users found to send reminder for the event ".$event->id);
            continue;
        }
        
        mtrace("  [Local Reminder] Starting sending reminders for ".$event->id." [type: ".$event->eventtype."]");
        $failedcount = 0;
        
        foreach ($sendusers as $touser) {
            $eventdata->userto = $touser;

            $mailresult = message_send($eventdata);

            if (!$mailresult) {
                $failedcount++;
                mtrace("Error: local/reminders/lib.php local_reminders_cron(): Could not send out message 
                        for eventid $event->id to user $eventdata->userto");
                mtrace($mailresult);
            } 
        }
        
        if ($failedcount > 0) {
            mtrace("  [Local Reminder] Failed to send ".$failedcount." reminders to users for event ".$event->id);
        } else {
            mtrace("  [Local Reminder] All reminders was sent successfully for event ".$event->id."!");
        }
        
        unset($sendusers);
        
    }
    
    add_to_log(0, 'local_reminders', 'cron', '', $timewindowend);
}
