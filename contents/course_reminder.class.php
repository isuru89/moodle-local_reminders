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
 * Course event reminder handler.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

global $CFG;

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');

/**
 * Class to specify the reminder message object for course events.
 *
 * @package    local_reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_reminder extends local_reminder {

    /**
     * Course reference.
     *
     * @var object
     */
    protected $course;

    /**
     * Creates a new course reminder instance.
     *
     * @param object $event calendar event.
     * @param object $course course instance.
     * @param integer $aheaddays number of days ahead.
     * @param object $custom_time contains the custom time value and unit (if configured). 
     */
    public function __construct($event, $course, $aheaddays = 1, $custom_time = null) {
        parent::__construct($event, $aheaddays, $custom_time);
        $this->course = $course;
    }

    /**
     * Generates a message content as a HTML for course email.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed)
     * @param stdClass $ctxinfo additional context info needed to process.
     * @return string Message content as HTML text.
     */
    public function get_message_html($user=null, $changetype=null, $ctxinfo=null) {
        $htmlmail = $this->get_html_header();
        $htmlmail .= html_writer::start_tag('body', array('id' => 'email'));
        $htmlmail .= $this->get_reminder_header();
        $htmlmail .= html_writer::start_tag('div');
        $htmlmail .= html_writer::start_tag('table',
                array('cellspacing' => 0, 'cellpadding' => 8, 'style' => $this->tbodycssstyle));

        $contenttitle = $this->get_message_title();
        if (!isemptystring($changetype)) {
            $titleprefixlangstr = get_string('calendarevent'.strtolower($changetype).'prefix', 'local_reminders');
            $contenttitle = "[$titleprefixlangstr]: $contenttitle";
        }
        $htmlmail .= html_writer::start_tag('tr');
        $htmlmail .= html_writer::start_tag('td', array('colspan' => 2));
        $htmlmail .= html_writer::link($this->generate_event_link(),
                html_writer::tag('h3', $contenttitle, array('style' => $this->titlestyle)),
                array('style' => 'text-decoration: none'));
        $htmlmail .= html_writer::end_tag('td').html_writer::end_tag('tr');

        $htmlmail .= $this->write_table_row(get_string('contentwhen', 'local_reminders'),
            format_event_time_duration($user, $this->event));
        $htmlmail .= $this->write_location_info($this->event);

        $htmlmail .= $this->write_table_row(get_string('contenttypecourse', 'local_reminders'), $this->course->fullname);

        $description = $this->event->description;
        $htmlmail .= $this->write_description($description, $this->event);

        $htmlmail .= $this->get_html_footer();
        return $htmlmail.html_writer::end_tag('table').
            html_writer::end_tag('div').
            html_writer::end_tag('body').
            html_writer::end_tag('html');
    }

    /**
     * Generates a message content as a plain-text for course.
     *
     * @param object $user The user object
     * @param object $changetype change type (add/update/removed)
     * @return string Message content as plain-text.
     */
    public function get_message_plaintext($user=null, $changetype=null) {
        if ($this->aheaddays != 0) {
            $text  = $this->get_message_title().' ['.$this->pluralize($this->aheaddays, ' day').' to go]'."\n";
        } else {
            $text  = $this->get_message_title().' ['.$this->pluralize($this->custom_time->value, ' ' . $this->custom_time->unit).' to go]'."\n";
        }
        $text .= get_string('contentwhen', 'local_reminders').': '.$this->get_tzinfo_plain($user, $this->event)."\n";
        $text .= get_string('contenttypecourse', 'local_reminders').': '.$this->course->fullname."\n";
        $text .= get_string('contentdescription', 'local_reminders').': '.$this->event->description."\n";

        return $text;
    }

    /**
     * Returns 'reminders_course' name.
     *
     * @return string Message provider name
     */
    protected function get_message_provider() {
        return 'reminders_course';
    }

    /**
     * Generates a message title for the course reminder.
     *
     * @param string $type type of message to be send (null=reminder cron)
     * @return string Message title as a plain-text.
     */
    public function get_message_title($type=null) {
        return '('.$this->course->shortname.') '.$this->event->name;
    }

    /**
     * Adds course id and name to header.
     *
     * @return array additional headers.
     */
    public function get_custom_headers() {
        $headers = parent::get_custom_headers();

        $headers[] = 'X-Course-Id: '.$this->course->id;
        $headers[] = 'X-Course-Name: '.format_string($this->course->fullname, true);

        return $headers;
    }
}
