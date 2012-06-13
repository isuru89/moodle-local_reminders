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
    
    protected $notification;
    protected $event;
    
    protected $tbodycssstyle = 'width:100%;font-family:Tahoma,Arial,Sans-serif;border-width:1px 2px 2px 1px;border:1px Solid #ccc';
    protected $titlestyle = 'padding:0 0 6px 0;margin:0;font-family:Arial,Sans-serif;font-size:16px;font-weight:bold;color:#222';
    protected $footerstyle = 'background-color:#f6f6f6;color:#888;border-top:1px Solid #ccc;font-family:Arial,Sans-serif;font-size:11px';
    
    public function __construct($event, $notificationstyle=1) {
        $this->event = $event;
        $this->notification = $notificationstyle;
    }
    
    protected function get_html_header() {
        return '<head></head>';
    }
    
    protected function get_html_footer() {
        global $CFG;
        
        $footer = '<tr><td style="'.$this->footerstyle.'" colspan="2">';
        $footer .= 'Reminder from <a href="'.$CFG->wwwroot.'/calendar/index.php" target="_blank">Moodle Calendar</a></p>';
        $footer .= '</td></tr>';
        return $footer;
    }
    
    protected function generate_event_link() {
        global $CFG;
        
        return $CFG->wwwroot.'/calendar/view.php?view=day&cal_d='.date('j', $this->event->timestart).
                '&cal_m='.date('n', $this->event->timestart).
                '&cal_y='.date('Y', $this->event->timestart).'#event_'.$this->event->id;
    }
    
    protected function format_event_time_duration() {
        $followedtimeformat = get_string('strftimetime', 'langconfig');

        $formattedtime = userdate($this->event->timestart);
        if ($this->event->timeduration > 0) {
            $formattedtime .= ' - '.userdate($this->event->timestart + $this->event->timeduration, $followedtimeformat);
        }
        return $formattedtime;
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
    public abstract function get_message_html();
    
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
     * @return object a message object which will be sent to the messaging API
     */
    public function create_reminder_message_object($admin=null) {  
        if ($admin == null) {
            $admin = get_admin();
        }
        //mtrace("Creating event object for "." {$USER->id} ".$this->get_message_provider());
        
        $contenthtml = $this->get_message_html();
        $titlehtml = $this->get_message_title();
        
        $eventdata = new stdClass();
        $eventdata->component           = 'local_reminders';   // plugin name
        $eventdata->name                = $this->get_message_provider();     // message interface name
        $eventdata->userfrom            = $admin;
        $eventdata->userto              = 3;
        $eventdata->subject             = 'Reminder: '.$titlehtml;    // message title
        $eventdata->fullmessage         = $this->get_message_plaintext(); 
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = $contenthtml;
        $eventdata->smallmessage        = $titlehtml . ' - ' . $contenthtml;
        $eventdata->notification        = 1;
        
        return $eventdata;
    }
    
}