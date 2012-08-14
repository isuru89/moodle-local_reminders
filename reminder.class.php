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
 * Abstract class for reminder object.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class reminder {
    
    protected $aheaddays;
    protected $notification = 1;
    protected $event;
    
    protected $tbodycssstyle = 'width:100%;font-family:Tahoma,Arial,Sans-serif;border-width:1px 2px 2px 1px;border:1px Solid #ccc';
    protected $titlestyle = 'padding:0 0 6px 0;margin:0;font-family:Arial,Sans-serif;font-size:16px;font-weight:bold;color:#222';
    protected $footerstyle = 'background-color:#f6f6f6;color:#888;border-top:1px Solid #ccc;font-family:Arial,Sans-serif;font-size:11px';
    
    public function __construct($event, $aheaddays = 1) {
        $this->event = $event;
        $this->aheaddays = $aheaddays;
    }
    
    /**
     * Gets the header content of the e-mail message.
     */
    protected function get_html_header() {
        return html_writer::tag('head', '');
    }
    
    /**
     * Gets the footer content of the e-mail message.
     */
    protected function get_html_footer() {
        global $CFG;
        
        $footer  = html_writer::start_tag('tr');
        $footer .= html_writer::start_tag('td', array('style' => $this->footerstyle, 'colspan' => 2));
        $footer .= get_string('reminderfrom', 'local_reminders').' ';
        $footer .= html_writer::link($CFG->wwwroot.'/calendar/index.php', get_string('calendarname', 'local_reminders'),
                array('target' => '_blank'));
        $footer .= html_writer::end_tag('td').html_writer::end_tag('tr');

        return $footer;
    }
    
    /**
     * Returns the correct link for the calendar event.
     * 
     * @return string complete url for the event
     */
    protected function generate_event_link() {       
        $params = array('view' => 'day', 'cal_d' => date('j', $this->event->timestart), 
            'cal_m' => date('n', $this->event->timestart), 'cal_y' => date('Y', $this->event->timestart));
        $calurl = new moodle_url('/calendar/view.php', $params);
        $calurl->set_anchor('event_'.$this->event->id);
        
        return $calurl->out(false);
    }
    
    /**
     * This function formats the due time of the event appropiately. If this event
     * has a duration then formatted time will be [starttime]-[endtime].
     * 
     * @return string formatted time string
     */
    protected function format_event_time_duration() {
        $followedtimeformat = get_string('strftimedatetime', 'langconfig');

        $formattedtime = userdate($this->event->timestart);
        $sdate = usergetdate($this->event->timestart);
        if ($this->event->timeduration > 0) {
            $ddate = usergetdate($this->event->timestart + $this->event->timeduration);
            if ($sdate['year'] == $ddate['year'] && $sdate['mon'] == $ddate['mon'] && $sdate['mday'] == $ddate['mday']) {
                $followedtimeformat = get_string('strftimetime', 'langconfig');
            }
            $formattedtime .= ' - '.userdate($this->event->timestart + $this->event->timeduration, $followedtimeformat);
        }
        return $formattedtime;
    }
    
    /**
     * This function returns an array of reminder_content_row objects
     * which will be  printed out in html content of the final message.
     * 
     * @return array of reminder_content_row objects.
     */
    protected function get_content_rows() {
        $rows = array();
        
        $row = new reminder_content_row();
        $row->add_column(new reminder_content_column(get_string('contentwhen', 'local_reminders'), array('width' => '25%')));
        $row->add_column(new reminder_content_column($this->format_event_time_duration()));
        $rows[] = $row;
        
        $row = new reminder_content_row();
        $row->add_column(new reminder_content_column(get_string('daysremaining', 'local_reminders')));
        $row->add_column(new reminder_content_column($this->aheaddays.' '.get_string('days', 'local_reminders')));
        $rows[] = $row;
        
        return $rows;
    }
    
    /**
     * This function setup the corresponding message provider for each
     * reminder type. It would be called everytime at the constructor.
     * 
     * @return string Message provider name
     */
    protected abstract function get_message_provider();
    
    /**
     * Generates a message content as a HTML. Suitable for email messages.
     *
     * @param object $event The event object
     * @return string Message content as HTML text.
     */
    public function get_message_html() {
        $htmlmail = html_writer::start_tag('html');
        $htmlmail .= $this->get_html_header();
        $htmlmail .= html_writer::start_tag('body', array('id' => 'email'));
        $htmlmail .= html_writer::start_tag('div');
        $htmlmail .= html_writer::start_tag('table', array('cellspacing' => 0, 'cellpadding' => 8, 'style' => $this->tbodycssstyle));
        
        $htmlmail .= html_writer::start_tag('tr');
        $htmlmail .= html_writer::start_tag('td', array('colspan' => 2));
        $htmlmail .= html_writer::link($this->generate_event_link(), 
                html_writer::tag('h3', $this->get_message_title(), array('style' => $this->titlestyle)), 
                array('style' => 'text-decoration: none'));
        $htmlmail .= html_writer::end_tag('td').html_writer::end_tag('tr');
        
        $rows = $this->get_content_rows();
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $htmlmail .= $row->html_out();
            }
        }
        
        $htmlmail .= html_writer::start_tag('tr');
        $htmlmail .= html_writer::tag('td', get_string('contentdescription', 'local_reminders'));
        $htmlmail .= html_writer::tag('td', html_writer::tag('div', $this->event->description));
        $htmlmail .= html_writer::end_tag('tr');
        
        $htmlmail .= $this->get_html_footer();
        $htmlmail .= html_writer::end_tag('table').html_writer::end_tag('div').html_writer::end_tag('body').
                html_writer::end_tag('html');
        return $htmlmail;
    }
    
    /**
     * Generates a message content as a plain-text. Suitable for popup messages.
     *
     * @param object $event The event object
     * @return string Message content as plain-text.
     */
    public abstract function get_message_plaintext();
    
    /**
     * Generates a message title for the reminder. Used for all message types.
     * 
     * @return string Message title as a plain-text. 
     */
    public abstract function get_message_title();
    
    /**
     * Gets an array of custom headers for the reminder message, specially
     * for e-mails. For e-mails they will be easier to track when
     * several e-mail reminders are received for a particular event. <br>
     * If no header is wanted, just simply returns an empty array.
     *  
     * @return array array of strings containing header attributes.
     */
    public function get_custom_headers() {
        global $CFG;
        $urlinfo = parse_url($CFG->wwwroot);
        $hostname = $urlinfo['host'];
        
        return array('Message-ID: <moodlereminder'.$this->event->id.'@'.$hostname.'>');
    }
    
    /**
     * @return object a message object which will be sent to the messaging API
     */
    public function create_reminder_message_object($admin=null) {  
        if ($admin == null) {
            $admin = get_admin();
        }
        
        $contenthtml = $this->get_message_html();
        $titlehtml = $this->get_message_title();
        $subjectprefix = get_string('titlesubjectprefix', 'local_reminders');
        $subject = $titlehtml;
        if (strlen(trim($subjectprefix)) > 0) {
            $subject = $subjectprefix.': '.$subject;
        } 
        
        $cheaders = $this->get_custom_headers();
        if (!empty($cheaders)) {
            $admin->customheaders = $cheaders;
        }
        
        $eventdata = new stdClass();
        $eventdata->component           = 'local_reminders';   // plugin name
        $eventdata->name                = $this->get_message_provider();     // message interface name
        $eventdata->userfrom            = $admin;
        //$eventdata->userto              = 3;
        $eventdata->subject             = $subject;    // message title
        $eventdata->fullmessage         = $this->get_message_plaintext(); 
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = $contenthtml;
        $eventdata->smallmessage        = $titlehtml . ' - ' . $contenthtml;
        $eventdata->notification        = $this->notification;
        
        return $eventdata;
    }
    
}

/**
 * Represents a event detail row for a reminder message. A row can consist of several
 * columns to represent these data.
 */
class reminder_content_row {
    
    /**
     * Set of columns for this row.
     * @var array of reminder_content_column 
     */
    private $columns = array();
    
    /**
     * Adds a new column to this row.
     * @param reminder_content_column $column column object
     */
    public function add_column(reminder_content_column $column) {
        $this->columns[] = $column;
    }
    
    /**
     * Returns the whole content of the row as a HTML. 
     * @return string html string content of the row
     */
    public function html_out() {
        if (empty($this->columns)) return '';
        $output = html_writer::start_tag('tr');
        foreach ($this->columns as $col) {
            $output .= $col->html_out();
        }
        $output .= html_writer::end_tag('tr');
        
        return $output;
    }
    
}

class reminder_content_column {
    
    /**
     * Text content of the column
     * @var string 
     */
    private $content;
    
        /**
     * Column parameters/attributes. Key as the attribute name and value as 
     * attribute value
     * @var array of string
     */
    private $styleparams = array();
    
    /**
     * Creates a column according to given parameters.
     * 
     * @param string $textcontent text content of the column
     * @param array $styles array of string indicates styles for the column
     */
    public function __construct($textcontent, $styles=null) {
        $this->content = $textcontent;
        $this->styleparams = $styles;
    }
    
    /**
     * Returns the content of this column
     * @return string text content of this column 
     */
    public function get_content() {
        return $this->content;
    }
    
    /**
     * Returns style corresponding to this column.
     * @return array string array indicating styles for this column
     */
    public function get_columnstyles() {
        return $this->styleparams;
    }
    
    /**
     * Returns the whole content of the column as a HTML. 
     * @return string html string content of the column
     */
    public function html_out() {
        return html_writer::tag('td', $this->content, $this->styleparams);
    }
}