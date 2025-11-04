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
use core_component;

/**
 * Helper class to determine the status of activities for users in a course.
 *
 * @package     local_reminders
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class completion_status {
    /** @var array A cache of instantiated status providers. */
    private static $statusproviders = [];

    /**
     * Check if the activity is considered complete for the given user.
     *
     * @param int $userid The user id.
     * @param cm_info $cm The course module object.
     * @return bool True if the activity is considered complete, false otherwise.
     */
    public static function is_completed(int $userid, cm_info $cm): bool {
        // Let supported activity types determine completion status.
        $statusprovider = self::get_completion_status_provider($cm->modname);
        if ($statusprovider) {
            return $statusprovider->is_completed($userid, $cm);
        }

        $statusprovider = new completion_status_provider();
        return $statusprovider->is_completed($userid, $cm);
    }

    /**
     * Get a submission status completion_status_provider for a given module name.
     *
     * @param string $modname The name of the module (e.g. 'assign', 'quiz').
     * @return completion_status_provider|null A completion_status_provider instance, or null if not supported.
     */
    private static function get_completion_status_provider(string $modname): ?completion_status_provider {

        if (isset(self::$statusproviders[$modname])) {
            return self::$statusproviders[$modname];
        }

        // Check if this is a valid, installed Moodle plugin component.
        if (empty(core_component::get_component_directory("mod_{$modname}"))) {
            self::$statusproviders[$modname] = null;
            return null;
        }

        $classname = __NAMESPACE__ . "\\status_provider\\mod_{$modname}";
        if (!class_exists($classname)) {
            self::$statusproviders[$modname] = null;
            return null;
        }

        $statusprovider = new $classname();
        if (!$statusprovider instanceof completion_status_provider) {
            self::$statusproviders[$modname] = null;
            return null;
        }

        self::$statusproviders[$modname] = $statusprovider;
        return $statusprovider;
    }
}
