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
 * More functions: quiz_update_attempts_layout, quiz_check_active_question, quiz_java_graphupdate.
 * More functions: quiz_get_questions, quiz_create_preview_icon, quiz_get_answers, quiz_random_string.
 * @package   quiz_liveviewpoll
 * @copyright 2018 w. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * This function puts all the elements together for the instructors interface.
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

    quiz_java_graphupdate($quizid, $cmid);
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
        $iframeurl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/quizgraphics.php?quizid=".$quizid;
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

    $slot = $DB->get_record('quiz_slots', array('quizid' => $quizid, 'slot' => 1));
    // The question saying that there is not active question should always be in slot 1.
    $myquestionid = $slot->questionid;

    $quiz = $DB->get_record('quiz', array('id' => $quizid));
    $record = new stdClass();
    $record->id = '';
    $record->course = $quiz->course;
    $record->ipal_id = 0;
    $record->quiz_id = $quiz->id;
    $record->question_id = -1;
    $record->timemodified = time();
    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quiz->id))) {
        $mybool = $DB->delete_records('quiz_active_questions', array('quiz_id' => $quiz->id));
    }
    $lastinsertid = $DB->insert_record('quiz_active_questions', $record);
    quiz_update_attempts_layout($quizid, $myquestionid);// Hopefully not needed if better communication is developed.
}


/**
 * This function sets the question in the database so the client functions can find what quesiton is active.  And it does it fast.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_send_question($quizid) {
    global $DB;
    global $CFG;

    $myquestionid = optional_param('question', 0, PARAM_INT);// The id of the question being sent.

    $quiz = $DB->get_record('quiz', array('id' => $quizid));
    $record = new stdClass();
    $record->id = '';
    $record->course = $quiz->course;
    $record->ipal_id = 0;
    $record->quiz_id = $quiz->id;
    $record->question_id = $myquestionid;
    $record->timemodified = time();
    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quiz->id))) {
        $mybool = $DB->delete_records('quiz_active_questions', array('quiz_id' => $quiz->id));
    }
    $lastinsertid = $DB->insert_record('quiz_active_questions', $record);
    quiz_update_attempts_layout($quizid, $myquestionid);// Hopefully not needed if better communication is developed.
}


/**
 * Prints out the javascript so that the display is updated whenever a student submits an answer.
 *
 * This is done by seeing if the most recent timemodified (supplied by graphicshash.php) has changed.
 * @param int $quizid The id for this quiz.
 * @param int $cmid The id in the course_modules table for this quiz.
 */
function quiz_java_graphupdate($quizid, $cmid) {
    global $DB;
    global $CFG;
    $iframeurl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/quizgraphics.php?quizid=".$quizid;
    $graphicshashurl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/graphicshash.php?id=".$cmid;
    if ($configs = $DB->get_record('config', array('name' => 'sessiontimeout'))) {
        $timeout = intval($configs->value);
    } else {
        $timeout = 7200;
    }
    echo "\n<div id='timemodified' name='-1'></div>";
    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";\nvar myCount=0;
            \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
            {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
        echo "\n\nfunction replace() { ";
        $t = '&t='.time();
        echo "\n x=document.getElementById('timemodified');";
        echo "\n myname = x.getAttribute('name');";
        echo "\nvar t=setTimeout(\"replace()\",3000);\nhttp.open(\"GET\", \"".$graphicshashurl.$t."\", true);";
        echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {";
        echo "\n if((parseInt(http.responseText) != parseInt(myname)) && (myCount < $timeout/3)){";
        echo "\n    document.getElementById('graphIframe').src=\"".$iframeurl."\"";
        echo "\n x.setAttribute('name', http.responseText)";
        echo "\n}\n}\n}";
        echo "\n http.send(null);";
        echo "\nmyCount++}\n\nreplace();";
    echo "\n</script>";
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
    if ($mycmid) {
        $querystring = 'id='.$mycmid.'&mode='.$mode;
    } else if ($cmid > 0) {
        $querystring = 'id='.$cmid.'&mode='.$mode;
    } else {
        $querystring = '';
    }

    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    foreach (quiz_get_questions($quizid) as $items) {
        $previewurl = $CFG->wwwroot.'/question/preview.php?id='.
            $items['id'].'&cmid='.$cmid.
            '&behaviour=deferredfeedback&correctness=0&marks=1&markdp=-2&feedback&generalfeedback&rightanswer&history';
        $myform .= "\nSend Question <input type=\"submit\" name=\"question\" value=\"".$items['id']."\" />";
        $myform .= "\n<a href=\"$previewurl\" onclick=\"return quizpopup('".$items['id']."')\" target=\"_blank\">";
        $myform .= quiz_create_preview_icon()."</a>";
        $graphurl = $CFG->wwwroot.'/mod/quiz/report/liveviewpoll/quizgraphics.php?question_id='.$items['id']."&quizid=".$quizid;
        $myform .= "\n<a href=\"".$graphurl."\" target=\"_blank\" title='".get_string('graphtooltip', 'quiz_liveviewpoll')."'>";
        $myform .= get_string('graph', 'quiz_liveviewpoll')."</a>";
        $myform .= "\n".$items['question']."<br />\n";
    }
/**    if (quiz_check_active_question($quizid)) {
        $myform .= "<input type=\"submit\" value=\"".get_string('sendquestion', 'quiz_liveviewpoll')."\" />\n</form>\n";
    } else {
        $myform .= "<input type=\"submit\" value=\"".get_string('startpolling', 'quiz_liveviewpoll')."\" />\n</form>\n";
    }
*/
    return($myform);
}


/**
 * This function finds the current question that is active for the quiz that it was requested from.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_show_current_question($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        $question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
        if ($question->question_id == -1) {
            // This plugin uses -1 for the questionid to indicate that polling has stopped or has not started.
            echo "There is no current question.";
            return(0);
        }
        $questiontext = $DB->get_record('question', array('id' => $question->question_id));
        echo get_string('currentquestionis', 'quiz_liveviewpoll')." -> ".strip_tags($questiontext->questiontext);
        return(1);
    } else {
        return(0);
    }
}

/**
 * This function changes the layout field in the quiz_attempts table so that the student gets the correct question.
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $questionid The id of the active question.
 */
function quiz_update_attempts_layout($quizid, $questionid) {
    global $DB;
    $slot = $DB->get_record('quiz_slots', array('quizid' => $quizid, 'questionid' => $questionid));
    $p = $slot->page;
    $quizattempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid));
    $record = new stdClass();
    $layout = "$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0";
    $layout .= ",$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0,$p,0";
    $record->layout = $layout;
    foreach ($quizattempts as $quizattempt) {
        $record->id = $quizattempt->id;
        $DB->update_record('quiz_attempts', $record, true);
    }
}

/**
 * The function finds out is there a question active?
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_check_active_question($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        return(1);
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
            $aquestions->questiontext = strip_tags($aquestions->questiontext);
            $pagearray2[] = array('id' => $q, 'question' => $aquestions->questiontext,
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
 * Function to create a no_current_question question (telling students there is no active/current question) if it does not exist.
 *
 * The question is created in the default category for the course and the name of the question is no_current_question essay.
 * The function requires the quiz_random_string() function.
 * @param int $courseid The id of the course
 * @return the questionid if the question is found or if the creation is successful.
 */
function quiz_nocurrentq_create($courseid) {
    global $DB;
    global $USER;
    global $COURSE;
    global $CFG;
    $contextid = $DB->get_record('context', array('instanceid' => "$courseid", 'contextlevel' => '50'));
    $mycontextid = $contextid->id;
    $categories = $DB->get_records_menu('question_categories', array('contextid' => "$mycontextid"));
    $categoryid = 0;
    foreach ($categories as $key => $value) {
        if (preg_match("/Default\ for/", $value)) {
            if (($value == "Default for ".$COURSE->shortname) or ($categoryid == 0)) {
                $categoryid = $key;
            }
        }
    }
    if (!($categoryid > 0)) {
        debugging('Error obtaining category id for default question category.');
        return false;
    }
    $nocurrentqfind = $DB->count_records('question', array('category' => "$categoryid",
        'name' => 'no_current_question'));
    if ($nocurrentqfind > 0) {
        $nocurrentqs = $DB->get_records('question', array('category' => "$categoryid",
        'name' => 'no_current_question'));
        foreach ($nocurrentqs as $nocurrentq) {
            $nocurrentqid = $nocurrentq->id;
        }
        return $nocurrentqid;
    }
    $hostname = 'unknownhost';
    if (!empty($_SERVER['HTTP_HOST'])) {
        $hostname = $_SERVER['HTTP_HOST'];
    } else if (!empty($_ENV['HTTP_HOST'])) {
        $hostname = $_ENV['HTTP_HOST'];
    } else if (!empty($_SERVER['SERVER_NAME'])) {
        $hostname = $_SERVER['SERVER_NAME'];
    } else if (!empty($_ENV['SERVER_NAME'])) {
        $hostname = $_ENV['SERVER_NAME'];
    }
    $questionfieldarray = array('category', 'parent', 'name', 'questiontext', 'questiontextformat', 'generalfeedback',
        'generalfeedbackformat', 'defaultmark', 'penalty', 'qtype', 'length', 'stamp', 'version', 'hidden',
        'timecreated', 'timemodified', 'createdby', 'modifiedby');
    $questionnotnullarray = array('name', 'questiontext', 'generalfeedback');
    $questioninsert = new stdClass();
    $date = gmdate("ymdHis");
    $questioninsert->category = $categoryid;
    $questioninsert->parent = 0;
    $questioninsert->questiontextformat = 1;
    $questioninsert->generalfeedback = ' ';
    $questioninsert->generalfeedbackformat = 1;
    $questioninsert->defaultmark = 1;
    $questioninsert->penalty = 0;
    $questioninsert->length = 1;
    $questioninsert->hidden = 0;
    $questioninsert->timecreated = time();
    $questioninsert->timemodified = time();
    $questioninsert->createdby = $USER->id;
    $questioninsert->modifiedby = $USER->id;
    $questioninsert->name = 'no_current_question';// Title.
    $questioninsert->questiontext = '<p>There is no active question right now. Please wait.</p>';
    $questioninsert->qtype = 'essay';
    $questioninsert->stamp = $hostname .'+'. $date .'+'.quiz_random_string(6);
    $questioninsert->version = $questioninsert->stamp;
    $nocurrentqid = $DB->insert_record('question', $questioninsert);
    $essayoptions = new stdClass;
    $essayoptions->questionid = $nocurrentqid;
    $essayoptions->responseformat = 'noinline';
    $essayoptions->responserequired = 0;
    $essayoptions->responsefieldlines = 0;
    $essayoptions->attachments = 0;
    $essayoptions->attachmentsrequired = 0;
    $essayoptionsid = $DB->insert_record('qtype_essay_options', $essayoptions);
    return $nocurrentqid;
}


/**
 * Function to generate the random string required to identify questions.
 *
 * @param int $length The length of the string to be generated.
 * @return string The random string.
 */
function quiz_random_string($length = 15) {
    $pool  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $pool .= 'abcdefghijklmnopqrstuvwxyz';
    $pool .= '0123456789';
    $poollen = strlen($pool);
    mt_srand ((double) microtime() * 1000000);
    $string = '';
    for ($i = 0; $i < $length; $i++) {
        $string .= substr($pool, (mt_rand() % ($poollen)), 1);
    }
    return $string;
}

/**
 * This function puts the desired question in the first slot of the quiz and moves the question that was there to another slot.
 *
 * This function also checks that there is one question per page and that the questions are not shuffled.
 * @param int $quizid The id of the quiz for which this is done.
 * @param int $questionid The id of the desired question that will be put in the first slot.
 * @return string The $message string is returned if anything goes wrong.
 **/
function quiz_add_firstquestion_to_quiz($quizid, $questionid) {
    global $DB;
    $message = '';// The returned message giving the result from this function.
    $insert = true;// The boolean to let the program know if the question has to be inserted.
    if (!($quizslots = $DB->get_records('quiz_slots', array('quizid' => $quizid)))) {
        $message .= "Error. There are no questions for this quiz.";
        return $message;
    }
    // Putting -1 in the field questionid. There should be no entry with this quizid in the quiz_active_questions table.
    if ($DB->get_records('quiz_active_questions', array('quiz_id' => $quizid))) {
        $message .= "\nError. This quiz is already being used by a quiz instance.";
        return $message;
    } else {
        $record3 = new stdClass();
        $record3->quiz_id = $quizid;
        $quiz = $DB->get_record('quiz', array('id' => $quizid));
        $record3->course_id = $quiz->course;
        $record3->question_id = -1;
        $record3->ipal_is = 0;
        $record3->timemodified = time();
    }
    $page = array();// An array to keep track of how many pages are in the quiz.
    foreach ($quizslots as $quizslot) {
        if ($quizslot->slot == 1) {
            $oldfirstslot = $quizslot;
        }
        if ($quizslot->questionid == $questionid) {
            // The desired question is already in the quiz. If necessary it will be moved.
            $priordesiredquestion = $quizslot;
            if ($quizslot->slot == 1) {
                if (!($insertid = $DB->insert_record('quiz_active_questions', $record3))) {
                    $message .= "\n<br />There was a problem inserting a -1 into the quiz_active_questions table.";
                    return $message;
                }
                // The desired question is in the correct location. Hopefully, there is no message.
                return $message;
            }
        }
        $page[$quizslot->page] = 1;
    }
    if (count($page) != count($quizslots)) {
        $message .= "There is more than one question per page. This must be fixed.";
        return $message;
    }
    // Make sure that questions aren't shuffled.
    $quizsection = $DB->get_record('quiz_sections', array('quizid' => $quizid));
    if ($quizsection->shufflequestions > 0) {
        $record = new stdClass();
        $record->id = $quizsection->id;
        $record->shufflequestions = 0;
        $DB->update_record('quiz_sections', $record);
        $message .= "The questions are no longer shuffled.";
    }
    // The questions are displayed by slot, not by page, in the quiz preview and ipal with quiz.
    if (!(isset($oldfirstslot))) {
        $message .= "For some reason there was no question is slot 1 for this quiz.";
        return $message;
    } else {
        // Put the desired question in this slot.
        $record1 = new stdClass();
        $record1->id = $oldfirstslot->id;
        $record1->slot = 1;
        $record1->quizid = $quizid;
        $record1->questionid = $questionid;
        $record1->page = $oldfirstslot->page;// This is probably 1, but we don't want more than one question per page.
        if (isset($priordesiredquestion)) {
            // The question was already in the quiz. We now move it to slot 1.
            $record1->requireprevious = $priordesiredquestion->requireprevious;
            $record1->maxmark = $priordesiredquestion->maxmark;
        } else {
            $record1->requireprevious = 0;
            $record1->maxmark = '0.000';
        }

        if (!($DB->update_record('quiz_slots', $record1))) {
            $message .= " something went wrong when trying to put question with id = $questionid into slot 1";
            return;
        }
        // Put oldfirstslot into the quiz.
        $record2 = new stdClass();
        $record2->quizid = $quizid;
        $record2->questionid = $oldfirstslot->questionid;
        $record2->requireprevious = $oldfirstslot->requireprevious;
        $record2->maxmark = $oldfirstslot->maxmark;
        if (isset($priordesiredquestion)) {
            // Put the old first slot question where the desired question used to be.
            $record2->id = $priordesiredquestion->id;
            $record2->page = $priordesiredquestion->page;
            $record2->slot = $priordesiredquestion->slot;
            if (!($DB->update_record('quiz_slots', $record2))) {
                $message .= " An error moving questionid = ".$oldfirstslot->questionid." to slot ".$priordesiredquestion->slot;
                return $message;
            }
        } else {
            // The old first slot question will have to be inserted into the quiz_slots table.
            // Probably there is no slot greater than the number of questions.
            $newslot = count($quizslots) + 1;
            if (($DB->get_record('quiz_slots', array('quizid' => $quizid, 'slot' => $newslot))) or
                ($DB->get_record('quiz_slots', array('quizid' => $quizid, 'page' => $newslot)))) {
                $message .= " Somehow this quiz already had more slots or pages than questions.";
                return $message;
            } else {
                $record2->slot = $newslot;
                $record2->page = $newslot;
                if (!($newslotid = $DB->insert_record('quiz_slots', $record2))) {
                    $message .= " Something went wrong trying to insert the question in slot one into a new slot.";
                    return $message;
                }
            }
        }

        if (!($insertid = $DB->insert_record('quiz_active_questions', $record3))) {
            $message .= "\n<br />There was a problem inserting a -1 into the quiz_active_questions table.";
            return $message;
        }
    }
    return $message;
}