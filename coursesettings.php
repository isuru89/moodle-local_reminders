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

require('../../config.php');
require_once($CFG->dirroot.'/local/reminders/coursesettings_form.php');

$activityprefix = 'activity_';

$courseid = required_param('courseid', PARAM_INT);

$return = new moodle_url('/course/view.php', array('id' => $courseid));

$course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
$coursesettings = $DB->get_record('local_reminders_course', array('courseid' => $courseid));
if (!$coursesettings) {
    $coursesettings = new stdClass();
}
$coursesettings->courseid = $courseid;
$coursecontext = context_course::instance($course->id);

$activitysettings = $DB->get_records('local_reminders_activityconf', array('courseid' => $courseid));
if (!$activitysettings) {
    $activitysettings = array();
} else {
    foreach ($activitysettings as $asetting) {
        $refkey = 'refactivity_'.$asetting->eventid;
        $actkey = 'activity_'.$asetting->eventid.'_'.$asetting->settingkey;
        $coursesettings->$refkey = $asetting->id;
        $coursesettings->$actkey = $asetting->settingvalue;
    }
}

require_login($course);
require_capability('moodle/course:update', $coursecontext);

$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/reminders/coursesettings.php', array('courseid' => $courseid));
$PAGE->set_title(get_string('admintreelabel', 'local_reminders'));
$PAGE->set_heading($course->fullname);

$mform = new local_reminders_coursesettings_edit_form(null, array($coursesettings, $activitysettings));

if ($mform->is_cancelled()) {
    redirect($return);
} else if ($data = $mform->get_data()) {
    $dataarray = get_object_vars($data);
    if (isset($coursesettings->id)) {
        $data->id = $coursesettings->id;
        $DB->update_record('local_reminders_course', $data);
    } else {
        $DB->insert_record('local_reminders_course', $data);
    }

    foreach ($dataarray as $key => $value) {
        if (substr($key, 0, strlen($activityprefix)) == $activityprefix) {
            $keyparts = explode('_', $key);
            $status = array_key_exists('refactivity_'.$keyparts[1], $dataarray);

            $actdata = new stdClass();
            $actdata->courseid = $data->courseid;
            $actdata->eventid = (int)$keyparts[1];
            $actdata->settingkey = $keyparts[2];
            $actdata->settingvalue = $value;
            if ($status) {
                $actdata->id = $dataarray['refactivity_'.$keyparts[1]];
                $DB->update_record('local_reminders_activityconf', $actdata);
            } else {
                $DB->insert_record('local_reminders_activityconf', $actdata);
            }
        }
    }
}


echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admintreelabel', 'local_reminders'));

$mform->display();

echo $OUTPUT->footer();
