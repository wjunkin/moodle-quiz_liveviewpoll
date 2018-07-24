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
 * Quiz liveviewpoll report class.
 *
 * @package   quiz_liveviewpoll
 * @copyright 2014 Open University
 * @author    James Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once('locallib_liveviewpoll.php');
/**
 * The class quiz_liveviewpoll_report supports dynamic in-class polling using the questions from the quiz.
 *
 * It gives the most recent answers from all students for the question that was sent.
 * There is an option to show what the grades would be if the quiz were graded at that moment.
 *
 * @copyright 2018 William Junkin
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_liveviewpoll_report extends quiz_default_report {

    /** @var context_module context of this quiz.*/
    protected $context;

    /** @var quiz_liveviewpoll_table instance of table class used for main questions stats table. */
    protected $table;

    /** @var int either 1 or 0 in the URL get determined by the teachere to show or hide grades of answers. */
    protected $evaluate = 0;
    /** @var int either 1 or 0 in the URL get determined by the teachere to show or hide grading key. */
    protected $showkey = 0;
    /** @var int The time of the last student response to a question. */
    protected $qmaxtime = 0;
    /** @var int The course module id for the quiz. */
    protected $id = 0;
    /** @var String The string that tells the code in quiz/report which sub-module to use. */
    protected $mode = '';
    /** @var int The context id for the quiz. */
    protected $quizcontextid = 0;
    /** @var Array The  array of the students who are attempting the quiz. */
    protected $users = array();
    /** @var String The answer submitted to a question. */
    protected $answer = '';
    /** @var String The URL where the program can find out if a new response has been submitted and thus update the spreadsheet. */
    protected $graphicshashurl = '';

    /**
     * Return the greatest time that a student responded to a given quiz.
     *
     * This is used to determine if the teacher view of the graph should be refreshed.
     * @param int $quizcontextid The ID for the context for this quiz.
     * @return int The integer for the greatest time.
     */
    private function liveviewquizmaxtime($quizcontextid) {
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
     * Function to get the questionids as the keys to the $slots array so we know all the questions in the quiz.
     * @param int $quizid The id for this quiz.
     * @return array $slots The slot values (from the quiz_slots table) indexed by questionids.
     */
    private function liveviewslots($quizid) {
        global $DB;
        $slots = array();
        $myslots = $DB->get_records('quiz_slots', array('quizid' => $quizid));
        foreach ($myslots as $key => $value) {
            $slots[$value->questionid] = $value->slot;
        }
        return $slots;
    }
    /**
     * Function to get the qtype, name, questiontext for each question.
     * @param array $slots and array of slot ids indexed by question ids.
     * @return array $question. A doubly indexed array giving qtype, qname, and qtext for the questions.
     */
    private function liveviewquestion($slots) {
        global $DB;
        $question = array();
        foreach ($slots as $questionid => $slotvalue) {
            if ($myquestion = $DB->get_record('question', array('id' => $questionid))) {
                $question['qtype'][$questionid] = $myquestion->qtype;
                $question['name'][$questionid] = $myquestion->name;
                $question['questiontext'][$questionid] = $myquestion->questiontext;
            }
        }
        return $question;
    }

    /**
     * Return the number of users who have submitted answers to this quiz instance.
     *
     * @param int $quizid The ID for the quiz instance
     * @return array The userids for all the students submitting answers.
     */
    private function liveview_who_sofar_gridview($quizid) {
        global $DB;

        $records = $DB->get_records('quiz_attempts', array('quiz' => $quizid));

        foreach ($records as $records) {
            $userid[] = $records->userid;
        }
        if (isset($userid)) {
            return(array_unique($userid));
        } else {
            return(null);
        }
    }
    /**
     * Display the report.
     * @param Obj $quiz The object from the quiz table.
     * @param Obj $cm The object from the course_module table.
     * @param Obj $course The object from the course table.
     * @return bool True if successful.
     */
    public function display($quiz, $cm, $course) {
        global $OUTPUT, $DB, $CFG;
        $id = optional_param('id', 0, PARAM_INT);
        $mode = optional_param('mode', '', PARAM_ALPHA);
        $slots = array();
        $question = array();
        $users = array();
        $quizid = $quiz->id;
        $answer = '';
        $graphicshashurl = '';
        $this->print_header_and_tabs($cm, $course, $quiz, 'liveviewpoll');
        $context = $DB->get_record('context', array('instanceid' => $cm->id, 'contextlevel' => 70));
        $quizcontextid = $context->id;
        // Make sure there is only one question per page. To do: make this better html code.
        $quizslots = $DB->get_records('quiz_slots', array('quizid' => $quiz->id));
        $slotpages = array();
        foreach ($quizslots as $quizslot) {
            $slotpages[$quizslot->page] = 1;
        }
        if (count($slotpages) != count($quizslots)) {
            echo "In-class polling requires one page per question.";
            echo "\n<br />You have ".count($quizslots)." questions and only ".count($slotpages)." pages.";
            echo "<br />You must use the back button on your broswer and correct this before using this quiz for in-class polling.";
            return;
        }
        if ($activequestion = $DB->get_record('quiz_active_questions', array('quiz_id' => $quiz->id))) {
            // This quiz is already set up for polling.
            // Make sure this is being used for in-class polling. Pages should not be shuffled.
            $quizsections = $DB->get_record('quiz_sections', array('quizid' => $quiz->id));
            if ($quizsections->shufflequestions <> 0) {
                $record = new stdClass();
                $record->id = $quizsections->id;
                $record->shufflequestions = 0;
                $DB->update_record('quiz_sections', $record);
            }
            quiz_display_instructor_interface($cm->id, $quiz->id);
        } else {
            $startpoll = optional_param('startpoll', 0, PARAM_INT);
            if ($startpoll) {
                $nocurrentquestionid = quiz_nocurrentq_create($course->id);
                // Put this question in slot 1 and make sure the questions are not shuffled.
                $message = quiz_add_firstquestion_to_quiz($quiz->id, $nocurrentquestionid);
                if (strlen($message) !== 0) {
                    echo $message;
                    exit;
                } else {
                    // Everything should be ready to go now.
                    quiz_display_instructor_interface($cm->id, $quiz->id);
                }

            } else {
                echo get_string('quiznotsetforpoll', 'quiz_liveviewpoll');
                echo "\n<br /><a href='";
                echo $CFG->wwwroot."/mod/quiz/report.php?id=".$cm->id."&mode=liveviewpoll&startpoll=1'>";
                echo get_string('preparequizforpoll', 'quiz_liveviewpoll');
                echo "\n</a>";
                echo get_string('preparequizexplanation', 'quiz_liveviewpoll');
            }
        }
        return true;
    }
}
