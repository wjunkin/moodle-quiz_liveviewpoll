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
 * This page prints the page to let students know there is no active question at this time.
 *
 * @package   quiz_liveviewpoll
 * @copyright 2020 William Junkin <junkinwf@eckerd.edu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
defined('MOODLE_INTERNAL') || die();
$cmid = optional_param('cmid', 0, PARAM_INT);
$attemptid = optional_param('attempt', 0, PARAM_INT);
if ($cmid > 0) {
    $cm = $DB->get_record('course_modules', array('id' => $cmid));
    $course = $DB->get_record('course', array('id' => $cm->course));
} else {
    // T take care of older quiz versions where only the attempt is sent in the URL.
    if ($attemptid > 0) {
        $attempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
        $quiz = $DB->get_record('quiz', array('id' => $attempt->quiz));
        $course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
    } else {
        echo "\n<br />Something is wrong with the attempt or course module associated with this site";
        exit;
    }
}
if (isset($_SERVER['HTTP_REFERER'])) {
    $pollingreturnurl = $_SERVER['HTTP_REFERER'];
} else if (($cmid) && ($attemptid)) {
    $pollingreturnurl = $CFG->wwwroot."/mod/quiz/attempt.php?attempt=$attemptid&cmid=$cmid";
} else {
    $pollingreturnurl = $CFG->wwwroot."/mod/quiz/attempt.php?attempt=$attemptid&cmid=$cmid";
}
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:view', $context);
echo "<html><head><title>".get_string('nocurrentqpage', 'quiz_liveviewpoll')."</title></head>";
echo "\n<body>";
// Is this a question used in a polling session?
// If yes and the teacher sent a question less than an hour ago, go to the quiz.
// If yes and the teacher paused polling less than and hour ago, wait.
// If yes and the teacher paused polling session more than an hour ago, echo too long between sending questions.
// If no, echo spent too long on question page.
$pollingsession = 0;
$questionid = 0;
if ($DB->record_exists('quiz_current_questions', array('quiz_id' => $cm->instance))) {
    $currentquestions = $DB->get_records('quiz_current_questions', array('quiz_id' => $cm->instance));
    foreach ($currentquestions as $currentquestion) {
        if ($currentquestion->groupid) {
            if (in_array($USER->id, explode(',', $currentquestion->groupmembers))) {
                $pasttime = time() - $currentquestion->timemodified;
                $questionid = $currentquestion->question_id;
            }
        } else {
            $pasttime = time() - $currentquestion->timemodified;
            $questionid = $currentquestion->question_id;
        }
    }
    // There should be only one or no currentquestion per user.
    // The variable pasttime is in seconds.
    if (($pasttime < 3600) and ($questionid > 0)) {
        // Go back to quiz. A question has been sent recently.
        echo "\n<script>";
        echo "\n     window.location.replace(\"$pollingreturnurl\");";
        echo "\n</script>";
    } else if (($pasttime < 3600) and ($questionid == -1)) {
        // Wait window.
        echo "<script src=\"javascript_noq_refresh.js\">
        </script>";
        echo get_string('noqsent', 'quiz_liveviewpoll');
        echo "<a href=\"$pollingreturnurl\">".get_string('here', 'quiz_liveviewpoll')."</a>.";
        $courseurl = $CFG->wwwroot."/course/view.php?id=".$course->id;
        echo "<br />".get_string('pleaseclick', 'quiz_liveviewpoll');
        echo "<a href=\"$courseurl\">".get_string('here', 'quiz_liveviewpoll')."</a>";
        echo get_string('toreturn', 'quiz_liveviewpoll');
    } else if ($questionid == -1) {
        // Teacher hasn't sent a question for more than an hour.
        echo get_string('noqforhour', 'quiz_liveviewpoll');
        echo "<a href=\"$pollingreturnurl\">".get_string('here', 'quiz_liveviewpoll')."</a>.";
        $courseurl = $CFG->wwwroot."/course/view.php?id=".$course->id;
        echo "<br />".get_string('pleaseclick', 'quiz_liveviewpoll');
        echo "<a href=\"$courseurl\">".get_string('here', 'quiz_liveviewpoll')."</a>";
        echo get_string('toreturn', 'quiz_liveviewpoll');
    } else {
        echo get_string('sessiontimedout', 'quiz_liveviewpoll');
        echo "<br />".get_string('pleaseclick', 'quiz_liveviewpoll');
        $courseurl = $CFG->wwwroot."/course/view.php?id=".$course->id;
        echo "<a href=\"$courseurl\">".get_string('here', 'quiz_liveviewpoll')."</a>";
        echo get_string('toreturn', 'quiz_liveviewpoll');
    }
}

echo "\n</body></html>";