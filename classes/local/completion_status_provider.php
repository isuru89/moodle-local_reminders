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

namespace local_reminders\local;

use cm_info;
use completion_info;

/**
 * Default class for activity completion status providers.
 *
 * @package     local_reminders
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_status_provider {
    /**
     * Check if the activity is considered complete.
     *
     * @param int $userid The user id.
     * @param cm_info $cm The course module object.
     * @return bool True if the user has made a submission, false otherwise.
     */
    public function is_completed(int $userid, cm_info $cm): bool {
        $completion = new completion_info($cm->get_course());

        if ($completion->is_enabled($cm)) {
            switch ($completion->get_data($cm, false, $userid)->completionstate) {
                case COMPLETION_COMPLETE:
                case COMPLETION_COMPLETE_PASS:
                case COMPLETION_COMPLETE_FAIL:
                    return true;
            }
        }

        return false;
    }
}
