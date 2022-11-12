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
 * The gradebook quizanalytics report
 *
 * @package   gradereport_quizanalytics
 * @author Moumita Adak <moumita.a@dualcube.com>
 * @copyright Dualcube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../config.php');
require_once($CFG->dirroot.'/grade/lib.php');
require_once ($CFG->dirroot.'/grade/report/quizanalytics/lib.php');

$courseid = required_param('id', PARAM_INT);
$userid   = optional_param('userid', $USER->id, PARAM_INT);

$PAGE->set_url(new moodle_url($CFG->wwwroot.'/grade/report/quizanalytics/index.php', array('id' => $courseid)));
$PAGE->requires->css('/grade/report/quizanalytics/css/styles.css', true);
$PAGE->requires->css('/grade/report/quizanalytics/css/bootstrap.min.css', true);
$PAGE->requires->js('/grade/report/quizanalytics/js/Chart.js', true);
$PAGE->requires->js_call_amd('gradereport_quizanalytics/analytic','analytic',array($userid,$courseid));


$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 5, PARAM_INT);  // How many per page.
$baseurl = new moodle_url($CFG->wwwroot.'/grade/report/quizanalytics/index.php', array('id' => $courseid, 'perpage' => $perpage));

// Basic access checks.
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('nocourseid');
}
require_login($course);
$PAGE->set_pagelayout('report');

$context = context_course::instance($course->id);
require_capability('gradereport/quizanalytics:view', $context);

if (empty($userid)) {
    require_capability('moodle/grade:viewall', $context);

} else {
    if (!$DB->get_record('user', array('id' => $userid, 'deleted' => 0)) or isguestuser($userid)) {
        print_error('invaliduser');
    }
}

$access = false;
if (has_capability('moodle/grade:viewall', $context)) {
    // Ok - can view all course grades.
    $access = true;

} else if ($userid == $USER->id and has_capability('moodle/grade:view', $context) and $course->showgrades) {
    // Ok - can view own grades.
    $access = true;

} else if (has_capability('moodle/grade:viewall', context_user::instance($userid)) and $course->showgrades) {
    // Ok - can view grades of this quizanalytics- parent most probably.
    $access = true;
}

if (!$access) {
    // No access to grades!
    print_error('nopermissiontoviewgrades', 'error',  $CFG->wwwroot.'/course/view.php?id='.$courseid);
}

$gpr = new grade_plugin_return(array('type' => 'report', 'plugin' => 'overview', 'courseid' => $course->id, 'userid' => $userid));

if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = array();
}
$USER->grade_last_report[$course->id] = 'overview';

// First make sure we have proper final grades - this must be done before constructing of the grade tree.
grade_regrade_final_grades($courseid);

// Print the page.
print_grade_page_head($courseid, 'report', 'quizanalytics',
    get_string('pluginname', 'gradereport_quizanalytics'). ' - '.$USER->firstname
    .' '.$USER->lastname);

$qanalyticsformatoptions = new stdClass();
$qanalyticsformatoptions->noclean = true;
$qanalyticsformatoptions->overflowdiv = false;

$getquiz = array();
$getquizrec = array();
$quizcount = 0;
$getquizrecords = $DB->get_records('quiz', array('course' => $courseid));
if (isset($getquizrecords)) {
    $quizcount = count($getquizrecords);
    $getquizrec = array_chunk($getquizrecords, $perpage);
}
if (!empty($getquizrec)) {
    $getquiz = $getquizrec[$page];
}
$table = new html_table();
if (!$getquiz) {
    echo $OUTPUT->heading(get_string('noquizfound', 'gradereport_quizanalytics'));
    $table = null;
} else {
    $table = get_table($USER,$getquiz,$table,$qanalyticsformatoptions,$courseid);
}

if (!empty($table)) {
    echo html_writer::start_tag('div', array('class' => 'no-overflow display-table'));
    echo html_writer::table($table);
    echo html_writer::end_tag('div');
    echo $OUTPUT->paging_bar($quizcount, $page, $perpage, $baseurl);
}

$html = get_showanalytics_html();

echo $html;

echo $OUTPUT->footer();