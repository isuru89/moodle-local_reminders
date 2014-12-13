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
 * @package local_reminders
 * @copyright  2014 Joannes Burk
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir.'/formslib.php');

class local_reminders_coursesettings_edit_form extends moodleform {

	function definition() {
        $mform = $this->_form;
        list($coursesettings) = $this->_customdata;

        // $mform->addElement('header', 'header', 'ueberschrift');

        $mform->addElement('advcheckbox', 'status_course', get_string('enabled', 'local_reminders'), get_string('courseheading', 'local_reminders'));
        $mform->setDefault('status_course', 1);
        // $mform->addHelpButton('status_course', 'status_course', 'status_course');
        
        $mform->addElement('advcheckbox', 'status_activities', get_string('enabled', 'local_reminders'), get_string('dueheading', 'local_reminders'));
        $mform->setDefault('status_activities', 1);

        $mform->addElement('advcheckbox', 'status_group', get_string('enabled', 'local_reminders'), get_string('groupheading', 'local_reminders'));
        $mform->setDefault('status_group', 1);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_action_buttons(true);

        $this->set_data($coursesettings);

    }
}