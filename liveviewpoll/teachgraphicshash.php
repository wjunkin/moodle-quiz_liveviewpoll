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
 * This script provides the hash telling the page to update or not.
 *
 * @package    quiz_liveviewpoll
 * @copyright  2016 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');

defined('MOODLE_INTERNAL') || die();

$id = optional_param('id', 0, PARAM_INT);
$cm = $DB->get_record('course_modules', array('id' => $id), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($id);
$quizcontextid = $contextinstance->id;
$quiztime = $DB->get_records_sql("
    SELECT max(qa.timemodified)
    FROM {question_attempts} qa
    JOIN {question_usages} qu ON qu.id = qa.questionusageid
    WHERE qu.contextid = ?", array($quizcontextid));
// Check if some student has joined the class since the last question was sent.
$currentquestion = $DB->get_record('quiz_current_questions', array('cmid' => $id));
// If the active question is -1, we don't care so take the current time.
echo "\n<br />quizid and currentquest are ".$currentquestion->quiz_id." and ".$currentquestion->question_id;
if ($currentquestion->question_id > 0) {
    $questionsenttime = $currentquestion->timemodified;
    $where = "SELECT * FROM {quiz_attempts} WHERE quiz = ? AND timestart > ?";
    $newstudents = $DB->get_records_sql($where, array($cm->instance, $questionsenttime));
    if (count($newstudents) > 0) {
        echo "\n<br />The count of new students is ".count($newstudents)."\n<br />";
        $slotid = pollingslot($cm->instance, $currentquestion->question_id);
        $mylayout = $slotid.',0';
        for ($i = 1; $i < 10; $i++) {
            $mylayout = $mylayout.",$slotid,0";
        }
        foreach ($newstudents as $newstudent) {
            echo "\n<br />id: ".$newstudent->id;
            $DB->set_field('quiz_attempts', 'layout', $mylayout, array('id' => $newstudent->id));
            // Start time is when questionsent in polling mode.
            $DB->set_field('quiz_attempts', 'timestart', $questionsenttime, array('id' => $newstudent->id));
        }
    }
}
/**
 * This function returns the correct quiz slot based on the quiz and the current question.
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $currentquestionid The id of the question that was sent.
 * @return int The quiz_slot for thh question that was sent.
 */
function pollingslot($quizid, $currentquestionid) {
    global $DB;
    if ($DB->record_exists('quiz_slots', array('quizid' => $quizid, 'questionid' => $currentquestionid))) {
        $slot = $DB->get_record('quiz_slots', array('quizid' => $quizid, 'questionid' => $currentquestionid));
        $slotid = $slot->slot;
    } else {
        $slotid = 0;
    }
    return $slotid;
}foreach ($quiztime as $qkey => $qtm) {
    $qmaxtime = intval($qkey) + 1;
}
echo $qmaxtime;