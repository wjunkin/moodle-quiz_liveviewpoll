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
$norefresh = optional_param('norefresh', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);
$quiz = $DB->get_record('quiz', array('id' => $quizid));
$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
if (!(has_capability('mod/quiz:manage', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}

// Usually only want answers after the question was sent, so need to find out when the question was sent.
if ($questionqid) {
    $timesent = 0;
    $questionid = $questionqid;
    $questiontext = $DB->get_record('question', array('id' => $questionid));

} else {
    if ($groupid > 0) {
        $question = $DB->get_record('quiz_current_questions', array('quiz_id' => $quizid, 'groupid' => $groupid));
        $users = explode(',', $question->groupmembers);
    } else {
        $question = $DB->get_record('quiz_current_questions', array('quiz_id' => $quizid));
    }
    $questionid = $question->question_id;
    $questiontext = $DB->get_record('question', array('id' => $questionid));
    $timesent = $question->timemodified;
}

$questionanswerids = array();
$multitype = array('multichoice', 'truefalse', 'calculatedmulti');
if (!($questiontext = $DB->get_record('question', array('id' => $questionid)))) {
    echo "\n<br />You must submit a valid questionid";
    exit;
}
if (in_array($questiontext->qtype, $multitype)) {
    $order = true;
} else {
    $order = false;
}
$qanswerids = array();

// For those questions that have answers, get the possible answers and create the labels for the histogram.
if ($answers = $DB->get_records('question_answers', array('question' => $questionid))) {
    $labels = '';
    $n = 0;
    foreach ($answers as $answer) {
        if ($order) {
            $qanswerids[$n] = $answer->id;// Needed for truefalse questions.
            $labels .= "&x[$n]=".substr(strip_tags($answer->answer), 0, 15);
            $n++;
        }
        if ($questiontext->qtype == 'ddwtos') {
            $qanswertext[$n + 1] = $answer->answer;
            $n++;
        }
    }
}

$stans = array();// The string of answers for each student to this question, indexed by the $userid.

$allquizattempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid));
// An array of all question answers, one per user.
$quizattpt = array();
// An array of all question answers after the question was sent, one per user.
$quizattempts = array();
foreach ($allquizattempts as $attempt) {
    if ($groupid > 0) {
        if (in_array($attempt->userid, $users)) {
            $quizattpt[$attempt->userid] = $attempt->id;
            if ($attempt->timemodified > $timesent) {
                $quizattempts[$attempt->userid] = $attempt;
            }
        }
    } else {
        $quizattpt[$attempt->userid] = $attempt->id;
        if ($attempt->timemodified > $timesent) {
            $quizattempts[$attempt->userid] = $attempt;
        }
    }
}
echo '<html><head>';
if (isset($meta)) {
    echo $meta;
}
echo '</head><body>';
if (!($norefresh)) {
    echo "\n<script src=\"javascript_teach_refresh.js\">";
    echo "\n</script>";
}

foreach ($quizattempts as $quizattempt) {
    $userid = $quizattempt->userid;
    $uniqueid = $quizattempt->uniqueid;
    $questionattempts = $DB->get_records('question_attempts', array('questionusageid' => $uniqueid, 'questionid' => $questionid));
    foreach ($questionattempts as $questionattempt) {
        $attemptid = $questionattempt->id;
        $attemptsteps = $DB->get_records('question_attempt_steps', array('questionattemptid' => $attemptid));
        foreach ($attemptsteps as $attemptstep) {
            // Every time a student looks at a question, this generates a new question_attempt_step.
            // Submitting one answer can generate several rows in the question_attempt_step_data table.
            $stanswer = array();// The array of questionanswerids for this student for multichoice with several answers.
            $attemptstepid = $attemptstep->id;
            $attemptdata = $DB->get_records('question_attempt_step_data',  array('attemptstepid' => $attemptstepid));
            foreach ($attemptdata as $datainfo) {
                $name = $datainfo->name;
                $value = $datainfo->value;
                if ($name == '_order') {
                    // The order step_data should always occur before the answers or choices, except for truefalse.
                    $questionanswerids = explode(',', $value);
                } else if ($attemptstep->state == 'complete') {
                    if ($name == 'answer') {
                        if ($order) {
                            if ($questiontext->qtype == 'truefalse') {
                                $truefalseindex = 1 - $value;
                                $stans[$userid] = $qanswerids[$truefalseindex];
                            } else {
                                $stans[$userid] = $questionanswerids[$value];
                            }
                        } else {
                            $stans[$userid] = $value;
                        }
                    }
                    if (preg_match('/choice(\d)/', $name, $matches)) {
                        if ($value > 0) {
                            $stanswer[] = $questionanswerids[$matches[1]];
                        }
                    }
                    if (preg_match('/p(\d)/', $name, $matches)) {
                        if ($value > 0) {
                            $stanswer[] = $qanswertext[$value];
                        }
                    }
                }
            }
            if (count($stanswer)) {
                $stans[$userid] = implode(',', $stanswer);
            }
        }
    }
}

// Starting the iframe output.
echo get_string('responsessofar', 'quiz_liveviewpoll').count($stans)."/".count($quizattpt);
if ($order) {
    $myx = array();
    foreach ($qanswerids as $qanswerid) {
        $myx[$qanswerid] = 0;
    }
    foreach ($stans as $key => $value) {
        if (strlen($value) > 0) {
            $values = explode(',', $value);
            foreach ($values as $qansid) {
                if (isset($myx[$qansid])) {
                    $myx[$qansid] ++;
                } else {
                    echo "\n<br />Something is wrong with answer id $qansid";
                }
            }
        }
    }

    $graphinfo = "?data=".implode(",", $myx).$labels."&total=10";
    $graphicurl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/graph.php";
    echo "\n<br /><img src=\"".$graphicurl.$graphinfo."&cmid=".$cm->id."\"></img>";
} else {
    echo "\n<br />";
    $quizgraphicsurl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/quizgraphics.php";
    if ($showstudents) {
        echo "<a href='".$quizgraphicsurl."?quizid=$quizid&showstudents=0'>";
        echo get_string('hidenames', 'quiz_liveviewpoll')."</a>";
    } else {
        echo "<a href='".$quizgraphicsurl."?quizid=$quizid&showstudents=1'>";
        echo get_string('shownames', 'quiz_liveviewpoll')."</a>";
    }
    foreach ($stans as $usr => $textanswer) {
        echo "\n<br />";
        if ($showstudents) {
            $user = $DB->get_record('user', array('id' => $usr));
            echo $user->firstname." ".$user->lastname.": ";
        }
        echo strip_tags($textanswer);
    }
}
echo '</body></html>';
