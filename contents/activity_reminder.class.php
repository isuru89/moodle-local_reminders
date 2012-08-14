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
 * Class to specify the reminder message object for activity events.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class activity_reminder extends course_reminder {
    
    private $cm;
    
    public function __construct($event, $course, $cm, $aheaddays = 1) {
        parent::__construct($event, $course, $aheaddays);
        $this->cm = $cm;
    }
    
    protected function get_content_rows() {
        $rows = parent::get_content_rows();
        
        $row = new reminder_content_row();
        $row->add_column(new reminder_content_column(get_string('contenttypeactivity', 'local_reminders')));
        $row->add_column(new reminder_content_column(
                html_writer::link($this->cm->get_url(), $this->cm->get_context_name(), array('target' => '_blank'))));
        $rows[] = $row;
        
        return $rows;
    }

    public function get_message_plaintext() {
        $text  = $this->get_message_title().' ['.$this->aheaddays.' day(s) to go]\n';
        $text .= get_string('contentwhen', 'local_reminders').': '.$this->format_event_time_duration().'\n';
        $text .= get_string('contenttypecourse', 'local_reminders').': '.$this->course->fullname.'\n';
        $text .= get_string('contenttypeactivity', 'local_reminders').': '.$this->cm->get_context_name().'\n';
        $text .= get_string('contentdescription', 'local_reminders').': '.$this->event->description.'\n';
        
        return $text;
    }

    protected function get_message_provider() {
        return 'reminders_activity';
    }

    public function get_message_title() {
        return $this->course->shortname.' - '.$this->event->name;
    }

    public function get_custom_headers() {
        $headers = parent::get_custom_headers();
        
        $headers[] = 'X-Activity-Id: '.$this->cm->id;
        $headers[] = 'X-Activity-Name: '.$this->cm->get_context_name();
        
        return $headers;
    }

}