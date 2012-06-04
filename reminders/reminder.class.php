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
    
    protected $msgprovider;
    protected $notification;
    protected $event;
    
    protected $bodycssstyle = 'width:100%;font-family:Tahoma,Arial,Sans-serif;border-width:1px 2px 2px 1px;border:1px Solid #ccc';
    protected $titlestyle = 'padding:0 0 6px 0;margin:0;font-family:Arial,Sans-serif;font-size:16px;font-weight:bold;color:#222';
    protected $footerstyle = 'background-color:#f6f6f6;color:#888;border-top:1px Solid #ccc;font-family:Arial,Sans-serif;font-size:11px';
    
    public function __construct($event, $notificationstyle=1) {
        $this->event = $event;
        $this->notification = $notificationstyle;
        $this->msgprovider = get_message_provider();
    }
    
    protected function get_html_header() {
        return '<head></head>';
    }
    
    protected function get_html_footer() {
        $footer .= '<tr><td style="'.$footerstyle.'">';
        $footer .= 'Reminder from <a href="'.$CFG->wwwroot.'/calendar/index.php" target="_blank">Moodle Calendar</a></p>';
        $footer .= '</td></tr>';
        return $footer;
    }
    
    protected function format_event_time_duration() {
        $followedtimeformat = get_string('strftimetime', 'langconfig');
        
        $formattedtime = userdate($event->starttime);
        if ($event->timeduration > 0) {
            $formattedtime .= ' - '.userdate($event->starttime + $event->timeduration, $followedtimeformat);
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
        
        $eventdata = new stdClass();
        $eventdata->component           = 'local_reminders';   // plugin name
        $eventdata->name                = $this->get_message_provider();     // message interface name
        $eventdata->userfrom            = $admin->id;
        $eventdata->subject             = 'Reminder: '.$this->get_message_title();    // message title
        $eventdata->fullmessage         = $this->get_message_plaintext(); 
        $eventdata->fullmessageformat   = FORMAT_PLAIN;
        $eventdata->fullmessagehtml     = $this->get_message_html();
        $eventdata->notification        = $this->notification;
        
        return $eventdata;
    }
    
}