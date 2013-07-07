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
            $tzone = $user->timezone;
        }
        
        return userdate($datetime, '', $tzone);
    }
    
}

class quiz_formatter extends activity_formatter {
    
    public function append_info(&$htmlmail, $modulename, $activity, $user=null, $event=null) {
        if (isset($activity->timeopen)) {
            $utime = time();
            if ($utime > $activity->timeopen) {
                $htmlmail .= html_writer::start_tag('tr');
                $htmlmail .= html_writer::tag('td', get_string('contentdescription', 'local_reminders'));
                $htmlmail .= html_writer::tag('td', $activity->intro);
                $htmlmail .= html_writer::end_tag('tr');
            }
        }
    }
}

class assign_formatter extends activity_formatter {
    
    public function append_info(&$htmlmail, $modulename, $activity, $user=null, $event=null) {
        if (isset($activity->alwaysshowdescription)) {
            $utime = time();
            if ($activity->alwaysshowdescription > 0 || $utime > $activity->allowsubmissionsfromdate) {
                $htmlmail .= html_writer::start_tag('tr');
                $htmlmail .= html_writer::tag('td', get_string('contentdescription', 'local_reminders'));
                $htmlmail .= html_writer::tag('td', $event->description);
                $htmlmail .= html_writer::end_tag('tr');
            } 
        }
        if (isset($activity->cutoffdate) && $activity->cutoffdate > 0) {
            $htmlmail .= html_writer::start_tag('tr');
            $htmlmail .= html_writer::tag('td', get_string('cutoffdate', 'assign'));
            $htmlmail .= html_writer::tag('td', $this->format_datetime($activity->cutoffdate, $user));
            $htmlmail .= html_writer::end_tag('tr');
        }
    }
}

