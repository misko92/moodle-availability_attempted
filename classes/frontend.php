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

/**
 * Restrict access editor support: lists the course's quizzes.
 *
 * @package    availability_attempted
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class frontend extends \core_availability\frontend {
    #[\Override]
    protected function get_javascript_strings() {
        return ['label_cm'];
    }

    #[\Override]
    protected function get_javascript_init_params($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        $context = \context_course::instance($course->id);
        $quizzes = [];
        $hasprevious = false;
        foreach (get_fast_modinfo($course)->get_cms() as $othercm) {
            if ($cm && $othercm->id == $cm->id) {
                // A quiz before the one being edited exists, so "Previous quiz" makes sense.
                $hasprevious = (bool) $quizzes;
                continue;
            }
            if ($othercm->modname === 'quiz' && !$othercm->deletioninprogress) {
                $quizzes[] = (object) [
                    'id' => (int) $othercm->id,
                    'name' => format_string($othercm->name, true, ['context' => $context]),
                ];
            }
        }
        if (!$cm) {
            // A new activity, a section or several activities at once: offer it if there is any quiz.
            $hasprevious = (bool) $quizzes;
        }
        if ($hasprevious) {
            array_unshift($quizzes, (object) [
                'id' => condition::OPTION_PREVIOUS,
                'name' => get_string('option_previous', 'availability_attempted'),
            ]);
        }
        return [$quizzes];
    }

    #[\Override]
    protected function allow_add($course, ?\cm_info $cm = null, ?\section_info $section = null) {
        return (bool) $this->get_javascript_init_params($course, $cm, $section)[0];
    }
}
