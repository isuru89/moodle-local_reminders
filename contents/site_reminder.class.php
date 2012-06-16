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
 * Class to specify the reminder message object for site (global) events.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class site_reminder extends reminder {
    
    public function __construct($event, $aheaddays = 1) {
        parent::__construct($event, $aheaddays);
    }
    
    public function get_message_html() {
        $htmlmail = $this->get_html_header().'';
        $htmlmail .= '<body id=\"email\"><div>';
        $htmlmail .= '<table cellspacing="0" cellpadding="8" border="0" summary="" style="'.$this->tbodycssstyle.'">';
        $htmlmail .= '<tr><td colspan="2"><a href="'.$this->generate_event_link().'" style="text-decoration: none">'.
            '<h3 style="'.$this->titlestyle.'">'.$this->get_message_title().'</h3></a></td></tr>';
        $htmlmail .= '<tr><td width="25%">When</td><td>'.$this->format_event_time_duration().'</td></tr>';
        $htmlmail .= '<tr><td>Description</td><td>'.$this->event->description.'</td></tr>';
        $htmlmail .= $this->get_html_footer();
        $htmlmail .= '</table></body></html>';
        
        return $htmlmail;
    }
    
    public function get_message_plaintext() {
        $text  = $this->get_message_title().' ['.$this->aheaddays.' day(s) to go]\n';
        $text .= 'When: '.$this->format_event_time_duration().'\n';
        $text .= 'Description: '.$this->event->description.'\n';
        
        return $text;
    }

    protected function get_message_provider() {
        return 'reminders_site';
    }

    public function get_message_title() {
        return $this->event->name;
    }
}