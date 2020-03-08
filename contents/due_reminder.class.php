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

global $CFG;

require_once($CFG->dirroot . '/local/reminders/reminder.class.php');
require_once($CFG->dirroot . '/local/reminders/contents/activity_handlers.class.php');

/**
 * Class to specify the reminder message object for due events.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class due_reminder extends course_reminder {

    private $cm;
    private $activityobj;
    private $modname;

    public function __construct($event, $course, $cm, $aheaddays = 1) {
        parent::__construct($event, $course, $aheaddays);
        $this->cm = $cm;
    }

    /**
     * Set activity instance if there is any
     * @param type $activity activity instance
     */
    public function set_activity($modulename, $activity) {
        $this->activityobj = $activity;
        $this->modname = $modulename;
    }

    /**
     * Cleanup this reminder instance.
     */
    public function cleanup() {
        parent::cleanup();

        if (isset($this->activityobj)) {
            unset($this->activityobj);
        }
    }

    public function get_message_html($user=null, $changetype=null) {
        $htmlmail = $this->get_html_header();
        $htmlmail .= html_writer::start_tag('body', array('id' => 'email'));
        $htmlmail .= html_writer::start_tag('div');
        $htmlmail .= html_writer::start_tag('table',
                array('cellspacing' => 0, 'cellpadding' => 8, 'style' => $this->tbodycssstyle));

        $contenttitle = $this->get_message_title();
        if (!isemptystring($changetype)) {
            $contenttitle = "[$changetype]: $contenttitle";
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

        $activitylink = html_writer::link($this->cm->get_url(), $this->cm->get_context_name(), array('target' => '_blank'));
        $htmlmail .= $this->write_table_row(get_string('contenttypeactivity', 'local_reminders'), $activitylink);

        $formattercls = null;
        if (!empty($this->modname) && !empty($this->activityobj)) {
            $clsname = 'local_reminder_'.$this->modname.'_handler';
            if (class_exists($clsname)) {
                $formattercls = new $clsname;
                $formattercls->append_info($htmlmail, $this->modname, $this->activityobj, $user, $this->event, $this);
            }
        }

        $description = isset($formattercls) ? $formattercls->get_description($this->activityobj, $this->event) : '';
        $htmlmail .= $this->write_description($description, $this->event);

        $htmlmail .= $this->get_html_footer();
        return $htmlmail.html_writer::end_tag('table').
            html_writer::end_tag('div').
            html_writer::end_tag('body').
            html_writer::end_tag('html');
    }

    public function get_message_plaintext($user=null, $changetype=null) {
        $text  = $this->get_message_title().' ['.$this->aheaddays.' day(s) to go]'."\n";
        $text .= get_string('contentwhen', 'local_reminders').': '.format_event_time_duration($user, $this->event)."\n";
        $text .= get_string('contenttypecourse', 'local_reminders').': '.$this->course->fullname."\n";
        $text .= get_string('contenttypeactivity', 'local_reminders').': '.$this->cm->get_context_name()."\n";
        $text .= get_string('contentdescription', 'local_reminders').': '.$this->event->description."\n";

        return $text;
    }

    protected function get_message_provider() {
        return 'reminders_due';
    }

    public function get_message_title($type=null) {
        $title = '('.$this->course->shortname;
        if (!empty($this->cm)) {
            $title .= '-'.get_string('modulename', $this->event->modulename);
        }
        return $title.') '.$this->event->name;
    }

    public function get_custom_headers() {
        $headers = parent::get_custom_headers();

        $headers[] = 'X-Activity-Id: '.$this->cm->id;
        $headers[] = 'X-Activity-Name: '.$this->cm->get_context_name();

        return $headers;
    }

}
