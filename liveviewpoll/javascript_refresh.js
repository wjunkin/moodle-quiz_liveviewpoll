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
 * JavaScript to refresh page when a question is sent in quiz_report_liveviewpoll module.
 *
 * @package    quiz_liveviewpoll
 * @copyright  2020 onwards William F Junkin  <junkinwf@eckerd.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var loc = String(window.location);
var match = loc.match(/mod\/quiz\/attempt.php/);
//alert('match is ' + match);
if (match.len = 20) {
//    alert('hello again');
    var http = false;
    var currentget = window.location.search;
    var newget = '';
    var obj = /page=\d+/.exec(currentget);
    var x = "";
    var mypage = '';
    var newpage = '';
    var str = obj + '';
    var n = str.length;
    if (str.length > 5) {
        mypage = obj;
    }
    // Call at the begining to save the start time.
    var start_time = new Date();
    var nocurrentquestionurl = "report/liveviewpoll/nocurrentquestion.php" + window.location.search;
    var currentpageurl = "report/liveviewpoll/currentpage.php" + window.location.search;
    var quizpage = "http://localhost/moodle381/mod/quiz/attempt.php";
    var thispage = window.location.href;
    if(navigator.appName == "Microsoft Internet Explorer") {
        http = new ActiveXObject("Microsoft.XMLHTTP")
    } else {
        http = new XMLHttpRequest();
    }

    function replace() {
        // Compute seconds (does not matter when/how often you call it).
        var milliseconds_since_start = new Date().valueOf() - start_time;
        if(milliseconds_since_start < 3600000) {
            var t = setTimeout("replace()",3000);
        } else {
            window.location.replace(nocurrentquestionurl);
        }

        http.open("GET", currentpageurl, true);
        http.onreadystatechange = function() {
            if(http.readyState == 4) {
                x = http.responseText;
                var pagex = x - 1;
                newpage = 'page=' + pagex;
                if(x == 0 ){
                } else if (x == -1) {
                    window.location.replace(nocurrentquestionurl);
                } else if(newpage != mypage && x > 0){
                    if (mypage == '') {
                        var newquestionurl = thispage + '&' + newpage;
                    } else {
                        var newquestionurl = thispage.replace(mypage, newpage);
                    }
                    window.location.replace(newquestionurl);
                }
            }
        }
        http.send(null);
    }

    replace();
}