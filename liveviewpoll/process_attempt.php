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
 * This page allows teachers to force the submission of student answers in polling quizzes.
 *
 * This code is a modification of quiz/processattempt.php. If that code is changed, this must be changed also.
 *
 * @package   quiz_liveviewpoll
 * @copyright 2020 William F Junkin (junkinwf@eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

// Remember the current time as the time any responses were submitted.
$timenow = time();

// Get submitted parameters.
$cmid          = optional_param('cmid', null, PARAM_INT);
$groupid  = optional_param('groupid', 0, PARAM_INT);
$confirmation = optional_param('confirmation', 0, PARAM_INT);
$cm = $DB->get_record('course_modules', array('id' => $cmid));
$courseurl = $CFG->wwwroot.'/course/view.php?id='.$cm->course;
$course = $DB->get_record('course', array('id' =>$cm->course));
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
if (!(has_capability('mod/quiz:manage', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}
echo "<html><head><title>Page to force submission of answers to polling quiz</title></head>";
echo "\n<body>";
if ($confirmation == 1) {
    // Get student ids for attempts that need to be closed.
    if ($DB->record_exists('quiz_current_questions', array('cmid' => $cmid, 'groupid' => $groupid))) {
        $currentquestion = $DB->get_record('quiz_current_questions', array('cmid' => $cmid, 'groupid' => $groupid));
        $quiz = $DB->get_record('quiz', array('id' => $currentquestion->quiz_id));
    } else {
        echo "\n<br />This doesn't seem to be a polling quiz.";
        echo "\n<br />Click <a href='$courseurl'>here</a> to return to the course.";
        exit;
    }
    // Get all attempts that are inprogress.
    if ($DB->record_exists('quiz_attempts', array('quiz' => $currentquestion->quiz_id, 'state' => 'inprogress'))) {
        $attempts = $DB->get_records('quiz_attempts', array('quiz' => $currentquestion->quiz_id, 'state' => 'inprogress'));
    } else {
        echo "\n<br />It looks like all these quiz attempts have already been submitted.";
        echo "\n<br />Click <a href='$courseurl'>here</a> to return to the course.";
        exit;
    }
    if ($groupid > 0) {
        // Get only the students in this group.
        $studentids = explode(',', $currentquestion->groupmembers);
    }
    $numfinished = 0;
// New code from https://tracker.moodle.org/browse/MDL-37846
// We have all the attempts.
    $groupstudents = array();
    $attemptids = array();
    foreach ($attempts as $key =>$attempt) {
        if ($groupid == 0) {
            $attemptids[] = $attempt->id;
            $groupstudents[] = $attempt->userid;
        } else if (in_array($attempt->userid, $studentids)) {
            $attemptids[] = $attempt->id;
            $groupstudents[] = $attempt->userid;
        }
    }
    close_attempts($quiz, $cm, $groupstudents = array(), $attemptids);
} else {
    echo "Are you sure you want to force all the students involved in this polling session to stop 
    this polling session and have all the answers of all attempts submitted?";
    $pollingurl = $CFG->wwwroot."/mod/quiz/report.php?id=$cmid&mode=liveviewpoll&groupid=$groupid";
    $processattempturl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/process_attempt.php";
    echo "<form action='$processattempturl' method='GET'>";
    echo "<input type='hidden' name='mode' value='liveviewpoll'>";
    echo "<input type='hidden' name='cmid' value='$cmid'>";
    echo "<input type='hidden' name='groupid' value='$groupid'>";
    echo "<input type='hidden' name='confirmation' value='1'>";
    echo "<input type='submit' value='Yes I am Sure'>";
    echo "\n<br /><a href='$pollingurl'>Cancel and go back to polling page</a>";
}
function close_attempts($quiz, $cm, $groupstudents = array(), $attemptids) {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . "/user/externallib.php");
    $attempts = array();
    foreach ($attemptids as $key => $attemptid) {//echo "\n<br />debug145 and attemptid is $attemptid";
        $attempts[] = $DB->get_record('quiz_attempts', array('id' => $attemptid));
    }
    $numclosed = 0;
    foreach ($attempts as $attempt) {       
        if ($attempt->state !='finished') {
            $timestamp = time();
            $transaction = $DB->start_delegated_transaction();
            $attempt->quba= question_engine::load_questions_usage_by_activity($attempt->uniqueid);
            $attempt->quba->process_all_actions($timestamp);
            $attempt->quba->finish_all_questions($timestamp);
     
            question_engine::save_questions_usage_by_activity($attempt->quba);
     
            $attempt->timemodified = $timestamp;
            $attempt->timefinish = $timestamp;
            $attempt->sumgrades = $attempt->quba->get_total_mark();
            $attempt->state = 'finished';
            $DB->update_record('quiz_attempts', $attempt);
            // Get student name
            $studentid = $attempt->userid;
            $studentwhere = "id = $studentid";
            $students = $DB->get_records_select('user', $studentwhere);
            foreach ($students as $student) {
                // Added so that the log message is no longer than 40 characters.
                $message = '';
                $name = $student->firstname.' '.$student->lastname;
                if (strlen($name) > 23 ) {
                    $message = substr($name,0,23);
                } else {
                    $message = $name;
                }
                echo "\n<br />Attempt by $name has been closed.";
                $numclosed ++;
            }
            $transaction->allow_commit();           
        } else {               
            continue;
        }
    }
    echo "\n<br />$numclosed attempts were closed.";
}
    
echo "\n</body></html>";

