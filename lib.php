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

require_once($CFG->dirroot . '/availability/classes/info_module.php');
require_once($CFG->libdir . '/modinfolib.php');

/// CONSTANTS ///////////////////////////////////////////////////////////

DEFINE('REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS', 2);

DEFINE('REMINDERS_7DAYSBEFORE_INSECONDS', 7*24*3600);
DEFINE('REMINDERS_3DAYSBEFORE_INSECONDS', 3*24*3600);
DEFINE('REMINDERS_1DAYBEFORE_INSECONDS', 24*3600);

DEFINE('REMINDERS_SEND_ALL_EVENTS', 50);
DEFINE('REMINDERS_SEND_ONLY_VISIBLE', 51);

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
    
    if (!isset($CFG->local_reminders_enable) || !$CFG->local_reminders_enable) {
        mtrace("   [Local Reminder] This cron cycle will be skipped, because plugin is not enabled!");
        return;
    }
       
    $aheaddaysindex = array(7 => 0, 3 => 1, 1 => 2);
    
    // loading roles allowed to receive reminder messages from configuration
    //
    $allroles = get_all_roles();
    $courseroleids = array();
    $activityroleids = array();
    if (!empty($allroles)) {
        $flag = 0;
        foreach ($allroles as $arole) {
            $roleoptionactivity = $CFG->local_reminders_activityroles;
            if ($roleoptionactivity[$flag] == '1') {
                $activityroleids[] = $arole->id;
            }
            $roleoption = $CFG->local_reminders_courseroles;
            if ($roleoption[$flag] == '1') {
                $courseroleids[] = $arole->id;
            }
            $flag++;
        }
    }

    // older implementation to retrieve most recent execution about reminders cron
    // cycle
    //
    //$params = array();
    //$selector = "l.course = 0 AND l.module = 'local_reminders' AND l.action = 'cron'";
    //$totalcount = 0;
    //$logrows = get_logs($selector, $params, 'l.time DESC', '', 1, $totalcount);

    // we need only last record only, so we limit the returning number of rows at most by one.
    //
    $logrows = $DB->get_records("local_reminders", array(), 'time DESC', '*', 0, 1);

    $timewindowstart = time();
    if (!$logrows) {  // this is the first cron cycle, after plugin is just installed
        mtrace("   [Local Reminder] This is the first cron cycle");
        $timewindowstart = $timewindowstart - REMINDERS_FIRST_CRON_CYCLE_CUTOFF_DAYS * 24 * 3600;
    } else {
        // info field includes that starting time of last cron cycle.
        $firstrecord = current($logrows);
        $timewindowstart = $firstrecord->time + 1;
    }
    
    // end of the time window will be set as current
    $timewindowend = time();
    
    // now lets filter appropiate events to send reminders
    //
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
        }
    }
    
    mtrace("   [Local Reminder] Time window: ".userdate($timewindowstart)." to ".userdate($timewindowend));
    
    $upcomingevents = $DB->get_records_select('event', $whereclause);
    if ($upcomingevents == false) {     // no upcoming events, so let's stop.
        mtrace("   [Local Reminder] No upcoming events. Aborting...");

        add_flag_record_db($timewindowend, 'no_events');
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
        mtrace("   [Local Reminder] Processing event#$event->id [Type: $event->eventtype, inaheadof=$aheadday days]...");
        
        $optionstr = 'local_reminders_'.$event->eventtype.'rdays';
        if (!isset($CFG->$optionstr)) {
            if ($event->modulename) {
                $optionstr = 'local_reminders_duerdays';
            } else {
                mtrace("   [Local Reminder] Couldn't find option for event $event->id [type: $event->eventtype]");
                continue;
            }
        }
        
        $options = $CFG->$optionstr;
        
        if (empty($options) || $options == null) {
            mtrace("   [Local Reminder] No configuration for eventtype $event->eventtype ".
                    "[event#$event->id is ignored!]...");
            continue;
        }
        
        // this reminder will not be set up to send by configurations
        if ($options[$aheaddaysindex[$aheadday]] == '0') {
            mtrace("   [Local Reminder] No reminder is due in ahead of $aheadday for eventtype $event->eventtype ".
                    "[event#$event->id is ignored!]...");
            continue;
        }
        
        $reminder = null;
        $eventdata = null;
        $sendusers = array();
        
        mtrace("   [Local Reminder] Finding out users for event#".$event->id."...");
        
        try {
        
            switch ($event->eventtype) {
                case 'site':
                    $reminder = new site_reminder($event, $aheadday);
                    $sendusers = $DB->get_records_sql("SELECT * 
                        FROM {user} 
                        WHERE id > 1 AND deleted=0 AND suspended=0 AND confirmed=1;");
                    $eventdata = $reminder->create_reminder_message_object($fromuser);

                    break;

                case 'user':
                    $user = $DB->get_record('user', array('id' => $event->userid));

                    if (!empty($user)) {
                        $reminder = new user_reminder($event, $user, $aheadday);
                        $eventdata = $reminder->create_reminder_message_object($fromuser);
                        $sendusers[] = $user;
                    } 

                    break;

                case 'course':
                    $course = $DB->get_record('course', array('id' => $event->courseid));

                    if (!empty($course)) {
                        $context = context_course::instance($course->id); //get_context_instance(CONTEXT_COURSE, $course->id);
                        $sendusers = get_role_users($courseroleids, $context, true, 'u.*');

                        // create reminder object...
                        //
                        $reminder = new course_reminder($event, $course, $aheadday);
                        $eventdata = $reminder->create_reminder_message_object($fromuser);
                    }

                    break;

                case 'open':

                    // if we dont want to send reminders for activity openings...
                    //
                    if (isset($CFG->local_reminders_duesend) && $CFG->local_reminders_duesend == REMINDERS_ACTIVITY_ONLY_CLOSINGS) {
                        mtrace("  [Local Reminder] Reminder sending for activity openings has been restricted in the configurations.");
                        break; 
                    }

                case 'close':

                    // if we dont want to send reminders for activity closings...
                    //
                    if (isset($CFG->local_reminders_duesend) && $CFG->local_reminders_duesend == REMINDERS_ACTIVITY_ONLY_OPENINGS) {
                        mtrace("  [Local Reminder] Reminder sending for activity closings has been restricted in the configurations.");
                        break; 
                    }

                case 'due':

                    if (!isemptyString($event->modulename)) {
                        $courseandcm = get_course_and_cm_from_instance($event->instance, $event->modulename, $event->courseid);
                        $course = $courseandcm[0];
                        $cm = $courseandcm[1];

                        if (!empty($course) && !empty($cm)) {
                            $activityobj = fetch_module_instance($event->modulename, $event->instance, $event->courseid);
                            $context = context_module::instance($cm->id); //get_context_instance(CONTEXT_MODULE, $cm->id);

                            // 'ra.id field added to avoid printing debug message from get_role_users (has odd behaivior when called with an array for $roleid param'
                            $sendusers = get_role_users($activityroleids, $context, true, 'ra.id, u.*');

                            // filter user list, replacement for deprecated/removed $cm->groupmembersonly & groups_get_grouping_members($cm->groupingid);
                            //   see: https://docs.moodle.org/dev/Availability_API#Display_a_list_of_users_who_may_be_able_to_access_the_current_activity
                            $info = new \core_availability\info_module($cm);
                            $sendusers = $info->filter_user_list($sendusers);

                            $reminder = new due_reminder($event, $course, $context, $aheadday);
                            $reminder->set_activity($event->modulename, $activityobj);
                            $eventdata = $reminder->create_reminder_message_object($fromuser);
                        }
                    }

                    break;

                case 'group':
                    $group = $DB->get_record('groups', array('id' => $event->groupid));

                    if (!empty($group)) {
                        $reminder = new group_reminder($event, $group, $aheadday);

                        // add module details, if this event is a mod type event
                        //
                        if (!isemptyString($event->modulename) && $event->courseid > 0) {
                            $activityobj = fetch_module_instance($event->modulename, $event->instance, $event->courseid);
                            $reminder->set_activity($event->modulename, $activityobj);
                        }
                        $eventdata = $reminder->create_reminder_message_object($fromuser);

                        $groupmemberroles = groups_get_members_by_role($group->id, $group->courseid, 'u.id');
                        if ($groupmemberroles) {
                            foreach($groupmemberroles as $roleid => $roledata) {
                                foreach($roledata->users as $member) {
                                    $sendusers[] = $DB->get_record('user', array('id' => $member->id));
                                }
                            }
                        }
                    }

                    break;

                default:
                     if (!isemptyString($event->modulename)) {
                        $course = $DB->get_record('course', array('id' => $event->courseid));
                        $cm = get_coursemodule_from_instance($event->modulename, $event->instance, $event->courseid);

                        if (!empty($course) && !empty($cm)) {
                            $activityobj = fetch_module_instance($event->modulename, $event->instance, $event->courseid);
                            $context = context_module::instance($cm->id); // get_context_instance(CONTEXT_MODULE, $cm->id);
                            $sendusers = get_role_users($activityroleids, $context, true, 'u.*');
                            
                            //$sendusers = get_enrolled_users($context, '', $event->groupid, 'u.*');
                            $reminder = new due_reminder($event, $course, $context, $aheadday);
                            $reminder->set_activity($event->modulename, $activityobj);
                            $eventdata = $reminder->create_reminder_message_object($fromuser);
                        }
                     } else {
                         mtrace("  [Local Reminder] Unknown event type [$event->eventtype]");
                     }
            }

        } catch (Exception $ex) {
            mtrace("  [Local Reminder - ERROR] Error occured when initializing ".
                    "for event#[$event->id] (type: $event->eventtype) ".$ex->getMessage());
            continue;
        }
        
        if ($eventdata == null) {
            mtrace("  [Local Reminder] Event object is not set for the event $event->id [type: $event->eventtype]");
            continue;
        }
        
        $usize = count($sendusers);
        if ($usize == 0) {
            mtrace("  [Local Reminder] No users found to send reminder for the event#$event->id");
            continue;
        }
        
        mtrace("  [Local Reminder] Starting sending reminders for $event->id [type: $event->eventtype]");
        $failedcount = 0;
        
        foreach ($sendusers as $touser) {
            $eventdata = $reminder->set_sendto_user($touser);
            //$eventdata->userto = $touser;
        
            //foreach ($touser as $key => $value) {
            //    mtrace(" User: $key : $value");
            //}
            //$mailresult = 1; //message_send($eventdata);
            //mtrace("-----------------------------------");
            //mtrace($eventdata->fullmessagehtml);
            //mtrace("-----------------------------------");
            try {
                $mailresult = message_send($eventdata);

                if (!$mailresult) {
                    throw new coding_exception("Could not send out message for event#$event->id to user $eventdata->userto");
                } 
            } catch (moodle_exception $mex) {
                $failedcount++;
                mtrace('Error: local/reminders/lib.php local_reminders_cron(): '.$mex->getMessage());
            }
        }
        
        if ($failedcount > 0) {
            mtrace("  [Local Reminder] Failed to send $failedcount reminders to users for event#$event->id");
        } else {
            mtrace("  [Local Reminder] All reminders was sent successfully for event#$event->id !");
        }
        
        unset($sendusers);
        
    }
    
    //add_to_log(0, 'local_reminders', 'cron', '', $timewindowend, 0, 0);
    add_flag_record_db($timewindowend, 'sent');
}

/**
 * Adds a database record to local_reminders table, to mark
 * that the current cron cycle is over. Then we flag the time
 * of end of the cron time window, so that no reminders sent
 * twice.
 *
 * @param $timewindowend string cron window time end.
 * @param string $crontype type of reminders cron.
 */
function add_flag_record_db($timewindowend, $crontype = '') {
    global $DB;

    $newRecord = new stdClass();
    $newRecord->time = $timewindowend;
    $newRecord->type = $crontype;
    $DB->insert_record("local_reminders", $newRecord);
}

/**
 * Function to retrive module instace from corresponding module
 * table. This function is written because when sending reminders 
 * it can restrict showing some fields in the message which are sensitive
 * to user. (Such as some descriptions are hidden until defined date)
 * Function is very similar to the function in datalib.php/get_coursemodule_from_instance,
 * but by below it returns all fields of the module.
 * 
 * Eg: can get the quiz instace from quiz table, can get the new assignment
 * instace from assign table, etc.
 * 
 * @param string $modulename name of module type, eg. resource, assignment,...
 * @param int $instance module instance number (id in resource, assignment etc. table)
 * @param int $courseid optional course id for extra validation
 * 
 * @return individual module instance (a quiz, a assignment, etc). 
 *          If fails returns null
 */
function fetch_module_instance($modulename, $instance, $courseid=0) {
    global $DB;

    $params = array('instance'=>$instance, 'modulename'=>$modulename);

    $courseselect = "";

    if ($courseid) {
        $courseselect = "AND cm.course = :courseid";
        $params['courseid'] = $courseid;
    }

    $sql = "SELECT m.*
              FROM {course_modules} cm
                   JOIN {modules} md ON md.id = cm.module
                   JOIN {".$modulename."} m ON m.id = cm.instance
             WHERE m.id = :instance AND md.name = :modulename
                   $courseselect";

    try {
        return $DB->get_record_sql($sql, $params, IGNORE_MISSING);
    } catch (moodle_exception $mex) {
        mtrace('  [Local Reminder - ERROR] Failed to fetch module instance! '.$mex.getMessage);
        return null;
    }
}

/**
 * Returns true if input string is empty/whitespaces only, otherwise false.
 * 
 * @param type $str string
 * 
 * @return boolean true if string is empty or whitespace
 */
function isemptyString($str) {
    return !isset($str) || empty($str) || trim($str) === '';
}