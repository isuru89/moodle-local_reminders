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
 * This file contains course specific settings instance manipulation.
 *
 * @package    local_reminders
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
        $actkey = 'activity_'.$asetting->eventid.'_'.$asetting->settingkey;
        $coursesettings->$actkey = $asetting->settingvalue;
    }
}

$globalactivityaheaddays = $CFG->local_reminders_duerdays;
if (!isset($globalactivityaheaddays)) {
    $globalactivityaheaddays = array(0, 0, 0);
}
$aheaddaysindex = array(7 => 0, 3 => 1, 1 => 2);
foreach ($aheaddaysindex as $dkey => $dvalue) {
    $daykey = 'activityglobal_days'.$dkey;
    $coursesettings->$daykey = $globalactivityaheaddays[$dvalue];
}

require_login($course);
require_capability('moodle/course:update', $coursecontext);

$PAGE->set_pagelayout('admin');
$PAGE->set_url('/local/reminders/coursesettings.php', array('courseid' => $courseid));
$PAGE->set_title(get_string('admintreelabel', 'local_reminders'));
$PAGE->set_heading($course->fullname);

$mform = new local_reminders_coursesettings_edit_form(null, array($coursesettings));

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
            if (count($keyparts) < 3) {
                continue;
            }
            $eventid = (int)$keyparts[1];
            $status = $DB->get_record_sql("SELECT id
                FROM {local_reminders_activityconf}
                WHERE courseid = :courseid AND eventid = :eventid AND settingkey = :settingkey",
                array('courseid' => $data->courseid, 'eventid' => $eventid, 'settingkey' => $keyparts[2]));

            $actdata = new stdClass();
            $actdata->courseid = $data->courseid;
            $actdata->eventid = $eventid;
            $actdata->settingkey = $keyparts[2];
            $actdata->settingvalue = $value;
            if (!$status) {
                $DB->insert_record('local_reminders_activityconf', $actdata);
            } else {
                $actdata->id = $status->id;
                $DB->update_record('local_reminders_activityconf', $actdata);
            }
        }
    }
}


echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('admintreelabel', 'local_reminders'));

$mform->display();

echo $OUTPUT->footer();
