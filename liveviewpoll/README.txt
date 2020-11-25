This Live Polling module is a quiz plugin to allow teachers to use a quiz for in-class polling.
The teacher interface allows teachers to start and stop polling. A teacher starts polling by sending a question.
Any student submitting an answer or refreshing a page from a quiz receives the question that was sent.
Students cannot go to another question until a different question is sent.
As students submit or change answers to the question, the teacher's display of their responses changes automatically.
The only responses that are displayed are the responses that are sent after the teacher sends the question.
For multichoice, truefalse, and calculated multichoice questions, the student responses are displayed in a histogram.
For essay questions and all other question types, the student answers are displayed. Each student gets one line in the display.
For these type of questions the teacher can choose to show or hide the names of the students submitting responses.
IF the Moodle module "Live Report" is loaded in the Moodle site, then
   The teacher also has the option to displaying a dynamic table/spreadsheet of student responses to all of the questions.
   The grades associated with these responses can be displayed or hidden.
   The display of responses to each of the questions can also be displayed.

To install this "Live Polling" module, place the liveviewpoll directory as a sub-directory in the /mod/quiz/report/ directory,
 creating the /mod/quiz/report/liveviewpoll/ directory.
This module can also be installed by using a zipped file of the liveviewpoll directory and the Moodle utility to install plugins.
After installation, teachers use it by clicking on the "Live Poll" option in the "Report" drop-down menu.
This plugin handles groups correctly. 
If a student starts the first attempt of the quiz after a question has been sent,
 the student will not see the correct question until the next question is sent. 
Changes for v2.5. No refresh goes more than 1 hour.
Changes for v2.5.2. Added utility so that teacher can removed polling feature from quizzes and the questions in quizzes.
Changes for v2.5.3. Added communication links from polling report page to restore_quiz.php and back to course.
Changes for v2.5.4. Added in the utility to allow teachers to force 'submit and finish' for all students in the polling session.
Changes for v2.5.7. Added in the options to evaluate, showkey, and use RAG colors. 
    The option to show student names will be added soon.
Changes for v2.5.8. Removed tooltips from histogram labels and ability to show names on top of the histogram bars. 
    Added in a table showing question stems above the histogram and a table showing student answers below the histogram.
    Made the iframe window adjust to the size of the histogram plus the names table when names are shown.
Changes for v2.6.1 (2020110901): Changed the algorythm so that the student is sent to the desired page instead of 
    changing the layout for the students. This change will keep other reports and grades intact.
Changes for v2.6.2 (2020112500): Cleaned up javascript_refresh.js and put in code so the javascript will not do anything
    unless the student is in the quiz (the url contains /mod/quiz/attempt.php/)
Changes for v2.6.3 (2020112502): Removed a bug. $row in poll_tooltip_graph.php wasn't defined in line 317 when names were hidden.
    I moved the definition of $row = array(); up to line 292.
