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
 * Provides most of the functions used by the /liveviewpoll/report.php script.
 *
 *
 * It has the following functions: quiz_display_instructor_interface, quiz_clear_question, quiz_send_question.
 * More functions: quiz_instructor_buttons, quiz_make_instructor_form, quiz_show_current_question.
 * More functions: quiz_update_attempts_layout, quiz_check_active_question.
 * More functions: quiz_get_questions, quiz_create_preview_icon, quiz_get_answers, quiz_random_string.
 * @package   quiz_liveviewpoll
 * @copyright 2020 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
/**
 * This function puts all the elements together for the instructors interface.
 *
 * This is the last stop before it is displayed.
 * @param int $cmid The id for the course module for this quiz instance.
 * @param int $quizid The id of this quiz instance.
 */
function quiz_display_instructor_interface($cmid, $quizid) {
    global $DB;
    global $CFG;

    $clearquestion = optional_param('clearQuestion', null, PARAM_TEXT);
    $sendquestionid = optional_param('question', 0, PARAM_INT);
    if (isset($clearquestion)) {
        quiz_clear_question($quizid);
    }
    $state = $DB->get_record('quiz', array('id' => $quizid));
    $state->mobile = 0;// Mobile not implemented for quiz.
    if ($sendquestionid) {
        quiz_send_question($quizid, $state->mobile);
    }

    $state->mobile = 0;// Not implemented yet.
    echo "<table><tr><td>".quiz_instructor_buttons($quizid)."</td>";
    echo "<td>&nbsp; &nbsp;<a href='".$CFG->wwwroot."/mod/quiz/edit.php?cmid=$cmid'>";
    echo get_string('changequestions', 'quiz_liveviewpoll')."</a></td>";
    // Add in a link to the liveview grid if that module exists in this Moodle site.
    $pathtogridreport = $CFG->dirroot.'/mod/quiz/report/liveviewgrid/report.php';
    if (file_exists($pathtogridreport)) {
        echo "<td>&nbsp; &nbsp;";
        echo "<a href='".$CFG->wwwroot."/mod/quiz/report.php?id=$cmid&mode=liveviewgrid' target = '_blank'>";
        echo get_string('quizspreadsheet', 'quiz_liveviewpoll')."</a></td>";
    }
    echo "</tr></table>";
    // Script to make the preview window a popout.
    echo "\n<script language=\"javascript\" type=\"text/javascript\">
    \n function quizpopup(id) {
        \n\t url = '".$CFG->wwwroot."/question/preview.php?id='+id+'&amp;cmid=";
        echo $cmid;
        echo "&amp;behaviour=deferredfeedback&amp;correctness=0&amp;marks=1&amp;markdp=-2";
        echo "&amp;feedback&amp;generalfeedback&amp;rightanswer&amp;history';";
        echo "\n\t newwindow=window.open(url,'Question Preview','height=600,width=800,top=0,left=0,menubar=0,";
        echo "location=0,scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent');";
        echo "\n\t if (window.focus) {newwindow.focus()}
        \n\t return false;
    \n }
    \n </script>\n";

    echo  quiz_make_instructor_form($quizid, $cmid);
    echo "<br><br>";

    if (quiz_show_current_question($quizid) == 1) {
        echo "<br>";
        echo "<br>";
        $iframeurl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/quizgraphics.php?quizid=$quizid&id=$cmid";
        echo "<iframe id= \"graphIframe\" src=\"".$iframeurl."\" height=\"540\" width=\"723\"></iframe>";
        echo "<br><br><a onclick=\"newwindow=window.open('quizpopupgraph.php?quizid=".$quizid."', '',
                'width=750,height=560,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,";
        echo "directories=no,scrollbars=yes,resizable=yes');
                return false;\"
                href=\"quizpopupgraph.php?quizid=".$quizid."\" target=\"_blank\">Open a new window for the graph.</a>";
    }
}

/**
 * This function clears the current question.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_clear_question($quizid) {
    global $DB;

    $DB->set_field('quiz_current_questions', 'question_id', '-1', array('quiz_id' => $quizid));
}


/**
 * This function sets the question in the database so the client functions can find what question is active.  And it does it fast.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_send_question($quizid) {
    global $DB;
    global $CFG;

    $myquestionid = optional_param('question', 0, PARAM_INT);// The id of the question being sent.
    $cmid = optional_param('id', 0, PARAM_INT);// The cmid of the quiz.
    $groupid = optional_param('groupid', 0, PARAM_INT);// the groupid selected by the teacher, if desired.
    if ($myquestionid > 0 && $cmid > 0) {
        quiz_update_attempt_layout($quizid, $myquestionid);
        $quiz = $DB->get_record('quiz', array('id' => $quizid));
        $record = new stdClass();
        $record->id = '';
        $record->course = $quiz->course;
        $record->cmid = $cmid;
        $record->quiz_id = $quiz->id;
        if ($groupid > 0) {
            $record->groupid = $groupid;
        }
        $record->question_id = $myquestionid;
        $record->timemodified = time();
        if ($DB->record_exists('quiz_current_questions', array('quiz_id' => $quiz->id))) {
            $mybool = $DB->delete_records('quiz_current_questions', array('quiz_id' => $quiz->id));
        }
        $lastinsertid = $DB->insert_record('quiz_current_questions', $record);
    }
}
/**
 * Sets the layout for the students so that they see the correct question.
 *
 * @param int $quizid The id for this quiz.
 * @param int $questionid The id of the question that the student should see.
 */
function quiz_update_attempt_layout($quizid, $questionid) {
    global $DB;
    if ($DB->record_exists('quiz_attempts', array('quiz' => $quizid, 'state' => 'inprogress'))) {
        $slotid = pollingslot($quizid, $questionid);
        $mylayout = $slotid.',0';
        for ($i = 1; $i < 10; $i++) {
            $mylayout = $mylayout.",$slotid,0";
        }
        $DB->set_field('quiz_attempts', 'layout', $mylayout, array('quiz' => $quizid, 'state' => 'inprogress'));
    }
}

/**
 * Return the greatest time that a student responded to a given quiz.
 *
 * This is used to determine if the teacher view of the graph should be refreshed.
 * @param int $quizcontextid The ID for the context for this quiz.
 * @return int The integer for the greatest time.
 */
function liveviewquizmaxtime($quizcontextid) {
    global $DB;
    $quiztime = $DB->get_records_sql("
        SELECT max(qa.timemodified)
        FROM {question_attempts} qa
        JOIN {question_usages} qu ON qu.id = qa.questionusageid
        WHERE qu.contextid = ?", array($quizcontextid));
    foreach ($quiztime as $qkey => $qtm) {
        $qmaxtime = intval($qkey) + 1;
    }
    return $qmaxtime;
}


/**
 * Make the button controls on the instructor interface.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_instructor_buttons($quizid) {
    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the quiz instance.
    $querystring = 'mode=liveviewpoll';
    if ($mycmid) {
        $querystring .= '&id='.$mycmid;
    }

    $disabled = "";
    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    if (!quiz_check_active_question($quizid)) {
        $disabled = "disabled=\"disabled\"";
    }

    $myform .= "<input type=\"submit\" value=\"".get_string('stoppolling', 'quiz_liveviewpoll');
    $myform .= "\" name=\"clearQuestion\" ".$disabled."/>\n</form>\n";

    return($myform);
}


/**
 * This function create the form for the instructors (or anyone higher than a student) to view.
 *
 * @param int $quizid The id of this quiz instance
 * @param int $cmid The id of this quiz course module.
 */
function quiz_make_instructor_form($quizid, $cmid) {
    global $CFG;
    global $PAGE;

    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the quiz instance.
    $mode = optional_param('mode', '', PARAM_TEXT);
    $groupid = optional_param('groupid', 0, PARAM_INT);
    if ($mycmid) {
        $querystring = 'id='.$mycmid.'&mode='.$mode.'&groupid='.$groupid;
    } else if ($cmid > 0) {
        $querystring = 'id='.$cmid.'&mode='.$mode.'&groupid='.$groupid;
    } else {
        $querystring = '';
    }

    $myform = "<style>
            p {
            margin-bottom:0rem
            }
            </style>";
    $myform .= "<form action=\"?".$querystring."\" method=\"post\">\n<table border=0>";
    foreach (quiz_get_questions($quizid) as $items) {
        $previewurl = $CFG->wwwroot.'/question/preview.php?id='.
            $items['id'].'&cmid='.$cmid.
            '&behaviour=deferredfeedback&correctness=0&marks=1&markdp=-2&feedback&generalfeedback&rightanswer&history';
        $myform .= "\n<tr><td style=\"valign:top\">".get_string('sendquestion', 'quiz_liveviewpoll');
        $myform .= "</td><td><input type=\"submit\" name=\"question\" value=\"".$items['id']."\" />";
        $myform .= "</td><td><a href=\"$previewurl\" onclick=\"return quizpopup('".$items['id']."')\" target=\"_blank\">";
        $myform .= quiz_create_preview_icon()."</a></td>";
        $graphurl = $CFG->wwwroot.'/mod/quiz/report/liveviewpoll/quizgraphics.php?
            question_id='.$items['id']."&quizid=".$quizid."&norefresh=1";
        $myform .= "<td><a href=\"".$graphurl."\" target=\"_blank\" title='".get_string('graphtooltip', 'quiz_liveviewpoll')."'>";
        $myform .= get_string('graph', 'quiz_liveviewpoll')."</a></td>";
        $myform .= "<td style=\"margin-bottom:20rem\">".$items['question']."</td></tr>\n";
    }
    $myform .= "\n</table></form>";
    return($myform);
}


/**
 * This function finds the current question that is active for the quiz that it was requested from.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_show_current_question($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_current_questions', array('quiz_id' => $quizid))) {
        $question = $DB->get_record('quiz_current_questions', array('quiz_id' => $quizid));
        if ($question->question_id == -1) {
            // This plugin uses -1 for the questionid to indicate that polling has stopped or has not started.
            echo get_string('nocurrentquestion', 'quiz_liveviewpoll');
            return(0);
        }
        $questiontext = $DB->get_record('question', array('id' => $question->question_id));
        echo get_string('currentquestionis', 'quiz_liveviewpoll')." -> ".$questiontext->questiontext;
        return(1);
    } else {
        return(0);
    }
}
/**
 * Function to add javascript_refresh.js to each qustion.
 *
 * @param int $quizid The id for the current quiz.
 */
function addrefreshscript($quizid) {
    global $DB;
    global $CFG;
    if (!($quizid > 0)) {
        echo "\n<br />Error. No quizid was furnished.";
        exit;
    }
    $editliburl = $CFG->dirroot.'/question/editlib.php';
    include_once($editliburl);
    $slots = $DB->get_records('quiz_slots', array('quizid' => $quizid));
    foreach ($slots as $slot) {
        $questionid = $slot->questionid;
        $question = $DB->get_record('question', array('id' => $questionid));
        $questiontext = $question->questiontext;
        if (!(preg_match("/\<polling\>\<\/polling\>/", $questiontext))) {
            $script = "<polling></polling>\n<script src=\"report/liveviewpoll/javascript_refresh.js\">\n</script>";
            $newquestiontext = $script.$questiontext;
            if ($DB->set_field('question', 'questiontext', $newquestiontext, array('id' => $questionid))) {
                // Clear cache for this question since the question has changed.
                question_bank::notify_question_edited($questionid);
            } else {
                echo "\n<br />Update not successful for question id = $questionid";
            }
        }
    }
}

/**
 * The function finds out is there a question active?
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_check_active_question($quizid) {
    global $DB;

    if ($currentquestion = $DB->get_record('quiz_current_questions', array('quiz_id' => $quizid))) {
        if ($currentquestion->question_id > 0) {
            return(1);
        } else {
            return(0);
        }
    } else {
        return(0);
    }
}

/**
 * Get the questions in any context (like the instructor).
 *
 * @param int $quizid The id for this quiz instance.
 */
function quiz_get_questions($quizid) {
    global $DB;
    global $CFG;
    $q = '';
    $pagearray2 = array();
    $questions = array();
    if ($slots = $DB->get_records('quiz_slots', array('quizid' => $quizid))) {
        foreach ($slots as $slot) {
            $questions[] = $slot->questionid;
        }
    }
    // Get the questions and stuff them into an array.
    foreach ($questions as $q) {
        if (empty($q)) {
            continue;
        }
        $aquestions = $DB->get_record('question', array('id' => $q));
        if (isset($aquestions->questiontext)) {
            $qtext = explode('</script><polling></polling>', $aquestions->questiontext);
            $myqtext = $qtext[count($qtext) - 1];
            $pagearray2[] = array('id' => $q, 'question' => $myqtext,
                'answers' => quiz_get_answers($q));
        }
    }
    return($pagearray2);
}

/**
 * This function creates the HTML tag for the preview icon.
 */
function quiz_create_preview_icon() {
    global $CFG;
    global $PAGE;
    $previewimageurl = $CFG->wwwroot.'/theme/image.php/'.$PAGE->theme->name.'/core/'.$CFG->themerev.'/t/preview';
    $imgtag = "<img alt='".get_string('previewquestion', 'quiz_liveviewpoll');
    $imgtag .= "' class='smallicon' title='Preview question' src='$previewimageurl' />";
    return $imgtag;
}

/**
 * Get Answers For a particular question id.
 * @param int $questionid The id of the question that has been answered in this quiz.
 */
function quiz_get_answers($questionid) {
    global $DB;
    global $CFG;
    $line = "";
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    foreach ($answers as $answers) {
        $line .= $answers->answer;
        $line .= "&nbsp;";
    }
    return($line);
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
}