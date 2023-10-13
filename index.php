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
 * @author DualCube <admin@dualcube.com>
 * @copyright Dualcube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->dirroot . '/grade/lib.php');
$courseid = required_param('id', PARAM_INT);
$userid   = optional_param('userid', $USER->id, PARAM_INT);
$PAGE->set_url(new moodle_url($CFG->wwwroot . '/grade/report/quizanalytics/index.php', array('id' => $courseid)));
$PAGE->requires->css('/grade/report/quizanalytics/css/frontend.css', true);
$PAGE->requires->css('/grade/report/quizanalytics/css/datatables.css', true);
$PAGE->requires->js('/grade/report/quizanalytics/js/Chart.js', true);
$PAGE->requires->js_call_amd('gradereport_quizanalytics/analytic', 'analytic');
$PAGE->requires->js_call_amd('gradereport_quizanalytics/analytic', 'init');
// Basic access checks.
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    throw new moodle_exception('nocourseid');
}
require_login($course);
$PAGE->set_pagelayout('report');
$context = context_course::instance($course->id);
require_capability('gradereport/quizanalytics:view', $context);
if (empty($userid)) {
    require_capability('moodle/grade:viewall', $context);
} else {
    if (!$DB->get_record('user', array('id' => $userid, 'deleted' => 0)) || isguestuser($userid)) {
        throw new moodle_exception('invaliduser');
    }
}
$access = false;
if (has_capability('moodle/grade:viewall', $context)) {
    // Ok - can view all course grades.
    $access = true;
} else if ($userid == $USER->id && has_capability('moodle/grade:view', $context) && $course->showgrades) {
    // Ok - can view own grades.
    $access = true;
} else if (has_capability('moodle/grade:viewall', context_user::instance($userid)) && $course->showgrades) {
    // Ok - can view grades of this quizanalytics- parent most probably.
    $access = true;
}
if (!$access) {
    // No access to grades!
        throw new moodle_exception('nopermissiontoviewgrades', 'error',  $CFG->wwwroot . '/course/view.php?id=' . $courseid);
}
$is_student = true;
$role = get_user_roles($context = context_course::instance($courseid), $userid, false);
if(reset($role)->shortname != 'student'){
  $is_student =false;
}
$gpr = new grade_plugin_return(array('type' => 'report', 'plugin' => 'overview', 'courseid' => $course->id, 'userid' => $userid));
if (!isset($USER->grade_last_report)) {
    $USER->grade_last_report = array();
}
$USER->grade_last_report[$course->id] = 'overview';
// First make sure we have proper final grades - this must be done before constructing of the grade tree.
grade_regrade_final_grades($courseid);
// Print the page.
print_grade_page_head(
  $courseid,
  'report',
  'quizanalytics',
  get_string('pluginname', 'gradereport_quizanalytics') . ' - ' . $USER->firstname
    . ' ' . $USER->lastname
);
$qanalyticsformatoptions = new stdClass();
$qanalyticsformatoptions->noclean = true;
$qanalyticsformatoptions->overflowdiv = false;
$getquiz = array();
$getquizrec = array();
$quizcount = 0;
$getquizrecords = $DB->get_records('quiz', array('course' => $courseid));
if (isset($getquizrecords)) {
    $quizcount = count($getquizrecords);
    $getquiz = $getquizrecords;
}
$table = new html_table();
if (!$getquiz) {
    echo $OUTPUT->heading(get_string('noquizfound', 'gradereport_quizanalytics'));
    $table = null;
} else {
    $table->head = array();
    $table->head[] = get_string('quizname', 'gradereport_quizanalytics');
    $table->head[] = get_string('noofattempts', 'gradereport_quizanalytics');
    if(!$is_student)
    $table->head[] = get_string('student_select', 'gradereport_quizanalytics');
    $table->head[] = get_string('action', 'gradereport_quizanalytics');
    foreach ($getquiz as $getquizkey => $getquizval) {
      if(!$is_student) {
        $getquizattemptsnotgraded = $DB->get_records_sql("SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NULL AND quiz = ?", array($getquizval->id));
      } else {
        $getquizattemptsnotgraded = $DB->get_records_sql("SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NULL AND quiz = ? AND userid = ?", array($getquizval->id, $USER->id));
      }
          if(!$is_student) {
            $getquizattempts = $DB->get_records('quiz_attempts', array(
              'quiz' => $getquizval->id,
              'state' => 'finished'
            ));
          } else {
            $getquizattempts = $DB->get_records('quiz_attempts', array(
              'quiz' => $getquizval->id,
              'userid' => $USER->id,
              'state' => 'finished'
            ));
          }
        $getmoduleid = $DB->get_record_sql("SELECT cm.id FROM {course_modules} cm, {modules} m, {quiz} q WHERE m.name = 'quiz' AND cm.module = m.id AND cm.course = q.course AND cm.instance = q.id AND q.id = ?", array($getquizval->id));
        if (isset($getmoduleid)) {
            $quizviewurl = $CFG->wwwroot . "/mod/quiz/view.php?id=" . $getmoduleid->id;
        } else {
            $quizviewurl = "#";
        }
        $row = array();
        $row[] = "<a href='" . $quizviewurl . "'>" . format_text($getquizval->name, "", $qanalyticsformatoptions) . "</a>";
        $row[] = count($getquizattempts);
        if(!$is_student){
          $attepomted_users = $DB->get_records_sql("SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NOT NULL AND attempt = 1 AND quiz = ?", array($getquizval->id));
          $select = "<select id='userSelect'><option value='-1'>" . get_string('user_select', 'gradereport_quizanalytics') . "</option>";
          foreach($attepomted_users as $user){
            $select .= "<option value='" . $user->userid . "'>" . get_complete_user_data('id', $user->userid)->username . "</option>";
          }
          $select .= "</select>";
          $row[] = $select;
        }
        if (count($getquizattemptsnotgraded) == count($getquizattempts)) {
          $row[] = get_string('notgraded', 'gradereport_quizanalytics');
        } else {
            $row[] = "<a " . ($is_student ? "" : "style='pointer-events: none; color: #999' ") . "href='#' id='viewanalytic' class='viewanalytic' data-url='" . $CFG->wwwroot . "' data-quiz_id='" . $getquizval->id . "' data-course_id='" . $courseid . "'>" . get_string('viewanalytics', 'gradereport_quizanalytics') . "</a>";
        }
        $table->data[] = $row;
    }
}
if (!empty($table)) {
    echo html_writer::start_tag('div', array('class' => 'no-overflow display-table'));
    echo html_writer::table($table);
    echo html_writer::end_tag('div');
}
$html = '<div class="showanalytics">
                    <div class="tabbable parentTabs">
                        <ul class="nav nav-tabs  ">
                            <li class="tab">
                                <a class="active" href="#tabs-1"><span class="last-attempt">Last </span>
                                ' . get_string('attemptsummary', 'gradereport_quizanalytics') . '</a>
                            </li>
                            <li class="tab">
                                <a href="#tabs-2">' . get_string('myprogress', 'gradereport_quizanalytics') . '</a>
                            </li>
                            <li class="tab">
                                <a href="#tabs-3">' . get_string('questioncategory', 'gradereport_quizanalytics') . '</a>
                            </li>
                            <li class="tab">
                                <a href="#tabs-4">' . get_string('questionstats', 'gradereport_quizanalytics') . '</a>
                            </li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane mobile-overflow active fade in" id="tabs-1">
                                <div class="canvas-wrap"><label style="width:850px;"><canvas id="lastAttempt"></canvas></label></div>
                                <p class="last-attempt-des">' . get_string('lastattemptsummarydes', 'gradereport_quizanalytics') . '</p>
                                <p class="attempt-des">' . get_string('attemptsummarydes', 'gradereport_quizanalytics') . '</p>
                            </div>
                            <div class="tab-pane mobile-overflow fade in" id="tabs-2">
                                <div class="tabbable">
                                    <ul class="nav nav-tabs  ">
                                        <li class="tab"><a class="active" href="#subtab21">
                                            <span class="improvementcurve">' . get_string('improvementcurve', 'gradereport_quizanalytics') . '</span>
                                            <span class="peerperformance">' . get_string('peerperformance', 'gradereport_quizanalytics') . '</span>
                                        </a></li>
                                        <li class="tab"><a href="#subtab22">' . get_string('hardestquestion', 'gradereport_quizanalytics') . '</a></li>
                                        <li class="tab"><a href="#subtab23">' . get_string('attemptsnapshot', 'gradereport_quizanalytics') . '</a></li>
                                    </ul>
                                    <div class="tab-content">
                                        <div id="subtab21" class="tab-pane fade in mobile-overflow active show">
                                            <div class="subtabmix">
                                                <div class="canvas-wrap">
                                                    <label style="width:700px;">
                                                        <canvas id="mixchart"></canvas>
                                                    </label>
                                                </div>
                                                <p>' . get_string('mixchartdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                            <div class="subtabtimechart1">
                                                <div class="canvas-wrap">
                                                    <label style="width:700px;">
                                                        <canvas id="timechart"></canvas>
                                                    </label>
                                                </div>
                                                <p>' . get_string('timechartdes', 'gradereport_quizanalytics') . '</p>
                                            </div>
                                        </div>
                                        <div id="subtab22" class="tab-pane fade in mobile-overflow">
                                            <div class="canvas-wrap"><label style="width:700px;">
                                                <canvas id="hardest-questions"></canvas>
                                            </lable></div>
                                            <p>' . get_string('hardestquesdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                        <div id="subtab23" class="tab-pane fade in mobile-overflow">
                                            <div class=" attemptssnapshot"></div>
                                            <p>' . get_string('attemptssnapshotdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane mobile-overflow fade in" id="tabs-3">
                                <div class="tabbable">
                                    <ul class="nav nav-tabs  ">
                                        <li class="tab">
                                            <a class="active" href="#subtab31">' . get_string('questionpercategory', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                        <li class="tab">
                                            <a href="#subtab32">' . get_string('challengingcategoris', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                        <li class="tab">
                                            <a href="#subtab33">' . get_string('challengingcategorisforme', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content">
                                        <div id="subtab31" class="tab-pane fade in mobile-overflow active show">
                                            <label style="width:400px; margin: 0 auto;"><canvas id="questionpercategories"></canvas>
                                            <div id="js-legendqpc" class="chart-legend"></div></label>
                                            <p>' . get_string('questionpercatdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                        <div id="subtab32" class="tab-pane fade in mobile-overflow">
                                           <div class="canvas-wrap"><label style="width:700px;"><canvas id="allusers"></canvas>
                                            </label></div>
                                            <p>' . get_string('allusersdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                        <div id="subtab33" class="tab-pane fade in mobile-overflow">
                                            <div class="canvas-wrap"><label style="width:700px;">
                                            <canvas id="loggedinuser"></canvas></label></div>
                                            <p>' . get_string('loggedinuserdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane mobile-overflow fade in" id="tabs-4">
                                <div class="tabbable">
                                    <ul class="nav nav-tabs  ">
                                        <li class="tab">
                                            <a class="active" href="#subtab41">' . get_string('scorbrpercent', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                        <li class="tab">
                                            <a href="#subtab42">' . get_string('quesanalysis', 'gradereport_quizanalytics') . '</a>
                                        </li>
                                    </ul>
                                    <div class="tab-content">
                                        <div id="subtab41" class="tab-pane fade in mobile-overflow active show">
                                            <label style="width:400px; margin: 0 auto;"><canvas id="gradeanalysis"></canvas>
                                            <div id="js-legendgrade" class="chart-legend"></div></label>
                                            <p>' . get_string('gradeanalysisdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                        <div id="subtab42" class="tab-pane fade in mobile-overflow">
                                            <div class="canvas-wrap"><label style="width:700px;">
                                            <canvas id="questionanalysis"></canvas></lable></div>
                                            <p>' . get_string('quesananalysisdes', 'gradereport_quizanalytics') . '</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>';
echo $html;
echo $OUTPUT->footer();
?>