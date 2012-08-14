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

/**
 * Class to specify the reminder message object for course events.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_reminder extends reminder {
    
    protected $course;
    
    public function __construct($event, $course, $aheaddays = 1) {
        parent::__construct($event, $aheaddays);
        $this->course = $course;
    }
    
    protected function generate_event_link() {
        $params = array('view' => 'day', 'course' => $this->course->id, 'cal_d' => date('j', $this->event->timestart), 
            'cal_m' => date('n', $this->event->timestart), 'cal_y' => date('Y', $this->event->timestart));
        $calurl = new moodle_url('/calendar/view.php', $params);
        $calurl->set_anchor('event_'.$this->event->id);
        
        return $calurl->out(false);
    }
    
    protected function get_content_rows() {
        global $CFG;
        $rows = parent::get_content_rows();
        
        $row = new reminder_content_row();
        $row->add_column(new reminder_content_column(get_string('contenttypecourse', 'local_reminders')));
        $row->add_column(new reminder_content_column(
            html_writer::link($CFG->wwwroot.'/course/view.php?id='.$this->course->id, $this->course->fullname, array('target' => '_blank'))));
        $rows[] = $row;
        
        return $rows;
    }

    public function get_message_plaintext() {
        $text  = $this->get_message_title().' ['.$this->aheaddays.' day(s) to go]\n';
        $text .= get_string('contentwhen', 'local_reminders').': '.$this->format_event_time_duration().'\n';
        $text .= get_string('contenttypecourse', 'local_reminders').': '.$this->course->fullname.'\n';
        $text .= get_string('contentdescription', 'local_reminders').': '.$this->event->description.'\n';
        
        return $text;
    }

    protected function get_message_provider() {
        return 'reminders_course';
    }

    public function get_message_title() {
        return $this->course->shortname.' - '.$this->event->name;
    }

    public function get_custom_headers() {
        $headers = parent::get_custom_headers();
        
        $headers[] = 'X-Course-Id: '.$this->course->id;
        $headers[] = 'X-Course-Name: '.format_string($this->course->fullname, true);
        
        return $headers;
    }
}