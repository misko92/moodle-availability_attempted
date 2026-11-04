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

namespace availability_attempted;

use core_availability\info_module;
use core_availability\info_section;
use core_availability\tree;

/**
 * Tests for the quiz attempted condition.
 *
 * @package    availability_attempted
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \availability_attempted\condition
 * @covers     \availability_attempted\frontend
 */
final class condition_test extends \advanced_testcase {
    /** @var \stdClass */
    private \stdClass $course;
    /** @var \stdClass quiz before the page */
    private \stdClass $quiz1;
    /** @var \stdClass quiz before the page, after quiz1 */
    private \stdClass $quiz2;
    /** @var \stdClass page restricted in the tests */
    private \stdClass $page;
    /** @var \stdClass */
    private \stdClass $student;

    #[\Override]
    protected function setUp(): void {
        global $CFG;
        parent::setUp();
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $this->resetAfterTest();
        set_config('enableavailability', 1);
        $gen = $this->getDataGenerator();
        $this->course = $gen->create_course(['numsections' => 2]);
        $this->quiz1 = $this->make_quiz('Quiz 1');
        $this->quiz2 = $this->make_quiz('Quiz 2');
        $this->page = $gen->create_module('page', ['course' => $this->course->id, 'name' => 'Notes', 'section' => 1]);
        $this->student = $gen->create_and_enrol($this->course, 'student');
    }

    /**
     * A quiz in section 1 with one true/false question.
     *
     * @param string $name
     * @return \stdClass
     */
    private function make_quiz(string $name): \stdClass {
        $quiz = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_instance([
            'course' => $this->course->id, 'name' => $name, 'section' => 1, 'grade' => 10,
        ]);
        $questiongen = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongen->create_question_category(['contextid' => \context_module::instance($quiz->cmid)->id]);
        $question = $questiongen->create_question('truefalse', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz);
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return $quiz;
    }

    /**
     * Start an attempt as the student, and optionally submit it.
     *
     * @param \stdClass $quiz
     * @param bool $submit
     * @return int attempt id
     */
    private function attempt(\stdClass $quiz, bool $submit): int {
        // The quiz generator starts the attempt as the logged-in user.
        $this->setUser($this->student);
        $attempt = $this->getDataGenerator()->get_plugin_generator('mod_quiz')->create_attempt($quiz->id, $this->student->id);
        if ($submit) {
            $this->submit($attempt->id);
        }
        return $attempt->id;
    }

    /**
     * Submit an attempt with no answers (so a failing grade).
     *
     * @param int $attemptid
     */
    private function submit(int $attemptid): void {
        $this->getDataGenerator()->get_plugin_generator('mod_quiz')->submit_responses($attemptid, [], false, true);
    }

    /**
     * Whether the page is available to the student under a condition.
     *
     * @param int $cmid quiz course module id, or condition::OPTION_PREVIOUS
     * @param bool $not
     * @return bool
     */
    private function available(int $cmid, bool $not = false, ?int $userid = null): bool {
        global $DB;
        // With one condition, "must not match any" is plain "must not".
        $json = tree::get_root_json([condition::get_json($cmid)], $not ? tree::OP_NOT_OR : tree::OP_AND);
        $DB->set_field('course_modules', 'availability', json_encode($json), ['id' => $this->page->cmid]);
        rebuild_course_cache($this->course->id, true);
        $info = new info_module(get_fast_modinfo($this->course->id)->get_cm($this->page->cmid));
        $information = '';
        return $info->is_available($information, false, $userid ?? $this->student->id);
    }

    /**
     * What a teacher sees about the page's restrictions.
     *
     * @return string
     */
    private function description(): string {
        $info = new info_module(get_fast_modinfo($this->course->id)->get_cm($this->page->cmid));
        return \core_availability\info::format_info($info->get_full_information(), $this->course);
    }

    public function test_submitted_attempt_counts_whatever_the_grade(): void {
        $this->assertFalse($this->available($this->quiz1->cmid));
        $this->assertTrue($this->available($this->quiz1->cmid, true));

        // An attempt still in progress doesn't count.
        $attemptid = $this->attempt($this->quiz1, false);
        $this->assertFalse($this->available($this->quiz1->cmid));

        // Once submitted it does, even with no marks.
        $this->submit($attemptid);
        $this->assertTrue($this->available($this->quiz1->cmid));
        $this->assertFalse($this->available($this->quiz1->cmid, true));
        // Only for that quiz.
        $this->assertFalse($this->available($this->quiz2->cmid));
    }

    public function test_previews_dont_count(): void {
        $this->setUser($this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher'));
        $quizobj = \mod_quiz\quiz_settings::create($this->quiz1->id);
        $attempt = quiz_prepare_and_start_new_attempt($quizobj, 1, null, true);
        \mod_quiz\quiz_attempt::create($attempt->id)->process_submit(time(), false);
        global $USER;
        $this->assertFalse($this->available($this->quiz1->cmid, false, $USER->id));
    }

    public function test_previous_quiz(): void {
        $modinfo = get_fast_modinfo($this->course->id);
        $info = new info_module($modinfo->get_cm($this->page->cmid));
        $this->assertSame((int) $this->quiz2->cmid, condition::previous_quiz($info, $modinfo));
        // The first quiz has no quiz before it.
        $info = new info_module($modinfo->get_cm($this->quiz1->cmid));
        $this->assertSame(0, condition::previous_quiz($info, $modinfo));
        // Section 2 comes after both quizzes.
        $info = new info_section($modinfo->get_section_info(2));
        $this->assertSame((int) $this->quiz2->cmid, condition::previous_quiz($info, $modinfo));

        $this->attempt($this->quiz1, true);
        $this->assertFalse($this->available(condition::OPTION_PREVIOUS));
        $this->attempt($this->quiz2, true);
        $this->assertTrue($this->available(condition::OPTION_PREVIOUS));
    }

    public function test_missing_quiz(): void {
        // A deleted quiz (or a non-quiz activity) never counts as attempted.
        $this->assertFalse($this->available((int) $this->page->cmid));
        $this->assertFalse($this->available(999999));
    }

    public function test_description_and_tree(): void {
        $this->setAdminUser();
        $this->available($this->quiz1->cmid);
        $text = $this->description();
        $this->assertStringContainsString('An attempt at', $text);
        $this->assertStringContainsString('Quiz 1', $text);

        $this->available($this->quiz1->cmid, true);
        $this->assertStringContainsString('No attempt at', $this->description());

        $this->available(condition::OPTION_PREVIOUS);
        $this->assertStringContainsString('Quiz 2', $this->description());
    }

    public function test_save_and_dependency_update(): void {
        $condition = new condition(condition::get_json($this->quiz1->cmid));
        $this->assertEquals(condition::get_json($this->quiz1->cmid), $condition->save());
        $this->assertTrue($condition->update_dependency_id('course_modules', $this->quiz1->cmid, 1234));
        $this->assertEquals(condition::get_json(1234), $condition->save());
        $this->assertFalse($condition->update_dependency_id('course_modules', 1, 2));

        $this->expectException(\coding_exception::class);
        new condition((object) ['type' => 'attempted']);
    }

    public function test_editor_lists_quizzes(): void {
        $frontend = new frontend();
        $method = new \ReflectionMethod($frontend, 'get_javascript_init_params');
        $modinfo = get_fast_modinfo($this->course->id);

        // Editing the page: both quizzes, plus "Previous quiz".
        [$quizzes] = $method->invoke($frontend, $this->course, $modinfo->get_cm($this->page->cmid));
        $this->assertSame(
            [condition::OPTION_PREVIOUS, (int) $this->quiz1->cmid, (int) $this->quiz2->cmid],
            array_column($quizzes, 'id')
        );
        // Editing the first quiz: only the other one, and no "Previous quiz".
        [$quizzes] = $method->invoke($frontend, $this->course, $modinfo->get_cm($this->quiz1->cmid));
        $this->assertSame([(int) $this->quiz2->cmid], array_column($quizzes, 'id'));

        // A course without quizzes doesn't offer the condition.
        $empty = $this->getDataGenerator()->create_course();
        $allow = new \ReflectionMethod($frontend, 'allow_add');
        $this->assertFalse($allow->invoke(new frontend(), $empty));
        $this->assertTrue($allow->invoke(new frontend(), $this->course));
    }
}
