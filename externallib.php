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
 * @package   gradereport_quizanalytics
 * @author DualCube <admin@dualcube.com>
 * @copyright Dualcube (https://dualcube.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
require_once("$CFG->libdir/externallib.php");
/**
 * Returns JASON
 * @return external_function_parameters
 */
class moodle_gradereport_quizanalytics_external extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function quizanalytics_analytic_parameters() {
        return new external_function_parameters(
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz id')
            )
        );
    }
    /**
     * The function itself
     * @param int $quizid
     * @return json
     */
    public static function quizanalytics_analytic($quizid) {
        global $DB, $USER, $CFG;
        $quiz = $DB->get_record('quiz', array('id' => $quizid));
        $sql = "SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ?";
        $totalquizattempted = $DB->get_records_sql($sql, array($quizid));
        $usersgradedattempts = $DB->get_records_sql($sql." AND userid = ?", array($quizid, $USER->id));
        $totalnoofquestion = $DB->get_record_sql("SELECT COUNT(q.id) as qnum FROM {quiz_slots} qs, {question} q WHERE q.id = qs.id AND qs.quizid = ? AND q.qtype != ?", array($quizid, 'description'));
        /**
         * Returns description of method parameters
         * @return str_pad
         */
        function random_color_part() {
            return str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
        }
        /**
         * Returns description of method parameters
         * @return 3*str_pad
         */
        function random_color() {
            return random_color_part() . random_color_part() . random_color_part();
        }
        $sql = "SELECT qc.id, COUNT(q.id) as qnum, qc.name FROM {quiz_slots} qs, {question} q, {question_categories} qc, {question_bank_entries} qbe WHERE q.id = qs.id AND qbe.questioncategoryid = qc.id AND qbe.id = q.id AND qs.quizid = ? AND q.qtype != ? GROUP BY qc.id";
        $categorys = $DB->get_records_sql($sql, array($quizid, 'description'));
        $sql = "SELECT qattstep.id as qattstepid, quizatt.id as quizattid, qatt.questionid, qattstep.state, qattstep.sequencenumber FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q, {question_categories} qc, {question_bank_entries} qbe WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id AND q.id = qatt.questionid AND  qbe.questioncategoryid = qc.id AND qbe.id = q.id AND quizatt.quiz = ? AND qattstep.questionattemptid  = ? AND q.qtype != ? AND qattstep.sequencenumber >= 2 AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')";
        foreach ($categorys as $category) {
            $categoryname[] = empty($category->name) ? 'category' : $category->name;
            $chartdata[] = empty($category->qnum) ? 1 : ($category->qnum);
            $randomcolor[] = "#" . random_color();
            $correctattempts = $DB->get_records_sql($sql, array($quizid, $category->id, 'description'));
            $userscorrectattempts = $DB->get_records_sql($sql." AND quizatt.userid = ?", array($quizid, $category->id, 'description', $USER->id));
            $categoryattempts = $category->qnum * count($totalquizattempted);
            $categoryuserattempts = $category->qnum * count($usersgradedattempts);
            $wrongattemts[] = ($categoryattempts - count($correctattempts));
            $userswrongattemts[] = ($categoryuserattempts - count($userscorrectattempts));
            $overallhardness[] = round(((($categoryattempts - count($correctattempts)) / $categoryattempts) * 100), 2);
            $userhardness[] = round(((($categoryuserattempts - count($userscorrectattempts)) / $categoryuserattempts) * 100), 2);
        }
        /* questionpercat */
        $questionpercategorydata = array('labels' => $categoryname, 'datasets' => array(array(
            'label'
            => get_string('questionspercategory', 'gradereport_quizanalytics'),
            'backgroundColor' => $randomcolor, 'data' => $chartdata
        )));
        $questionpercategoryopt = array('legend' => array(
            'display' => false,
            'position' => 'bottom', 'labels' => array('boxWidth' => 13)
        ), 'title' => array(
            'display' => true,
            'position' => 'bottom', 'text' => get_string('questionspercategory', 'gradereport_quizanalytics')
        ));
        /* allusers */
        arsort($overallhardness);
        $maxhardnesskeys = array_keys($overallhardness, max($overallhardness));
        foreach ($maxhardnesskeys as $maxhardnesskey) {
            $previous = $maxhardnesskey;
            break;
        }
        $count = 0;
        $randomcolor = $chartdata = $chartlabels = array();
        foreach ($overallhardness as $key => $val) {
            if ($wrongattemts[$key] > 0) {
                if ($wrongattemts[$key] >= (($wrongattemts[$previous] * 20) / 100)) {
                    if ($count < 10) {
                        $chartdata[] = $val;
                        $chartlabels[] = $categoryname[$key];
                        $randomcolor[] = "#" . random_color();
                        $count++;
                    }
                }
            }
            $previous = $key;
        }
        $allusersdata = array(
            'labels' => $chartlabels, 'datasets' => array(
                array(
                    'label' => get_string('hardness', 'gradereport_quizanalytics'),
                    'backgroundColor' => $randomcolor,
                    'data' => $chartdata
                )
            )
        );
        $allusersopt = array('legend' => array(
            'display' => false,
            'position' => 'bottom'
        ), 'title' => array(
            'display' => false,
            'position' => 'bottom', 'text' => get_string('hardcatalluser', 'gradereport_quizanalytics')
        ));
        /* loggedinuser */
        arsort($userhardness);
        $hardnesskeys = array_keys($userhardness, max($userhardness));
        foreach ($hardnesskeys as $hardnesskey) {
            $previouskey = $hardnesskey;
            break;
        }
        $count = 0;
        $randomcolor = $chartdata = $chartlabels = array();
        foreach ($userhardness as $key => $val) {
            if ($userswrongattemts[$key] > 0) {
                if ($userswrongattemts[$key] >= (($userswrongattemts[$previouskey] * 20) / 100) ) {
                    if ($count < 10) {
                        $chartdata[] = $val;
                        $chartlabels[] = $categoryname[$key];
                        $randomcolor[] = "#" . random_color();
                        $count++;
                    }
                }
            }
            $previouskey = $key;
        }
        $loggedinuserdata = array(
            'labels' => $chartlabels, 'datasets' => array(
                array(
                    'label' => get_string('hardness', 'gradereport_quizanalytics'),
                    'backgroundColor' => $randomcolor, 'data' => $chartdata
                )
            )
        );
        $loggedinuseropt = array('legend' => array(
            'display' => false,
            'position' => 'bottom'
        ), 'title' => array(
            'display' => false,
            'position' => 'bottom', 'text' => get_string('hardcatlogginuser', 'gradereport_quizanalytics')
        ));
        /* lastattemptsummary */
        $lastattemptid = $DB->get_record_sql("SELECT quizatt.id FROM {quiz_attempts} quizatt WHERE quizatt.state = 'finished' AND quizatt.sumgrades IS NOT NULL AND quizatt.quiz = ? AND quizatt.userid= ? ORDER BY quizatt.id DESC LIMIT 1", array($quizid, $USER->id));
        $sql = "SELECT qatt.questionid, qattstep.state, qattstep.fraction, qatt.maxmark FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id AND quizatt.userid = ? AND quizatt.id = ? AND quizatt.quiz = ? ";
        $totalattempted = $DB->get_records_sql($sql." AND qattstep.sequencenumber = 2", array($USER->id, $lastattemptid->id, $quizid));
        $rightattempt = $DB->get_records_sql($sql." AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')", array($USER->id, $lastattemptid->id, $quizid));
        $partialcorrectattempt = $DB->get_records_sql($sql." AND (qattstep.state = 'gradedpartial' OR qattstep.state = 'mangrpartial')", array($USER->id, $lastattemptid->id, $quizid));
        $count = $totaluserscores = $totalquesmarks = 0;
        if (!empty($partialcorrectattempt)) {
            foreach ($partialcorrectattempt as $partialcorrect) {
                $count++;
                $totaluserscores = $totaluserscores + $partialcorrect->fraction;
                $totalquesmarks = $totalquesmarks + $partialcorrect->maxmark;
            }
            $partiallycorrect = $count * ((($totaluserscores / $totalquesmarks) * 100) / 100);
        } else {
            $partiallycorrect = 0;
        }
        if (!empty($totalattempted)) {
            $accuracyrate = ((count($rightattempt) + round($partiallycorrect)) / count($totalattempted)) * 100;
        } else {
            $accuracyrate = 0;
        }
        if (count($totalattempted) != 0) {
            if (count($partialcorrectattempt) != 0) {
                $lastattemptsummarydata = array(
                    'labels' => array(
                        get_string('noofquestionattempt', 'gradereport_quizanalytics'),
                        get_string('noofrightans', 'gradereport_quizanalytics'),
                        get_string('noofpartialcorrect', 'gradereport_quizanalytics')
                    ),
                    'datasets' => array(array(
                        'backgroundColor' => array("#2EA0EF", "#79D527", "#FF9827"),
                        'data' => array(
                            count($totalattempted), count($rightattempt),
                            count($partialcorrectattempt)
                        )
                    ))
                );
            } else {
                $lastattemptsummarydata = array(
                    'labels' => array(
                        get_string('noofquestionattempt', 'gradereport_quizanalytics'),
                        get_string('noofrightans', 'gradereport_quizanalytics')
                    ),
                    'datasets' => array(array(
                        'backgroundColor' => array("#2EA0EF", "#79D527"),
                        'data' => array(count($totalattempted), count($rightattempt))
                    ))
                );
            }
            $lastattemptsummaryopt = array(
                'legend' => array('display' => false),
                'title' => array('display' => false), 'scales' => array(
                    'xAxes' => array(array(
                        'ticks' => array('min' => 0),
                        'scaleLabel' => array(
                            'display' => true,
                            'labelString' => get_string('accuaracyrate', 'gradereport_quizanalytics') . round($accuracyrate, 2) . "%"
                        )
                    )),
                    'yAxes' => array(array('barPercentage' => 0.4))
                )
            );
        }
        /* attemptssnapshot */
        if (!empty($usersgradedattempts)) {
            $count = 1;
            $sql = "SELECT COUNT(qatt.questionid) as num FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.sequencenumber = 2 AND q.id = qatt.questionid AND qattstep.questionattemptid = qatt.id AND quizatt.userid = ? AND quizatt.quiz= ? AND q.qtype != ? AND quizatt.attempt = ? AND qattstep.state = ?";
            foreach ($usersgradedattempts as $attemptvalue) {
                $numofattempt = $DB->get_record_sql("SELECT COUNT(qatt.questionid) as anum FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q WHERE qatt.questionusageid = quizatt.uniqueid AND q.id = qatt.questionid AND qattstep.questionattemptid = qatt.id AND qattstep.sequencenumber = 2 AND quizatt.userid = ? AND quizatt.quiz= ? AND quizatt.attempt = ? AND q.qtype != ?", array($USER->id, $quizid, $attemptvalue->attempt, 'description'));
                $timetaken = round((($attemptvalue->timefinish - $attemptvalue->timestart) / 60), 2);
                $unattempt = ($totalnoofquestion->qnum - $numofattempt->anum);
                $correct = $DB->get_record_sql($sql, array($USER->id, $quizid, 'description', $attemptvalue->attempt, 'gradedright'));
                $incorrect = $DB->get_record_sql($sql, array($USER->id, $quizid, 'description', $attemptvalue->attempt, 'gradedwrong'));
                $partialcorrect = $DB->get_record_sql($sql, array($USER->id, $quizid, 'description', $attemptvalue->attempt, 'gradedpartial'));
                $snapdata[$count][0] = intval($unattempt);
                $snapdata[$count][1] = intval($correct->num);
                $snapdata[$count][2] = intval($incorrect->num);
                $snapdata[$count][3] = intval($partialcorrect->num);
                $snapshotdata[$count] = array(
                    'labels' => array(
                        get_string('unattempted', 'gradereport_quizanalytics'),
                        get_string('correct', 'gradereport_quizanalytics'),
                        get_string('incorrect', 'gradereport_quizanalytics'),
                        get_string('partialcorrect', 'gradereport_quizanalytics')
                    ),
                    'datasets' => array(array(
                        'label' => 'Attempt' . $count,
                        'backgroundColor' => array('#3e95cd', '#8e5ea2', '#3cba9f', '#e8c3b9'),
                        'data' => $snapdata[$count]
                    ))
                );
                $snapshotopt[$count] = array(
                    'title' => array(
                        'display' => true,
                        'position' => 'bottom', 'text' => get_string(
                            'timetaken',
                            'gradereport_quizanalytics'
                        ) . $timetaken . 'min)'
                    ),
                    'legend' => array(
                        'display' => false, 'position' => 'bottom',
                        'labels' => array('boxWidth' => 13)
                    )
                );
                $count++;
            }
        } else {
            $snapshotdata[1] = array(
                'labels' => array(
                    get_string('unattempted', 'gradereport_quizanalytics'),
                    get_string('correct', 'gradereport_quizanalytics'),
                    get_string('incorrect', 'gradereport_quizanalytics'),
                    get_string('partialcorrect', 'gradereport_quizanalytics')
                ),
                'datasets' => array(array(
                    'label' => 'Attempt1',
                    'backgroundColor' => array('#3e95cd', '#8e5ea2', '#3cba9f', '#e8c3b9'),
                    'data' => array(0, 0, 0, 0)
                ))
            );
            $snapshotopt[1] = array('title' => array(
                'display' => true,
                'position' => 'bottom', 'text' => 'Attempts Snapshot( timetaken: 0min )'
            ));
        }
        /* timechart */
        if ($quiz->attempts == 1) {
            $scores = $scoredata = array();
            foreach ($totalquizattempted as $totalquizattempt) {
                $scores[] = ($totalquizattempt->sumgrades / $quiz->sumgrades) * 100;
            }
            $userscore = $DB->get_record('quiz_attempts', array('quiz' => $quizid, 'userid' => $USER->id));
            $userscoredata = ($userscore->sumgrades / $quiz->sumgrades) * 100;
            $scoredata[0] = round($userscoredata, 2);
            $scoredata[1] = round(max($scores), 2);
            $scoredata[2] = round((array_sum($scores) / count($scores)), 2);
            $scoredata[3] = round(min($scores), 2);
            $timechartdata = array(
                'labels' => array(
                    get_string('userscore', 'gradereport_quizanalytics'),
                    get_string('bestscore', 'gradereport_quizanalytics'),
                    get_string('avgscore', 'gradereport_quizanalytics'),
                    get_string('lowestscore', 'gradereport_quizanalytics')
                ),
                'datasets' => array(array(
                    'label' => get_string('score', 'gradereport_quizanalytics'),
                    'backgroundColor' => "#3e95cd", 'data' => $scoredata
                ))
            );
            $timechartopt = array(
                'showTooltips' => false,
                'legend' => array('display' => false),
                'title' => array('display' => true, 'text' => get_string('peerscores', 'gradereport_quizanalytics'))
            );
        }
        /* mixchart */
        $attemptcutoff = $DB->get_records_sql("SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ?  AND sumgrades >= ? GROUP BY userid", array($quizid, (($quiz->sumgrades * $CFG->gradereport_quizanalytics_cutoff) / 100)));
        foreach ($attemptcutoff as $torichcutoff) {
            $attemptresult[] = $torichcutoff->attempt;
        }
        if (!empty($attemptresult)) {
            $averageattempt = array_sum($attemptresult) / count($attemptresult);
        } else {
            $averageattempt = 0;
        }
        for ($i = 0; $i <= round($averageattempt); $i++) {
            $cutoffarray[] = round((($quiz->sumgrades * $CFG->gradereport_quizanalytics_cutoff) / 100), 2);
        }
        $usersattempts = $DB->get_records_sql("SELECT * FROM {quiz_attempts} WHERE  state = 'finished' AND quiz = ? AND userid = ?", array($quizid, $USER->id));
        $attemptnum = $scored = array(0);
        $count = 1;
        foreach ($usersattempts as $usersattempt) {
            if (!empty($usersattempt->sumgrades)) {
                array_push($attemptnum, $count);
                array_push($scored, round($usersattempt->sumgrades, 2));
            } else {
                array_push($attemptnum, $count . '(NG)');
                array_push($scored, 0);
            }
            $count++;
        }
        if (round($averageattempt) >= $count) {
            for ($j = $count; $j <= round($averageattempt); $j++) {
                array_push($attemptnum, $j);
            }
        }
        $mixchartdata = array(
            'labels' => $attemptnum,
            'datasets' => array(
                array(
                    'label' => get_string('cutoffscore', 'gradereport_quizanalytics'),
                    'borderColor' => "#3e95cd",
                    'data' => $cutoffarray,
                    'fill' => true
                ),
                array(
                    'label' => get_string('score', 'gradereport_quizanalytics'),
                    'borderColor' => "#8e5ea2",
                    'data' => $scored,
                    'fill' => false
                )
            )
        );
        $mixchartopt = array(
            'title' => array(
                'display' => true, 'position' => 'bottom',
                'text' => get_string('impandpredicanalysis', 'gradereport_quizanalytics')
            ),
            'legend' => array('display' => true, 'position' => 'bottom', 'labels' => array('boxWidth' => 13))
        );
        /* gradeanalysis */
        $chartdata = $chartlabels = array();
        $sql = "SELECT COUNT(qg.id) as numofstudents FROM {quiz_grades} qg, {quiz} q WHERE q.id = qg.quiz AND qg.quiz = ? AND qg.grade BETWEEN ? AND ?";
        if ($CFG->gradereport_quizanalytics_globalboundary == 1) {
            $gradeboundary = explode(",", ($CFG->gradereport_quizanalytics_gradeboundary));
            if (!empty($gradeboundary)) {
                foreach ($gradeboundary as $gradeboundary) {
                    $grades = explode("-", $gradeboundary);
                    $mingrade = ($grades[0] * $quiz->grade) / 100;
                    $maxgrade = ($grades[1] * $quiz->grade) / 100;
                    $chartlabels[] = $mingrade . " - " . $maxgrade;
                    $randomcolor[] = "#" . random_color();
                    $userrecords = $DB->get_record_sql($sql, array($quizid, $mingrade, $maxgrade));
                    $chartdata[] = $userrecords->numofstudents;
                }
            }
        } else {
            $feedbackrecs = $DB->get_records_sql("SELECT id, mingrade, maxgrade FROM {quiz_feedback} WHERE quizid = ?", array($quizid));
            foreach ($feedbackrecs as $feedbackrec) {
                $mingrade = round($feedbackrec->mingrade);
                $maxgrade = round($feedbackrec->maxgrade) - 1;
                $chartlabels[] = $mingrade . " - " . $maxgrade;
                $randomcolor[] = "#" . random_color();
                $userrecords = $DB->get_record_sql($sql, array($quizid, $mingrade, $maxgrade));
                $chartdata[] = $userrecords->numofstudents;
            }
        }
        $gradeanalysisdata = array('labels' => $chartlabels, 'datasets' => array(
            array(
                'label' => get_string('noofstudents', 'gradereport_quizanalytics'),
                'backgroundColor' => $randomcolor, 'data' => $chartdata
            )
        ));
        $gradeanalysisopt = array(
            'title' => array(
                'display' => true,
                'text' => get_string('noofstudents', 'gradereport_quizanalytics'), 'position' => 'bottom'
            ),
            'legend' => array('display' => false, 'position' => 'bottom', 'labels' => array('boxWidth' => 13))
        );
        /* quesanalysis */
        $totalquestions = $DB->get_records_sql("SELECT qs.id, q.qtype FROM {quiz_slots} qs, {question} q WHERE q.id = qs.id AND qs.quizid= ? AND q.qtype != ?", array($quizid, 'description'));
        $count = 1;
        $sql = "SELECT COUNT(qatt.id) as qnum FROM {question_attempts} qatt, {quiz_attempts} quizatt, {question_attempt_steps} qas WHERE qas.questionattemptid = qatt.id AND quizatt.uniqueid = qatt.questionusageid AND qas.sequencenumber = ? AND quizatt.sumgrades <> 'NULL' AND quizatt.quiz= ? AND qatt.questionid = ? AND quizatt.userid = ? AND";
        foreach ($totalquestions as $totalquestion) {
            if ($totalquestion->qtype == "essay") {
                 $sequencenumber = 3;
            } else {
                $sequencenumber = 2;
            }
             $usercorrectresponse = $DB->get_record_sql( $sql." (qas.state = 'gradedright' OR qas.state = 'mangrright')", array($sequencenumber, $quizid, $totalquestion->id, $USER->id)
            );
            $userincorrectresponse = $DB->get_record_sql( $sql." (qas.state = 'gradedwrong' OR qas.state = 'mangrwrong')", array($sequencenumber, $quizid, $totalquestion->id, $USER->id)
            );
            $userpartialresponse = $DB->get_record_sql( $sql." (qas.state = 'gradedpartial' OR qas.state = 'mangrpartial')", array($sequencenumber, $quizid, $totalquestion->id, $USER->id)
            );
            $userunattempted[] = count($usersgradedattempts) - ($usercorrectresponse->qnum + $userincorrectresponse->qnum + $userpartialresponse->qnum);
            $unattempted = count($totalquizattempted) - ($usercorrectresponse->qnum + $userincorrectresponse->qnum + $userpartialresponse->qnum);
            $correctresponse[] = $usercorrectresponse->qnum;
            $incorrectresponse[] = $userincorrectresponse->qnum;
            $partialresponse[] = $userpartialresponse->qnum;
            $questionlabels[] = "Q" . $count;
            $negativeattemptd[] = $unattempted + $userincorrectresponse->qnum;
            $queshardness[] = round((($unattempted + $userincorrectresponse->qnum) / count($totalquizattempted)) * 100, 2);
            $selectedquestionid[] = "Q" . $count . "," . $totalquestion->id;
            $count++;
        }
        arsort($queshardness);
        $maxhardness = array_keys($queshardness, max($queshardness));
        foreach ($maxhardness as $maxhardnesskey) {
            $previous = $maxhardnesskey;
            break;
        }
        $count = 0;
        foreach ($queshardness as $key => $val) {
            if ($negativeattemptd[$key] > 0) {
                if ($negativeattemptd[$key] >= (($negativeattemptd[$previous] * 20) / 100)) {
                    if ($count < 10) {
                        $hardestquesdatalabel[] = $questionlabels[$key];
                        $totalquizattemptdata[] = count($totalquizattempted);
                        $negativeattemptdata[] = $negativeattemptd[$key];
                        $count++;
                    }
                }
            }
            $previous = $key;
        }
        $hardestquesdata = array('labels' => $hardestquesdatalabel, 'datasets' => array(
            array(
                'label' => get_string('totalquizattempt', 'gradereport_quizanalytics'),
                'backgroundColor' => "#8e5ea2", 'data' => $totalquizattemptdata
            ),
            array(
                'label' => get_string('wrongandunattemptd', 'gradereport_quizanalytics'),
                'backgroundColor' => "#EB2838", 'data' => $negativeattemptdata
            )
        ));
        $hardestquesopt = array('title' => array('display' => false, 'text' => get_string('hardestquestion', 'gradereport_quizanalytics')), 'legend' => array('display' => true, 'position' => 'bottom', 'labels' => array('boxWidth' => 13)), 'barPercentage' => 1.0, 'categoryPercentage' => 1.0);
        /*Quesanalysis*/
        $quesanalysisdata = array('labels' => $questionlabels, 'datasets' => array(
            array(
                'data' => $correctresponse, 'borderColor' => "#3e95cd", 'fill' => false,
                'label' => get_string('correct', 'gradereport_quizanalytics')
            ),
            array(
                'data' => $incorrectresponse, 'borderColor' => "#8e5ea2", 'fill' => false,
                'label' => get_string('incorrect', 'gradereport_quizanalytics')
            ),
            array(
                'data' => $partialresponse, 'borderColor' => "#3cba9f",
                'fill' => false, 'label' => get_string('partialcorrect', 'gradereport_quizanalytics')
            ),
            array(
                'data' => $userunattempted, 'borderColor' => "#c45850", 'fill' => false,
                'label' => get_string('unattempted', 'gradereport_quizanalytics')
            )
        ));
        $quesanalysisopt = array('title' => array('display' => false), 'legend' => array('display' => true, 'position' => 'bottom', 'labels' => array('boxWidth' => 13)));
        $totalarray = array(
            'questionPerCategories' => array(
                'data' => $questionpercategorydata,
                'opt' => $questionpercategoryopt
            ),
            'allUsers' => array(
                'data' => $allusersdata,
                'opt' => $allusersopt
            ),
            'loggedInUser' => array(
                'data' => $loggedinuserdata,
                'opt' => $loggedinuseropt
            ),
            'lastAttemptSummary' => array(
                'data' => $lastattemptsummarydata,
                'opt' => $lastattemptsummaryopt
            ),
            'attemptssnapshot' => array(
                'data' => $snapshotdata,
                'opt' => $snapshotopt
            ),
            'mixChart' => array(
                'data' => $mixchartdata,
                'opt' => $mixchartopt
            ),
            'timeChart' => array(
                'data' => $timechartdata,
                'opt' => $timechartopt
            ),
            'gradeAnalysis' => array(
                'data' => $gradeanalysisdata,
                'opt' => $gradeanalysisopt
            ),
            'quesAnalysis' => array(
                'data' => $quesanalysisdata,
                'opt' => $quesanalysisopt
            ),
            'hardestQuestions' => array(
                'data' => $hardestquesdata,
                'opt' => $hardestquesopt
            ),
            'userAttempts' => count($usersgradedattempts),
            'quizAttempt' => $quiz->attempts,
            'allQuestions' => $selectedquestionid,
            'quizid' => $quizid,
            'lastUserQuizAttemptID' => $lastattemptid->id,
            'url' => $CFG->wwwroot
        );
        return json_encode($totalarray);
    }
    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function quizanalytics_analytic_returns() {
        return new external_value(PARAM_RAW, 'The updated JSON output');
    }
}
