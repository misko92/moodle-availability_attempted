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

use cache;
use cache_store;
use core_availability\info;
use core_availability\info_module;
use core_availability\info_section;
use course_modinfo;
use stdClass;

/**
 * Condition: the student has submitted an attempt at a quiz, whatever the grade.
 *
 * Unlike activity completion, a failed attempt (or one waiting for grading)
 * counts. Preview attempts, abandoned ones and attempts still in progress don't.
 *
 * @package    availability_attempted
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class condition extends \core_availability\condition {
    /** @var int The quiz before this activity or section, in course order. */
    public const OPTION_PREVIOUS = -1;

    /** @var int course module id of the quiz, or OPTION_PREVIOUS */
    protected int $cmid;

    /**
     * Constructor.
     *
     * @param stdClass $structure data structure from JSON decode
     * @throws \coding_exception if invalid data structure
     */
    public function __construct($structure) {
        if (isset($structure->cm) && is_number($structure->cm)) {
            $this->cmid = (int) $structure->cm;
        } else {
            throw new \coding_exception('Missing or invalid ->cm for quiz attempted condition');
        }
    }

    #[\Override]
    public function save(): stdClass {
        return (object) ['type' => 'attempted', 'cm' => $this->cmid];
    }

    /**
     * A condition of this type, as JSON structure (for tests; normally built by the editor).
     *
     * @param int $cmid course module id of the quiz, or OPTION_PREVIOUS
     * @return stdClass
     */
    public static function get_json(int $cmid): stdClass {
        return (object) ['type' => 'attempted', 'cm' => $cmid];
    }

    #[\Override]
    public function is_available($not, info $info, $grabthelot, $userid): bool {
        $modinfo = $info->get_modinfo();
        $cm = $this->get_quiz($info, $modinfo);
        $allow = $cm !== null && in_array((int) $cm->instance, self::attempted_quizzes((int) $userid, $modinfo->get_course_id()));
        return $not ? !$allow : $allow;
    }

    #[\Override]
    public function get_description($full, $not, info $info): string {
        global $USER;
        $suffix = $not ? 'notattempted' : 'attempted';
        $courseid = $info->get_course()->id;
        $editing = !empty($USER->editing) &&
            has_capability('moodle/course:manageactivities', \context_course::instance($courseid));
        if ($this->cmid === self::OPTION_PREVIOUS && $editing) {
            // As in core: the previous quiz could be shown wrongly while the course page is being edited.
            return get_string('requires_previous_' . $suffix, 'availability_attempted');
        }
        $cm = $this->get_quiz($info, $info->get_modinfo());
        $name = $cm ? self::description_cm_name($cm->id) : get_string('missing', 'availability_attempted');
        return get_string('requires_' . $suffix, 'availability_attempted', $name);
    }

    #[\Override]
    protected function get_debug_string(): string {
        return 'cm' . ($this->cmid === self::OPTION_PREVIOUS ? 'previous' : $this->cmid);
    }

    /**
     * The quiz this condition is about, or null if it is missing or being deleted.
     *
     * @param info $info the item being checked
     * @param course_modinfo $modinfo
     * @return \cm_info|null
     */
    protected function get_quiz(info $info, course_modinfo $modinfo): ?\cm_info {
        $cmid = $this->cmid === self::OPTION_PREVIOUS ? self::previous_quiz($info, $modinfo) : $this->cmid;
        $cm = $modinfo->cms[$cmid] ?? null;
        if (!$cm || $cm->modname !== 'quiz' || $cm->deletioninprogress) {
            return null;
        }
        return $cm;
    }

    /**
     * The course module id of the last quiz before an activity or section, in course order.
     *
     * @param info $info the activity or section being checked
     * @param course_modinfo $modinfo
     * @return int course module id, or 0 if there is none
     */
    public static function previous_quiz(info $info, course_modinfo $modinfo): int {
        $selfcmid = $info instanceof info_module ? (int) $info->get_course_module()->id : 0;
        $sectionnum = $info instanceof info_section ? (int) $info->get_section()->section : null;
        $previous = 0;
        foreach ($modinfo->get_cms() as $cm) {
            if ($cm->id == $selfcmid || ($sectionnum !== null && $cm->sectionnum >= $sectionnum)) {
                break;
            }
            if ($cm->modname === 'quiz' && !$cm->deletioninprogress) {
                $previous = (int) $cm->id;
            }
        }
        return $previous;
    }

    /**
     * Ids of the quizzes in a course a user has submitted an attempt at.
     *
     * Cached for the request; reset_cache() clears it when attempts change.
     *
     * @param int $userid
     * @param int $courseid
     * @return int[] quiz ids
     */
    public static function attempted_quizzes(int $userid, int $courseid): array {
        global $DB;
        $cache = self::cache();
        $key = $userid . '_' . $courseid;
        $quizids = $cache->get($key);
        if ($quizids === false) {
            $quizids = array_map('intval', $DB->get_fieldset_sql(
                "SELECT DISTINCT qa.quiz
                   FROM {quiz_attempts} qa
                   JOIN {quiz} q ON q.id = qa.quiz
                  WHERE qa.userid = :userid AND q.course = :courseid AND qa.preview = 0
                        AND qa.state IN (:submitted, :finished)",
                // Moodle 5.3+ attempts are 'submitted' until graded, then 'finished'.
                ['userid' => $userid, 'courseid' => $courseid, 'submitted' => 'submitted', 'finished' => 'finished']
            ));
            $cache->set($key, $quizids);
        }
        return $quizids;
    }

    /**
     * Forget cached attempts, e.g. after one is submitted or deleted.
     */
    public static function reset_cache(): void {
        self::cache()->purge();
    }

    /**
     * The request cache of attempted quizzes.
     *
     * @return \cache_application|\cache_session|\cache
     */
    protected static function cache() {
        return cache::make_from_params(cache_store::MODE_REQUEST, 'availability_attempted', 'attempts');
    }

    #[\Override]
    public function update_after_restore($restoreid, $courseid, \base_logger $logger, $name): bool {
        global $DB;
        if ($this->cmid === self::OPTION_PREVIOUS) {
            return false;
        }
        $rec = \restore_dbops::get_backup_ids_record($restoreid, 'course_module', $this->cmid);
        if (!$rec || !$rec->newitemid) {
            // Same course (e.g. duplicating an activity): keep pointing at the same quiz.
            if ($DB->record_exists('course_modules', ['id' => $this->cmid, 'course' => $courseid])) {
                return false;
            }
            $this->cmid = 0;
            $logger->process(
                'Restored item (' . $name . ') has a quiz attempted condition on a quiz that was not restored',
                \backup::LOG_WARNING
            );
        } else {
            $this->cmid = (int) $rec->newitemid;
        }
        return true;
    }

    #[\Override]
    public function update_dependency_id($table, $oldid, $newid) {
        if ($table === 'course_modules' && $this->cmid === (int) $oldid) {
            $this->cmid = (int) $newid;
            return true;
        }
        return false;
    }
}
