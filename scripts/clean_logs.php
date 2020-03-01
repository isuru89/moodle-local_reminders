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
 * Library function for reminders cron function.
 *
 * @package    local
 * @subpackage reminders
 * @copyright  2012 Isuru Madushanka Weerarathna
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('REMINDERS_CLEAN_7DAYSBEFORE_INSECONDS', 7 * 24 * 3600);

define('REMINDERS_CLEAN_TABLE', 'local_reminders');

/**
 * Cleans the local_reminders table by deleting older unnecessary records.
 */
function clean_local_reminders_logs() {
    global $CFG, $DB, $PAGE;

    $cutofftime = time() - REMINDERS_CLEAN_7DAYSBEFORE_INSECONDS;
    mtrace("  [Local Reminders][CLEAN] clean cutoff time: $cutofftime");
    $recordcount = $DB->count_records_select(REMINDERS_CLEAN_TABLE, "time >= $cutofftime");
    if ($recordcount > 0) {
        mtrace('  [Local Reminders][CLEAN] Cleaning can be executed now as there are newer records.');
        $deletestatus = $DB->delete_records_select(REMINDERS_CLEAN_TABLE, "time < $cutofftime");
        mtrace('  [Local Reminders][CLEAN] Cleaning status: '.$deletestatus);
    } else {
        mtrace('  [Local Reminders][CLEAN] No records allow to clean since reminders cron has not bee executed for long time!');
    }
}
