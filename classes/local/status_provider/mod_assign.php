<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_reminders\local\status_provider;

use assign;
use cm_info;
use local_reminders\local\completion_status_provider;

/**
 * Submission status provider for assignments.
 *
 * @package     local_reminders
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_assign extends completion_status_provider {
    /**
     * {@inheritDoc}
     *
     * @param int $userid The user id.
     * @param cm_info $cm The course module object.
     * @return bool True if the user has made a submission, false otherwise.
     */
    public function is_completed(int $userid, cm_info $cm): bool {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $assignment = new assign($cm->context, $cm, $cm->get_course());
        $instance = $assignment->get_instance($userid);

        if ($instance->teamsubmission) {
            $submission = $assignment->get_group_submission($userid, 0, false);
        } else {
            $submission = $assignment->get_user_submission($userid, false);
        }

        if (is_object($submission) && isset($submission->status)) {
            return $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED;
        }

        return parent::is_completed($userid, $cm);
    }
}
