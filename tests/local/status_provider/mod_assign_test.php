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


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/assign/tests/generator.php');

use advanced_testcase;
use local_reminders\local\completion_status;
use mod_assign_test_generator;

/**
 * Test status class.
 *
 * @package     local_reminders
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_assign_test extends advanced_testcase {
    // Include the assign test generator helper trait.
    use mod_assign_test_generator;

    /**
     * Test return values of get_status.
     *
     * @covers ::is_completed
     */
    public function test_get_status(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);

        // Assignment that requires submission and grading to complete.
        $assign1 = $this->create_instance($course, [
            'assignsubmission_onlinetext_enabled' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,
            'completionusegrade' => 1,
        ]);

        // Assignment that requires submission, grading and a passing grade to complete.
        $assign2 = $this->create_instance($course, [
            'assignsubmission_onlinetext_enabled' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
            'completionsubmit' => 1,
            'completionusegrade' => 1,
            'completionpassgrade' => 1,
            'gradepass' => 50,
        ]);

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course);
        $student2 = $this->getDataGenerator()->create_and_enrol($course);

        // Check the initial status of assignments.
        $this->assertFalse(completion_status::is_completed($student1->id, $assign1->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student1->id, $assign2->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign1->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign2->get_course_module()));

        // Add a draft submission for student 1.
        $this->add_submission($student1, $assign1, 'Assignment submission text alpha.');
        $this->add_submission($student1, $assign2, 'Graded assignment submission text alpha.');

        $this->assertFalse(completion_status::is_completed($student1->id, $assign1->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student1->id, $assign2->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign1->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign2->get_course_module()));

        // Submit assignment for student 1.
        $this->submit_for_grading($student1, $assign1);
        $this->submit_for_grading($student1, $assign2);

        $this->assertTrue(completion_status::is_completed($student1->id, $assign1->get_course_module()));
        $this->assertTrue(completion_status::is_completed($student1->id, $assign2->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign1->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign2->get_course_module()));

        // Resubmit assignments for student 1 before grading.
        $this->add_submission($student1, $assign1, 'Assignment submission text beta.');
        $this->add_submission($student1, $assign2, 'Graded assignment submission text beta.');
        $this->submit_for_grading($student1, $assign1);
        $this->submit_for_grading($student1, $assign2);

        // Since the assignment has not been graded yet, the status should still be submitted.
        $this->assertTrue(completion_status::is_completed($student1->id, $assign1->get_course_module()));
        $this->assertTrue(completion_status::is_completed($student1->id, $assign2->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign1->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign2->get_course_module()));

        // Grade the assignments for student 1.
        $this->mark_submission($teacher, $assign1, $student1, 25.0);
        $this->mark_submission($teacher, $assign2, $student1, 25.0);

        $this->assertTrue(completion_status::is_completed($student1->id, $assign1->get_course_module()));
        $this->assertTrue(completion_status::is_completed($student1->id, $assign2->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign1->get_course_module()));
        $this->assertFalse(completion_status::is_completed($student2->id, $assign2->get_course_module()));
    }
}
