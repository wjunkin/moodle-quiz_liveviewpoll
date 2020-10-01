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
 * This page removes the polling feature from quizzes and questions and reports on the restoring.
 *
 * @package   quiz_liveviewpoll
 * @copyright 2020 William Junkin <junkinwf@eckerd.edu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(dirname(__FILE__))))).'/config.php');
$cmid = optional_param('cmid', 0, PARAM_INT);
if ($cmid > 0) {
    $cm = $DB->get_record('course_modules', array('id' => $cmid));
    $course = $DB->get_record('course', array('id' => $cm->course));
} else {
    echo get_string('nocmid', 'quiz_liveviewpoll');
    exit;
}
$course = $DB->get_record('course', array('id' => $cm->course));
require_login($course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/quiz:view', $context);
echo "<html><head><title>".get_string('restorequizzes', 'quiz_liveviewpoll')."</title></head><body>";
// Restore the checked quizzes and remove javascript from the questions.
$checked = array();
$checked = optional_param_array('pquiz', null, PARAM_INT);
$checkedquestions = array(); // The array (indexed by ids of all the questions in checked quizzes.
$checkedqrestored = array(); // The array (indexed by ids) of all the questions restored in checked quizzes.
$checkedqleft = array(); // The array (indexed by ids) of all the quesitons in checked quizzes but not restored.
if (count($checked) > 0) {
    echo get_string('quizzesrestored', 'quiz_liveviewpoll');
    echo get_string('pollremoved',  'quiz_liveviewpoll');
    foreach ($checked as $key => $value) {
        if ($DB->record_exists('quiz_current_questions', array('id' => $key))) {
            $success = true;
            $currentquestion = $DB->get_record('quiz_current_questions', array('id' => $key));
            $deletedquiz = $DB->get_record('quiz', array('id' => $currentquestion->quiz_id));
            $DB->delete_records('quiz_current_questions', array('id' => $key));
        } else {
            $success = false;
        }
        if ($success) {
            echo "\n<li>".$deletedquiz->name;
            if ($currentquestion->groupid > 0) {
                $group = $DB->get_record('groups', array('id' => $currentquestion->groupid));
                echo " (for group -- ".$group->name.")";
            }
            echo " -- Success</li>";
        } else {
            echo get_string('alreadyremoved', 'quiz_liveviewpoll');
        }

        if ($success) {
            $checkedqslots = $DB->get_records('quiz_slots', array('quizid' => $currentquestion->quiz_id));
            foreach ($checkedqslots as $checkedqslot) {
                $checkedquestions[$checkedqslot->questionid] = $checkedqslot->questionid;
            }
        }
    }
    echo "\n</ul>";
    // Find out if any questions that are not in polling quizzes
    // (now that some quizzes have been removed from quiz_active_questions table) have polling javascript in the text.
    $pollquests = array();// The array of the ids of all the questions used in polling. Duplicates are OK.
    $pollingquizzes = $DB->get_records('quiz_current_questions');
    foreach ($pollingquizzes as $key => $pquiz) {
        $slots = $DB->get_records('quiz_slots', array('quizid' => $pquiz->quiz_id));
        foreach ($slots as $slot) {
            $pollquests[] = $slot->questionid;
        }
    }
    $sql = "SELECT * FROM {question} WHERE  questiontext LIKE '%<polling></polling>%'";
    $qwithjava = $DB->get_records_sql($sql);
    foreach ($qwithjava as $key => $pollingquestion) {
        if (in_array($pollingquestion->id, $pollquests)) {
            if (in_array($pollingquestion->id, $checkedquestions)) {
                $checkedqleft[$pollingquestion->id] = $pollingquestion->name;
            }
        } else {
            remove_qjavascript($pollingquestion->id);
            if (in_array($pollingquestion->id, $checkedquestions)) {
                $checkedqrestored[$pollingquestion->id] = $pollingquestion->name;
            }
        }
    }
}
if ((count($checkedqrestored) > 0) || (count($checkedqleft) > 0)) {
    // Script to hide or display the option form.
    echo "\n<script>";
    echo "\nfunction optionfunction() {";
    echo "\n  var e=document.getElementById(\"option1\");";
    echo "\n  var b=document.getElementById(\"button1\");";
    echo "\n  if(e.style.display == \"none\") { ";
    echo "\n      e.style.display = \"block\";";
    echo "\n        b.innerHTML = \"".get_string('clickhidedetails', 'quiz_liveviewpoll')."\";";
    echo "\n  } else {";
    echo "\n      e.style.display=\"none\";";
    echo "\n      b.innerHTML = \"".get_string('clickshowdetails', 'quiz_liveviewpoll')."\";";
    echo "\n  }";
    echo "\n}";
    echo "\n</script>  ";
    echo "\n<button id='button1' type='button'  onclick=\"optionfunction()\">";
    echo get_string('clickshowdetails', 'quiz_liveviewpoll')."</button>";
    echo "\n<div class='myoptions' id='option1' style=\"display:none;\">";
    echo get_string('details', 'quiz_liveviewpoll');

    if (count($checkedqrestored) > 0) {
        echo get_string('removedfromquestions', 'quiz_liveviewpoll');
        echo "\n<ul>";
        foreach ($checkedqrestored as $qname) {
            echo "\n<li>$qname</li>";
        }
        echo "\n</ul>";
    }
    if (count($checkedqleft) > 0) {
        echo get_string('notremovedfromquestions', 'quiz_liveviewpoll');
        echo "\n<ul>";
        foreach ($checkedqleft as $qname) {
            echo "\n<li>$qname</li>";
        }
        echo "\n</ul>";
    }
    echo "\n</div>";
}
echo get_string('featureinfo', 'quiz_liveviewpoll');

$quizzes = $DB->get_records('quiz_current_questions', array());
$myquizzes = array();// Array of all quizids of the polling quizzes this user can manage.
foreach ($quizzes as $quiz) {
    $quizid = $quiz->quiz_id;
    $pollquizzes[$quizid] = 1;
    $quizcmid = $quiz->cmid;
    $canmanage = false;
    $canaccess = false;
    $mycm = $DB->get_record('course_modules', array('id' => $quizcmid));
    $mycontext = context_module::instance($mycm->id);
    if (has_capability('mod/quiz:manage', $mycontext)) {
        $canmanage = true;
    }
    if (($quiz->groupid == 0) || (has_capability('moodle/site:accessallgroups', $mycontext)) ||
        ($DB->get_record('groups_members', array('groupid' => $quiz->groupid, 'userid' => $USER->id)))) {
        $canaccess = true;
    }
    if ($canmanage && $canaccess) {
        $myquizzes[$quiz->id] = $quiz;
    }
}
// Take care of the case where the groupid > 0 but the teacher can access all the given groups.

foreach ($myquizzes as $key => $value) {
    // Quizid is the $key.
    $thisquiz[$value->quiz_id] = $DB->get_record('quiz', array('id' => $value->quiz_id));
}

/**
 * Function to remove javascript from questions.
 *
 * @param int $questionid The id for the question whose refreshing javascript is removed.
 */
function remove_qjavascript($questionid) {
    global $CFG, $DB;
    $script = '<polling><\/polling>\s+<script src="report\/liveviewpoll\/javascript_refresh.js">\s+<\/script>';
    $editliburl = $CFG->dirroot.'/question/editlib.php';
    include_once($editliburl);
    $questiontext = $DB->get_record('question', array('id' => $questionid));
    $newtext = preg_replace("/$script/m", '', $questiontext->questiontext);
    if (strlen($newtext) < strlen($questiontext->questiontext)) {
        if ($DB->set_field('question', 'questiontext', $newtext, array('id' => $questionid))) {
            // Clear cache for this question since the question has changed.
            question_bank::notify_question_edited($questionid);
        } else {
            echo get_string('updatenotsuccess', 'quiz_liveviewpoll').$questionid;
        }
    } else {
        echo get_string('nopollwithquestion', 'quiz_liveviewpoll').$questionid;
    }
}
echo "\n<br /><form method='POST'>";
echo "\n<input type='hidden' name='cmid' value='$cmid'>";
foreach ($myquizzes as $key => $value) {
    echo "\n<br /><input type='checkbox' name='pquiz[$key]' value=$key>".$thisquiz[$value->quiz_id]->name;
    if ($value->groupid > 0) {
        $group = $DB->get_record('groups', array('id' => $value->groupid));
        echo " (for group -- ".$group->name.")";
    }
}
echo "\n<br /><input type='submit' value='Submit'>";
echo "\n</form>";
echo "</body></html>";