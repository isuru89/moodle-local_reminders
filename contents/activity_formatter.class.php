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
abstract class activity_formatter {

    /**
     * This function will format/append reminder messages with necessary info
     * based on constraints in that activity instance
     *
     */
    public abstract function append_info(&$htmlmail, $modulename, $activity, $user=null, $event=null);

    /**
     * formats given date and time based on given user's timezone
     */
    protected function format_datetime($datetime, $user) {
        $tzone = 99;
        if (isset($user) && !empty($user)) {
            $tzone = core_date::get_user_timezone($user);
        }

        $daytimeformat = get_string('strftimedaydate', 'langconfig');
        $utimeformat = self::get_correct_timeformat_user($user);
        return userdate($datetime, $daytimeformat, $tzone).' '.userdate($datetime, $utimeformat, $tzone);
    }

    /**
     * This function would return time formats relevent for the given user.
     * Sometimes a user might have changed time display format in his/her preferences.
     *
     */
    private function get_correct_timeformat_user($user) {
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

}

class quiz_formatter extends activity_formatter {

    public function append_info(&$htmlmail, $modulename, $activity, $user=null, $event=null, $reminder=null) {
        if (isset($activity->timeopen)) {
            $utime = time();
            if ($utime > $activity->timeopen) {
                $htmlmail .= $reminder->write_table_row(
                    get_string('contentdescription', 'local_reminders'),
                    $activity->intro);
            }
        }
    }
}

class assign_formatter extends activity_formatter {

    public function append_info(&$htmlmail, $modulename, $activity, $user=null, $event=null, $reminder=null) {
        if (isset($activity->alwaysshowdescription)) {
            $utime = time();
            if ($activity->alwaysshowdescription > 0 || $utime > $activity->allowsubmissionsfromdate) {
                $htmlmail .= $reminder->write_table_row(
                    get_string('contentdescription', 'local_reminders'),
                    $event->description);
            }
        }
        if (isset($activity->cutoffdate) && $activity->cutoffdate > 0) {
            $htmlmail .= $reminder->write_table_row(
                get_string('cutoffdate', 'assign'),
                $this->format_datetime($activity->cutoffdate, $user));
        }
    }
}

