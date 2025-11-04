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

use completion_info;
use advanced_testcase;
use local_reminders\local\completion_status;
use question_engine;

/**
 * Test status class for quizzes.
 *
 * @package     local_reminders
 * @author      Alexander Van der Bellen <alexandervanderbellen@catalyst-au.net>
 * @copyright   2025 Catalyst IT Australia Pty Ltd
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class mod_quiz_test extends advanced_testcase {
    /**
     * Helper function to set up a quiz with one numerical question.
     *
     * @param int $courseid The course id where the quiz will be created.
     * @return object The created quiz instance.
     */
    private function setup_quiz(int $courseid): object {
        // Create a quiz.
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $quiz = $quizgenerator->create_instance([
            'course' => $courseid,
            'grade' => 100.0,
            'sumgrades' => 1,
            'completion' => COMPLETION_TRACKING_AUTOMATIC,
        ]);

        // Add a question.
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $cat = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('numerical', null, ['category' => $cat->id]);
        quiz_add_quiz_question($question->id, $quiz);

        return $quiz;
    }

    /**
     * Helper function for tests.
     * Starts an attempt, processes responses and finishes the attempt.
     *
     * @param array $attemptoptions ['quiz'] => object, ['student'] => object, ['tosubmit'] => array, ['attemptnumber'] => int
     */
    private function do_attempt_quiz(array $attemptoptions): void {
        $quizobj = \mod_quiz\quiz_settings::create((int) $attemptoptions['quiz']->id);

        // Start the passing attempt.
        $quba = question_engine::make_questions_usage_by_activity('mod_quiz', $quizobj->get_context());
        $quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);

        $timenow = time();
        $attempt = quiz_create_attempt(
            $quizobj,
            $attemptoptions['attemptnumber'],
            false,
            $timenow,
            false,
            $attemptoptions['student']->id
        );
        quiz_start_new_attempt($quizobj, $quba, $attempt, $attemptoptions['attemptnumber'], $timenow);
        quiz_attempt_save_started($quizobj, $quba, $attempt);

        // Process responses from the student.
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $attemptobj->process_submitted_actions($timenow, false, $attemptoptions['tosubmit']);

        // Finish the attempt.
        $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
        $this->assertTrue($attemptobj->has_response_to_at_least_one_graded_question());
        $attemptobj->process_finish($timenow, false);
    }

    /**
     * Test return values of get_status.
     *
     * @covers ::is_completed
     */
    public function test_get_status(): void {
        global $CFG, $DB;

        if (!class_exists('\mod_quiz\quiz_settings') || !class_exists('\mod_quiz\quiz_attempt')) {
            $this->markTestSkipped('mod_quiz API is not available.');
        }

        $this->resetAfterTest(true);
        $CFG->enablecompletion = true;

        $course = $this->getDataGenerator()->create_course(['enablecompletion' => true]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quiz = $this->setup_quiz($course->id);
        $modinfo = get_fast_modinfo($course);
        $cm = $modinfo->get_cm($quiz->cmid);

        // Check intial status.
        $this->assertFalse(completion_status::is_completed($student->id, $cm));

        // Complete an attempt and check status is submitted.
        $this->do_attempt_quiz([
            'quiz' => $quiz,
            'student' => $student,
            'attemptnumber' => 1,
            'tosubmit' => [1 => ['answer' => '3.14']],
        ]);
        $this->assertTrue(completion_status::is_completed($student->id, $cm));

        // Update completion criteria and check status is completed.
        $DB->update_record('course_modules', [
            'id' => $cm->id,
            'completionpassgrade' => 1,
        ]);
        $completion = new completion_info($course);
        $completion->update_state($cm, COMPLETION_UNKNOWN, $student->id);
        $this->assertTrue(completion_status::is_completed($student->id, $cm));
    }
}
