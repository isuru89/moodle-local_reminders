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

defined('MOODLE_INTERNAL') || die;

/**
 * Abstract class for formatting reminder message based on activity type.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class local_reminder_activity_handler {

    /**
     * This function will format/append reminder messages with necessary info
     * based on constraints in that activity instance
     *
     */
    public function append_info(&$htmlmail, $modulename, $activity, $user=null, $event=null) {
        // Do nothing.
    }

    /**
     * Returns associated description of the given activity.
     */
    public abstract function get_description($activity, $event);

    /**
     * Filter out users who still does not have completed this activity.
     *
     * @param array $users user array to check.
     * @param object $course course instance belong to.
     * @param object $coursemodule course module instance.
     * @param object $coursemodulecontext course module context instance.
     * @return array array of filtered users.
     */
    public function filter_incompleted_users($users, $course, $coursemodule, $coursemodulecontext) {
        return $users;
    }

    /**
     * formats given date and time based on given user's timezone
     */
    protected function format_datetime($datetime, $user, $reminder) {
        $tzone = 99;
        if (isset($user) && !empty($user)) {
            $tzone = core_date::get_user_timezone($user);
        }

        $daytimeformat = get_string('strftimedaydate', 'langconfig');
        $utimeformat = get_correct_timeformat_user($user);
        return userdate($datetime, $daytimeformat, $tzone).
            ' '.userdate($datetime, $utimeformat, $tzone).
            ' &nbsp;&nbsp;<span style="'.$reminder->tzshowstyle.'">'.
            local_reminders_tz_info::get_human_readable_tz($tzone).'</span>';
    }

}

class local_reminder_quiz_handler extends local_reminder_activity_handler {

    public function get_description($activity, $event) {
        if (isset($activity->timeopen)) {
            $utime = time();
            if ($utime > $activity->timeopen) {
                return $activity->intro;
            }
        }
        return null;
    }
}

class local_reminder_assign_handler extends local_reminder_activity_handler {

    /**
     * Filter out users who still does not have submitted assignment.
     *
     * @param array $users user array to check.
     * @param object $course course instance belong to.
     * @param object $coursemodule course module instance.
     * @param object $coursemodulecontext course module context instance.
     * @return array array of filtered users.
     */
    public function filter_incompleted_users($users, $course, $coursemodule, $coursemodulecontext) {
        $filteredusers = array();

        foreach ($users as $auser) {
            $status = assign_get_completion_state($course, $coursemodule, $auser->id, false) ? 1 : 0;
            mtrace("  $auser->id = $status");
            if (!$status) {
                $filteredusers[] = $auser;
            }
        }
        return $filteredusers;
    }


    public function append_info(&$htmlmail, $modulename, $activity, $user=null, $event=null, $reminder=null) {
        if (isset($activity->cutoffdate) && $activity->cutoffdate > 0) {
            $htmlmail .= $reminder->write_table_row(
                get_string('cutoffdate', 'assign'),
                $this->format_datetime($activity->cutoffdate, $user, $reminder));
        }
    }

    public function get_description($activity, $event) {
        if (isset($activity->alwaysshowdescription)) {
            $utime = time();
            if ($activity->alwaysshowdescription > 0 || $utime > $activity->allowsubmissionsfromdate) {
                return $event->description;
            }
        }
        return null;
    }
}

