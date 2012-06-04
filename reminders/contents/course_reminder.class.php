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

/**
 * Class to specify the reminder message object for site (global) events.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_reminder extends reminder {
    
    private $course;
    
    public function __construct($event, $course, $notificationstyle = 1) {
        parent::__construct($event, $notificationstyle);
        $this->course = $course;
    }
    
    public function get_message_html() {
        $htmlmail = $this->get_html_header().'\n';
        $htmlmail .= '<body id=\"email\">\n<div>\n';
        $htmlmail .= '<table cellspacing="0" cellpadding="8" border="0" summary="" style="'.$this->bodycssstyle.'">';
        $htmlmail .= '<tr><td><h3 style="'.$this->titlestyle.'">'.$this->get_message_title().'</h3></td></tr>';
        $htmlmail .= '<tr><td>When</td><td>'.$this->format_event_time_duration().'</td></tr>';
        $htmlmail .= '<tr><td>User</td><td>'.$this->course->shortname.' '.$this->user->fullname.'</td></tr>';
        $htmlmail .= '<tr><td>Description</td><td>'.$event->description.'</td></tr>';
        $htmlmail .= $this->get_html_footer();
        $htmlmail .= '</table>\n</body>\n</html>';
    }
    
    public function get_message_plaintext() {
        
    }

    protected function get_message_provider() {
        return 'reminders_course';
    }

    public function get_message_title() {
        return $course->shortname.' - '.$event->name;
    }


}