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
 * @param int $cmid The id for teh course module for this quiz instance.
 * @param int $quizid The id of this quiz instance.
 * @param int $canaccess Whether the user can (1) or cannot (0) access all groups.
 * @param int $groupid The id of the group to which it is to be sent.
 * @param int $showanswer The value to show the correct answer (1) or not (0).
 */
function quiz_display_instructor_interface($cmid, $quizid, $canaccess, $groupid, $showanswer) {
    global $DB;
    global $CFG;

    $clearquestion = optional_param('clearQuestion', null, PARAM_TEXT);
    $sendquestionid = optional_param('question', 0, PARAM_INT);
    $evaluate = optional_param('evaluate', 0, PARAM_INT);
    $showkey = optional_param('showkey', 0, PARAM_INT);
    $rag = optional_param('rag', 0, PARAM_INT);
    if (isset($clearquestion)) {
        quiz_clear_question($quizid, $groupid);
    }
    $quiz = $DB->get_record('quiz', array('id' => $quizid));
    if ($sendquestionid) {
        quiz_send_question($quizid, $sendquestionid, $groupid);
    }

    echo "<table><tr><td>".quiz_instructor_buttons($quizid, $groupid)."</td>";
    echo "<td>&nbsp; &nbsp;<a href='".$CFG->wwwroot."/mod/quiz/edit.php?cmid=$cmid'>";
    echo get_string('changequestions', 'quiz_liveviewpoll')."</a></td>";
    // Add in a link to the liveview grid if that module exists in this Moodle site.
    $pathtogridreport = $CFG->dirroot.'/mod/quiz/report/liveviewgrid/report.php';
    if (file_exists($pathtogridreport)) {
        echo "<td>&nbsp; &nbsp;";
        echo "<a href='".$CFG->wwwroot."/mod/quiz/report.php?id=$cmid&mode=liveviewgrid&group=$groupid' target = '_blank'>";
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
    // Add in the legend/key if desired.
    if ($showkey) {
        echo get_string('fractioncolors', 'quiz_liveviewpoll')."\n<br />";
        echo "<table border=\"1\" width=\"100%\">\n";
        $head = "<tr>";
        for ($i = 0; $i < 11; $i++) {
            $myfraction = number_format($i / 10, 1, '.', ',');
            $head .= "<td ";
            if ($rag == 1) {// Colors from image from Moodle.
                if ($myfraction == 0) {
                    $redpart = 244;
                    $greenpart = 67;
                    $bluepart = 54;
                } else if ($myfraction == 1) {
                    $redpart = 139;
                    $greenpart = 195;
                    $bluepart = 74;
                } else {
                    $redpart = 255;
                    $greenpart = 152;
                    $bluepart = 0;
                }
            } else {
                // Make .5 match up to Moodle amber even when making them different with gradation.
                $greenpart = intval(67 + 212 * $myfraction - 84 * $myfraction * $myfraction);
                $redpart = intval(244 + 149 * $myfraction - 254 * $myfraction * $myfraction);
                if ($redpart > 255) {
                    $redpart = 255;
                }
                $bluepart = intval(54 - 236 * $myfraction + 256 * $myfraction * $myfraction);
            }
            $head .= "style='background-color: rgb($redpart,$greenpart,$bluepart)'";
            $head .= ">$myfraction</td>";
        }
        echo $head."\n</tr></table>";
    }
    echo "\n<table><tr><td>";

    echo get_string('responses', 'quiz_liveviewpoll');
    if ($groupid) {
        $grpname = $DB->get_record('groups', array('id' => $groupid));
        echo get_string('from', 'quiz_liveviewpoll').$grpname->name;
    } else if ($canaccess) {
        echo ' -- ('.get_string('allgroups', 'quiz_liveviewpoll').')';
    }
    echo "<br>";

    if (quiz_show_current_question($quizid, $showanswer, $groupid) == 1) {
        echo "<br>";
        echo "<br>";
        $getvalues = "quizid=$quizid&id=$cmid&groupid=$groupid&evaluate=$evaluate&rag=$rag&courseid=".$quiz->course;
        $iframeurl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/poll_tooltip_graph.php?$getvalues";
        $popupgraphurl = $CFG->wwwroot."/mod/quiz/report/liveviewpoll/popupgraph.php?$getvalues";
        echo "<iframe id= \"graphIframe\" src=\"".$iframeurl."\" height=\"540\" width=\"723\"></iframe>";
        echo "<br><br><a onclick=\"newwindow=window.open('$popupgraphurl', '',
                'width=750,height=560,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,";
        echo "directories=no,scrollbars=yes,resizable=yes');
                return false;\"
                href=\"$popupgraphurl\" target=\"_blank\">Open a new window for the graph.</a>";
    }
}

/**
 * This function sets the question in the database so the client functions can find what question is active.  And it does it fast.
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $sendquestionid The id of the question that is to be sent.
 * @param int $groupid The id of the group to which it is to be sent. 0 = no groups.
 */
function quiz_send_question($quizid, $sendquestionid, $groupid) {
    global $DB;
    global $CFG;

    $myquestionid = $sendquestionid;// The id of the question being sent.
    $cmid = optional_param('id', 0, PARAM_INT);// The cmid of the quiz.
    if ($myquestionid > 0 && $cmid > 0) {
        quiz_update_attempt_layout($quizid, $myquestionid, $groupid);
        $quiz = $DB->get_record('quiz', array('id' => $quizid));
        $record = new stdClass();
        $record->id = '';
        $record->course = $quiz->course;
        $record->cmid = $cmid;
        $record->quiz_id = $quiz->id;
        if ($groupid > 0) {
            $record->groupid = $groupid;
            $record->groupmembers = ','.implode(',', get_userids_for_group($groupid)).',';
        } else {
            $record->groupid = 0;
            $record->groupmembers = '';
        }
        $record->question_id = $myquestionid;
        $record->timemodified = time();
        if ($DB->record_exists('quiz_current_questions', array('quiz_id' => $quiz->id, 'groupid' => $groupid))) {
            $mybool = $DB->delete_records('quiz_current_questions', array('quiz_id' => $quiz->id, 'groupid' => $groupid));
        }
        $lastinsertid = $DB->insert_record('quiz_current_questions', $record);
    }
}

/**
 * This function clears the current question.
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $groupid The id of the group if one has been chosen, otherwise 0.
 */
function quiz_clear_question($quizid, $groupid) {
    global $DB;
    // We don't have to change the layout becuase the javascript will send the students to the no question page.
    $timemodified = time();
    $DB->set_field('quiz_current_questions', 'question_id', '-1', array('quiz_id' => $quizid, 'groupid' => $groupid));
    $DB->set_field('quiz_current_questions', 'timemodified', $timemodified, array('quiz_id' => $quizid, 'groupid' => $groupid));
}

/**
 * Sets the layout for the students so that they see the correct question.
 *
 * @param int $quizid The id for this quiz.
 * @param int $questionid The id of the question that the student should see.
 * @param int $groupid The id of the group if one has been chosen, otherwise 0.
 */
function quiz_update_attempt_layout($quizid, $questionid, $groupid) {
    global $DB;
    $slotid = pollingslot($quizid, $questionid);
    $mylayout = $slotid.',0';
    for ($i = 1; $i < 10; $i++) {
        $mylayout = $mylayout.",$slotid,0";
    }
    if ($DB->record_exists('quiz_attempts', array('quiz' => $quizid, 'state' => 'inprogress'))) {
        if ($groupid > 0) {
            $userids = get_userids_for_group($groupid);
            foreach ($userids as $usrid) {
                $DB->set_field('quiz_attempts', 'layout', $mylayout,
                    array('quiz' => $quizid, 'state' => 'inprogress', 'userid' => $usrid));
            }
        } else {
            $DB->set_field('quiz_attempts', 'layout', $mylayout, array('quiz' => $quizid, 'state' => 'inprogress'));
        }
    }
}

/**
 * A function to turn an array of the userids for all memers in a group.
 *
 * @param int $groupid The id for the group.
 * @return array An array of the userids of the members in this group.
 */
function get_userids_for_group($groupid) {
    global $DB;
    $userids = array();
    $members = $DB->get_records('groups_members', array('groupid' => $groupid));
    foreach ($members as $member) {
        $userids[] = $member->userid;
    }
    return $userids;
}

/**
 * This function returns the correct quiz slot based on the quiz and the current question.
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $currentquestionid The id of the question that was sent.
 * @return int The quiz_slot for the question that was sent.
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

/**
 * Make the button controls on the instructor interface.
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $groupid The id of the group to which it is to be sent.
 */
function quiz_instructor_buttons($quizid, $groupid) {
    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the quiz instance.
    $querystring = 'mode=liveviewpoll&groupid='.$groupid;
    if ($mycmid) {
        $querystring .= '&id='.$mycmid;
    }

    $disabled = "";
    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    if (!quiz_check_active_question($quizid, $groupid)) {
        $disabled = "disabled=\"disabled\"";
    }
    $myform .= "<input type=\"submit\" value=\"".get_string('stoppolling', 'quiz_liveviewpoll');
    $myform .= "\" name=\"clearQuestion\" ".$disabled."/>\n</form>\n";

    return($myform);
}

/**
 * Function to make the option form.
 *
 * @param int $id The id for the course module for this quiz instance.
 * @param int $quizid The id of this quiz instance.
 * @param int $groupid The id of the group to which it is to be sent.
 * @param string $mode The string, liveviewpoll, telling reports that this is the Live View Poll module.
 * @param array $hidden The array of value to show, 1, or hide, 0, for various options.
 * @param array $strings The array of strings to use to describe each hidden value.
 * @param int $refresht The value (in 10 seconds) for refreshing the display.
 */
function option_form($id, $quizid, $groupid, $mode, $hidden, $strings, $refresht) {
    global $DB, $CFG;
    // Script to hide or display the option form.
    echo "\n<script>";
    echo "\nfunction optionfunction() {";
    echo "\n  var e=document.getElementById(\"option1\");";
    echo "\n  var b=document.getElementById(\"button1\");";
    echo "\n  if(e.style.display == \"none\") { ";
    echo "\n      e.style.display = \"block\";";
    echo "\n        b.innerHTML = \"".get_string('clicktohide', 'quiz_liveviewpoll')."\";";
    echo "\n  } else {";
    echo "\n      e.style.display=\"none\";";
    echo "\n      b.innerHTML = \"".get_string('clicktodisplay', 'quiz_liveviewpoll')."\";";
    echo "\n  }";
    echo "\n}";
    echo "\n</script>  ";

    if ($DB->record_exists('quiz_current_questions', array('quiz_id' => $quizid, 'groupid' => $groupid))) {
        $currentquestion = $DB->get_record('quiz_current_questions', array('quiz_id' => $quizid, 'groupid' => $groupid));
        if ($currentquestion->question_id > 0) {
            $singleqid = $currentquestion->question_id;
        } else {
            $singleqid = 0;
        }
    } else {
        $singleqid = 0;
    }

    echo "\n<button id='button1' type='button'  onclick=\"optionfunction()\">";
    echo get_string('clicktodisplay', 'quiz_liveviewpoll')."</button>";
    echo "\n<div class='myoptions' id='option1' style=\"display:none;\">";
    echo "<form action=\"".$CFG->wwwroot."/mod/quiz/report.php\">";
    echo "<input type='hidden' name='changeoption' value=1>";
    echo "<input type='hidden' name='id' value=$id>";
    echo "<input type='hidden' name='mode' value=$mode>";
    echo "<input type='hidden' name='singleqid' value=$singleqid>";
    echo "<input type='hidden' name='groupid' value=$groupid>";
    $checked = array();
    $notchecked = array();
    foreach ($hidden as $hiddenkey => $hiddenvalue) {
        if ($hiddenvalue) {
            $checked[$hiddenkey] = 'checked';
            $notchecked[$hiddenkey] = '';
        } else {
            $checked[$hiddenkey] = '';
            $notchecked[$hiddenkey] = 'checked';
        }
    }
    $twait = array(1, 2, 3, 6, 200);
    foreach ($twait as $myt) {
        $tindex = 'refresht'.$myt;
        if ($refresht == $myt) {
            $checked[$tindex] = 'checked';
        } else {
            $checked[$tindex] = '';
        }
    }
    $td = "<td style=\"padding:5px 8px;border:1px solid #CCC;\">";
    echo "\n<table>";

    foreach ($hidden as $hiddenkey => $hiddenvalue) {
        echo "\n<tr>".$td.$strings[$hiddenkey]."</td>";
        echo $td."<input type='radio' name='$hiddenkey' value=1 ".$checked[$hiddenkey]."> ";
        echo get_string('yes', 'quiz_liveviewpoll')."</td>";
        echo $td."<input type='radio' name='$hiddenkey' value=0 ".$notchecked[$hiddenkey]."> ";
        echo get_string('no', 'quiz_liveviewpoll')."</td></tr>";
    }
    echo "\n</table>";
    $buttontext = get_string('submitoptionchanges', 'quiz_liveviewpoll');
    echo "<br /><input type=\"submit\" value=\"$buttontext\"></form>";
    echo "</div>";
    return $hidden;
}
/**
 * The function finds out is there a question active?
 *
 * @param int $quizid The id of this quiz instance.
 * @param int $groupid The id of he group selected by the teacher.
 */
function quiz_check_active_question($quizid, $groupid) {
    global $DB;

    if ($currentquestion = $DB->get_record('quiz_current_questions', array('quiz_id' => $quizid, 'groupid' => $groupid))) {
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
 * A function to create a dropdown menu for the groups.
 *
 * @param int $courseid The id for the course.
 * @param string $geturl The url for the form when submit is clicked.
 * @param int $canaccess Whether the user can (1) or cannot (0) access all groups.
 * @param array $hidden The array of keys and values for the hidden inputs in the form.
 */
function liveviewpoll_group_dropdownmenu($courseid, $geturl, $canaccess, $hidden) {
    global $DB, $USER;
    echo "\n<table border=0><tr><td valign=\"top\">";
    echo get_string('whichgroups', 'quiz_liveviewpoll')."</td>";
    $groups = $DB->get_records('groups', array('courseid' => $courseid));
    echo "\n<td><form action=\"$geturl\">";
    $mygroup = -1;
    foreach ($hidden as $key => $value) {
        if ($key <> 'groupid') {
            echo "\n<input type=\"hidden\" name=\"$key\" value=\"$value\">";
        } else {
            $mygroup = $value;
        }
    }
    echo "\n<select name=\"groupid\" onchange='this.form.submit()'>";
    echo "\n<option value=\"0\">".get_string('choosegroup', 'quiz_liveviewpoll')."</option>";
    if ($canaccess && ($mygroup > 0)) {
        echo "\n<option value=\"0\">".get_string('allgroups', 'quiz_liveviewpoll')."</option>";
    }
    foreach ($groups as $grp) {
        if ($DB->get_record('groups_members', array('groupid' => $grp->id, 'userid' => $USER->id)) || $canaccess) {
            $groupid = $grp->id;
            // This teacher can see this group.
            if ($groupid <> $mygroup) {
                $okgroup[$groupid] = $grp->name;
            }
        }
    }
    asort($okgroup);
    foreach ($okgroup as $grpid => $grpname) {
        echo "\n<option value=\"$grpid\">$grpname</option>";
    }
    echo "\n</select>";
    echo "\n</form></td></tr></table>";
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
    $script = '<polling><\/polling>\s+<script src="report\/liveviewpoll\/javascript_refresh.js">\s+<\/script>';
    foreach (quiz_get_questions($quizid) as $items) {
        // Remove the refresh javascript from the questions.
        $items['question'] = preg_replace("/$script/m", '', $items['question']);
        $previewurl = $CFG->wwwroot.'/question/preview.php?id='.
            $items['id'].'&cmid='.$cmid.
            '&behaviour=deferredfeedback&correctness=0&marks=1&markdp=-2&feedback&generalfeedback&rightanswer&history';
        $myform .= "\n<tr><td style=\"valign:top\">".get_string('sendquestion', 'quiz_liveviewpoll');
        $myform .= "</td><td><input type=\"submit\" name=\"question\" value=\"".$items['id']."\" />";
        $myform .= "</td><td><a href=\"$previewurl\" onclick=\"return quizpopup('".$items['id']."')\" target=\"_blank\">";
        $myform .= quiz_create_preview_icon()."</a></td>";
        $graphurl = $CFG->wwwroot.'/mod/quiz/report/liveviewpoll/quizgraphics.php?';
        $graphurl .= 'question_id='.$items['id']."&quizid=".$quizid."&norefresh=1";
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
 * @param int $showanswer The value to show the correct answer (1) or not (0).
 * @param int $groupid The id of the group. If no groups, $groupid = 0.
 */
function quiz_show_current_question($quizid, $showanswer, $groupid) {
    global $DB;

    if ($DB->record_exists('quiz_current_questions', array('quiz_id' => $quizid, 'groupid' => $groupid))) {
        $question = $DB->get_record('quiz_current_questions', array('quiz_id' => $quizid, 'groupid' => $groupid));
        if ($question->question_id == -1) {
            // This plugin uses -1 for the questionid to indicate that polling has stopped or has not started.
            echo get_string('nocurrentquestion', 'quiz_liveviewpoll');
            return(0);
        }
        $questiontext = $DB->get_record('question', array('id' => $question->question_id));
        $script = '<polling><\/polling>\s+<script src="report\/liveviewpoll\/javascript_refresh.js">\s+<\/script>';
        $qtext = preg_replace("/$script/m", '', $questiontext->questiontext);
        echo get_string('currentquestionis', 'quiz_liveviewpoll')." -> ".$qtext;
        if ($showanswer) {
            if ($questiontext->qtype == 'essay') {
                $rightanswer = get_string('rightansweressay', 'quiz_liveviewpoll');
            } else {
                $attempts = $DB->get_records('question_attempts', array('questionid' => $question->question_id));
                foreach ($attempts as $attempt) {
                    $rightanswer = $attempt->rightanswer;
                }
            }
            echo get_string('rightanswer', 'quiz_liveviewpoll').$rightanswer;
        }
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
