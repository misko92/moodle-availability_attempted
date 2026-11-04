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
 * Language strings.
 *
 * @package    availability_attempted
 * @copyright  2026 misko92
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['description'] = 'Require students to have submitted an attempt at a quiz, whatever their grade.';
$string['error_selectcmid'] = 'You must select a quiz for the quiz attempted condition.';
$string['label_cm'] = 'Quiz';
$string['missing'] = '(Missing quiz)';
$string['option_previous'] = 'Previous quiz';
$string['pluginname'] = 'Restriction by quiz attempted';
$string['privacy:metadata'] = 'The Restriction by quiz attempted plugin does not store any personal data.';
$string['requires_attempted'] = 'An attempt at <strong>{$a}</strong> has been submitted';
$string['requires_notattempted'] = 'No attempt at <strong>{$a}</strong> has been submitted';
$string['requires_previous_attempted'] = 'An attempt at <strong>the previous quiz</strong> has been submitted';
$string['requires_previous_notattempted'] = 'No attempt at <strong>the previous quiz</strong> has been submitted';
$string['title'] = 'Quiz attempted';
