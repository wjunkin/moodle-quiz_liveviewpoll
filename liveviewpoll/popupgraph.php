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
 * Displays the quiz HIstogram or text resposes.
 *
 * An indicaton of # of responses to this question/# of student responding to this quiz instance is printed.
 * After that the histogram or the text responses are printed, depending on the question type.
 * @package   quiz_liveviewpoll
 * @copyright  2018 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
$quizid = optional_param('quizid', 0, PARAM_INT);
$questionqid = optional_param('question_id', 0, PARAM_INT);
$showstudents = optional_param('showstudents', 0, PARAM_INT);
$evaluate = optional_param('evaluate', 0, PARAM_INT);
$rag = optional_param('rag', 0, PARAM_INT);
$quiz = $DB->get_record('quiz', array('id' => $quizid));
$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
if (!(has_capability('mod/quiz:manage', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}
require_once('poll_tooltip_graph.php');
