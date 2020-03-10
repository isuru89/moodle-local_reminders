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
 * Local helper functions for reminders cron function.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/site_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/user_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/course_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/group_reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/due_reminder.class.php');

function process_activity_event($event, $aheadday, $activityroleids=null, $showtrace=true, $calltype=REMINDERS_CALL_TYPE_PRE) {
    global $DB, $PAGE;
    if (isemptystring($event->modulename)) {
        return null;
    }

    $courseandcm = get_course_and_cm_from_instance($event->instance, $event->modulename, $event->courseid);
    $course = $courseandcm[0];
    $cm = $courseandcm[1];
    $coursesettings = $DB->get_record('local_reminders_course', array('courseid' => $event->courseid));
    if (isset($coursesettings->status_activities) && $coursesettings->status_activities == 0) {
        $showtrace && mtrace("  [Local Reminder] Reminders for activities has been restricted in the configs.");
        return null;
    }

    if (!empty($course) && !empty($cm)) {
        $activityobj = fetch_module_instance($event->modulename, $event->instance, $event->courseid, $showtrace);
        $context = context_module::instance($cm->id);
        $PAGE->set_context($context);
        $sendusers = array();
        $reminder = new due_reminder($event, $course, $context, $cm, $aheadday);

        if ($event->courseid <= 0 && $event->userid > 0) {
            // A user overridden activity.
            $showtrace && mtrace("  [Local Reminder] Event #".$event->id." is a user overridden ".$event->modulename." event.");
            $user = $DB->get_record('user', array('id' => $event->userid));
            $sendusers[] = $user;
        } else if ($event->courseid <= 0 && $event->groupid > 0) {
            // A group overridden activity.
            $showtrace && mtrace("  [Local Reminder] Event #".$event->id." is a group overridden ".$event->modulename." event.");
            $group = $DB->get_record('groups', array('id' => $event->groupid));
            $sendusers = get_users_in_group($group);
        } else {
            // Here 'ra.id field added to avoid printing debug message,
            // from get_role_users (has odd behaivior when called with an array for $roleid param'.
            $sendusers = get_active_role_users($activityroleids, $context);

            // Filter user list,
            // see: https://docs.moodle.org/dev/Availability_API.
            $info = new \core_availability\info_module($cm);
            $sendusers = $info->filter_user_list($sendusers);
        }

        $reminder->set_activity($event->modulename, $activityobj);
        $filteredusers = $reminder->filter_authorized_users($sendusers, $calltype);
        return new reminder_ref($reminder, $filteredusers);
    }
    return null;
}

function process_unknown_event($event, $aheadday, $activityroleids=null, $showtrace=true) {
    global $DB, $PAGE;
    if (isemptystring($event->modulename)) {
        $showtrace && mtrace("  [Local Reminder] Unknown event type [$event->eventtype]");
        return null;
    }

    $course = $DB->get_record('course', array('id' => $event->courseid));
    $cm = get_coursemodule_from_instance($event->modulename, $event->instance, $event->courseid);

    if (!empty($course) && !empty($cm)) {
        $activityobj = fetch_module_instance($event->modulename, $event->instance, $event->courseid, $showtrace);
        $context = context_module::instance($cm->id);
        $PAGE->set_context($context);
        $sendusers = get_active_role_users($activityroleids, $context);

        if (strcmp($event->eventtype, 'gradingdue') == 0 && isset($context)) {
            $filteredusers = array();
            foreach ($sendusers as $guser) {
                if (has_capability('mod/assign:grade', $context, $guser)) {
                    $filteredusers[] = $guser;
                }
            }
            $sendusers = $filteredusers;
        }
        $reminder = new due_reminder($event, $course, $context, $cm, $aheadday);
        $reminder->set_activity($event->modulename, $activityobj);
        return new reminder_ref($reminder, $sendusers);
    }
    return null;
}

function process_course_event($event, $aheadday, $courseroleids=null, $showtrace=true) {
    global $DB, $PAGE;

    $course = $DB->get_record('course', array('id' => $event->courseid));
    $coursesettings = $DB->get_record('local_reminders_course', array('courseid' => $event->courseid));
    if (isset($coursesettings->status_course) && $coursesettings->status_course == 0) {
        $showtrace && mtrace("  [Local Reminder] Reminders for course events has been restricted.");
        return null;
    }

    if (!empty($course)) {
        $context = context_course::instance($course->id);
        $PAGE->set_context($context);
        $roleusers = get_role_users($courseroleids, $context, true, 'ra.id as ra_id, u.*');
        $senduserids = array_map(
        function($u) {
            return $u->id;
        }, $roleusers);
        $sendusers = array_combine($senduserids, $roleusers);

        $reminder = new course_reminder($event, $course, $aheadday);
        return new reminder_ref($reminder, $sendusers);
    }
    return null;
}

function process_group_event($event, $aheadday, $showtrace=true) {
    global $DB;

    $group = $DB->get_record('groups', array('id' => $event->groupid));
    if (!empty($group)) {
        $coursesettings = $DB->get_record('local_reminders_course', array('courseid' => $group->courseid));
        if (isset($coursesettings->status_group) && $coursesettings->status_group == 0) {
            $showtrace && mtrace("  [Local Reminder] Reminders for group events has been restricted in the configs.");
            return null;
        }

        $reminder = new group_reminder($event, $group, $aheadday);

        // Add module details, if this event is a mod type event.
        if (!isemptystring($event->modulename) && $event->courseid > 0) {
            $activityobj = fetch_module_instance($event->modulename, $event->instance, $event->courseid, $showtrace);
            $reminder->set_activity($event->modulename, $activityobj);
        }
        $sendusers = get_users_in_group($group);
        return new reminder_ref($reminder, $sendusers);
    }
}

function process_user_event($event, $aheadday) {
    global $DB;

    $user = $DB->get_record('user', array('id' => $event->userid));

    if (!empty($user)) {
        $reminder = new user_reminder($event, $user, $aheadday);
        $sendusers[] = $user;
        return new reminder_ref($reminder, $sendusers);
    }
    return null;
}

function process_site_event($event, $aheadday) {
    global $DB;

    $reminder = new site_reminder($event, $aheadday);
    $sendusers = $DB->get_records_sql("SELECT *
        FROM {user}
        WHERE id > 1 AND deleted=0 AND suspended=0 AND confirmed=1;");
    return new reminder_ref($reminder, $sendusers);
}

/**
 * Returns course roles and activity role ids globally defined in moodle.
 *
 * @return array containing two elements course roles ids and activity role ids.
 */
function get_roles_for_reminders() {
    global $CFG;

    $allroles = get_all_roles();
    $courseroleids = array();
    $activityroleids = array();
    if (!empty($allroles)) {
        $flag = 0;
        foreach ($allroles as $arole) {
            $roleoptionactivity = $CFG->local_reminders_activityroles;
            if (isset($roleoptionactivity[$flag]) && $roleoptionactivity[$flag] == '1') {
                $activityroleids[] = $arole->id;
            }
            $roleoption = $CFG->local_reminders_courseroles;
            if (isset($roleoption[$flag]) && $roleoption[$flag] == '1') {
                $courseroleids[] = $arole->id;
            }
            $flag++;
        }
    }
    return array(
        $courseroleids,
        $activityroleids
    );
}

/**
 * This function formats the due time of the event appropiately. If this event
 * has a duration then formatted time will be [starttime]-[endtime].
 *
 * @param object $user user object
 * @param object $event event instance
 * @param array $tzstyle css style string for tz
 * @return string formatted time string
 */
function format_event_time_duration($user, $event, $tzstyle=null) {
    $followedtimeformat = get_string('strftimedaydate', 'langconfig');
    $usertimeformat = get_correct_timeformat_user($user);

    $tzone = 99;
    if (isset($user) && !empty($user)) {
        $tzone = core_date::get_user_timezone($user);
    }

    $addflag = false;
    $formattedtimeprefix = userdate($event->timestart, $followedtimeformat, $tzone);
    $formattedtime = userdate($event->timestart, $usertimeformat, $tzone);
    $sdate = usergetdate($event->timestart, $tzone);
    if ($event->timeduration > 0) {
        $etime = $event->timestart + $event->timeduration;
        $ddate = usergetdate($etime, $tzone);

        // Falls in the same day.
        if ($sdate['year'] == $ddate['year'] && $sdate['mon'] == $ddate['mon'] && $sdate['mday'] == $ddate['mday']) {
            // Bug fix for not correctly displaying times in incorrect formats.
            // Issue report: https://tracker.moodle.org/browse/CONTRIB-3647?focusedCommentId=408657.
            $formattedtime .= ' - '.userdate($etime, $usertimeformat, $tzone);
            $addflag = true;
        } else {
            $formattedtime .= ' - '.
                userdate($etime, $followedtimeformat, $tzone)." ".
                userdate($etime, $usertimeformat, $tzone);
        }

        if ($addflag) {
            $formattedtime = $formattedtimeprefix.'  ['.$formattedtime.']';
        } else {
            $formattedtime = $formattedtimeprefix.' '.$formattedtime;
        }

    } else {
        $formattedtime = $formattedtimeprefix.' '.$formattedtime;
    }

    $tzstr = local_reminders_tz_info::get_human_readable_tz($tzone);
    if (!isemptystring($tzstyle)) {
        $tzstr = '<span style="'.$tzstyle.'">'.$tzstr.'</span>';
    } else {
        $tzstr = '<span style="font-size:13px;color: #888;">'.$tzstr.'</span>';
    }
    return $formattedtime.' &nbsp;&nbsp;'.$tzstr;
}

/**
 * This function would return time formats relevent for the given user.
 * Sometimes a user might have changed time display format in his/her preferences.
 *
 */
function get_correct_timeformat_user($user) {
    static $langtimeformat = null;
    if ($langtimeformat === null) {
        $langtimeformat = get_string('strftimetime', 'langconfig');
    }

    // We get user time formattings... if such exist, will return non-empty value.
    $utimeformat = get_user_preferences('calendar_timeformat', '', $user);
    if (empty($utimeformat)) {
        $utimeformat = get_config(null, 'calendar_site_timeformat');
    }
    return empty($utimeformat) ? $langtimeformat : $utimeformat;
}

/**
 * Returns array of users active (not suspended) in the provided contexts and
 * at the same time belongs to the given roles.
 *
 * @param $activityroleids role ids
 * @param $context context to search for users
 * @return array of user records
 */
function get_active_role_users($activityroleids, $context) {
    return get_role_users($activityroleids, $context, true, 'ra.id, u.*',
                    null, false, '', '', '',
                    'ue.status = :userenrolstatus',
                    array('userenrolstatus' => ENROL_USER_ACTIVE));
}

/**
 * Returns all users belong to the given group.
 *
 * @param $group group object as received from db.
 * @return array users in an array
 */
function get_users_in_group($group) {
    global $DB;

    $sendusers = array();
    $groupmemberroles = groups_get_members_by_role($group->id, $group->courseid, 'u.id');
    if ($groupmemberroles) {
        foreach ($groupmemberroles as $roleid => $roledata) {
            foreach ($roledata->users as $member) {
                $sendusers[] = $DB->get_record('user', array('id' => $member->id));
            }
        }
    }
    return $sendusers;
}

/**
 * Returns true if input string is empty/whitespaces only, otherwise false.
 *
 * @param type $str string
 *
 * @return boolean true if string is empty or whitespace
 */
function isemptystring($str) {
    return !isset($str) || empty($str) || trim($str) === '';
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
 * @param boolean $showtrace optional to print trace logs.
 * @return individual module instance (a quiz, a assignment, etc).
 *          If fails returns null
 */
function fetch_module_instance($modulename, $instance, $courseid=0, $showtrace=true) {
    global $DB;

    $params = array('instance' => $instance, 'modulename' => $modulename);

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
        $showtrace && mtrace('  [Local Reminder - ERROR] Failed to fetch module instance! '.$mex.getMessage);
        return null;
    }
}

/**
 * Returns the from user instance which should be send notifications.
 *
 * @return object from user object.
 */
function get_from_user() {
    global $CFG;

    $fromuser = core_user::get_noreply_user();
    if (isset($CFG->local_reminders_sendasname) && !empty($CFG->local_reminders_sendasname)) {
        $fromuser->firstname = $CFG->local_reminders_sendasname;
    }
    if (isset($CFG->local_reminders_sendas) && $CFG->local_reminders_sendas == REMINDERS_SEND_AS_ADMIN) {
        $fromuser = get_admin();
    }
}

/**
 * Reminder specific timezone data holder.
 * Note: you must have at least Moodle 3.5 or higher.
 */
class local_reminders_tz_info extends \core_date {
    protected static $mapping;

    public static function get_human_readable_tz($tz) {
        if (!isset(self::$mapping)) {
            static::load_tz_info();
        }

        if (is_numeric($tz)) {
            return static::get_localised_timezone($tz);
        }
        if (array_key_exists($tz, self::$mapping)) {
            return self::$mapping[$tz];
        }
        return static::get_localised_timezone($tz);
    }

    private static function load_tz_info() {
        self::$mapping = array();
        foreach (static::$badzones as $detailname => $abbr) {
            if (!is_numeric($detailname)) {
                self::$mapping[$abbr] = $detailname;
            }
        }
    }
}

/**
 * Reminder reference class.
 */
class reminder_ref {
    protected $reminder;
    protected $sendusers;

    public function __construct($reminder, $sendusers) {
        $this->reminder = $reminder;
        $this->sendusers = $sendusers;
    }

    public function get_total_users_to_send() {
        return count($this->sendusers);
    }

    public function get_event_to_send($fromuser, $touser) {
        return $this->reminder->get_sending_event($fromuser, $touser);
    }

    public function get_updating_send_event($changetype, $fromuser, $touser) {
        return $this->reminder->get_updating_event_message($changetype, $fromuser, $touser);
    }

    public function get_sending_users() {
        return $this->sendusers;
    }

    public function cleanup() {
        unset($this->sendusers);
        if (isset($this->reminder)) {
            $this->reminder->cleanup();
        }
    }
}
