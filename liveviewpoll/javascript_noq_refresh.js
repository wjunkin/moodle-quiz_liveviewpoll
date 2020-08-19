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
 * JavaScript to return student to quiz when a question is sent in quiz_report_liveviewpoll module.
 *
 * @package    quiz_liveviewpoll
 * @copyright  2020 onwards William F Junkin  <junkinwf@eckerd.edu>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
var http = false;
var x = "";
var myCount = 0;
var currentquestionurl = "currentquestion.php" + window.location.search;
if(navigator.appName == "Microsoft Internet Explorer") {
    http = new ActiveXObject("Microsoft.XMLHTTP")
} else {
    http = new XMLHttpRequest();
}

function replace() {
    var t = setTimeout("replace()", 3000);
    http.open("GET", currentquestionurl, true);
    http.onreadystatechange = function() {
        if(http.readyState == 4) {
            if(x > 0 ){
                window.location = document.referrer;
            }
            x = http.responseText;
        }
    }
    http.send(null);
    myCount++;
}

replace();