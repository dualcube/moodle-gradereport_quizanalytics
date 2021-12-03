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

global $DB, $USER, $PAGE, $CFG;
$quiz_id  = required_param('quiz', PARAM_INT);

require_login();

if (!empty($quiz_id)) {
    $quiz = $DB->get_record('quiz', array('id' => $quiz_id));

    $attempts_sql = "SELECT * FROM {quiz_attempts}
      WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ?";

    $total_quiz_attempted = $DB->get_records_sql($attempts_sql, array($quiz_id));

    $users_graded_attempts = $DB->get_records_sql($attempts_sql." AND userid = ?", array($quiz_id, $USER->id));

    $total_no_of_question = $DB->get_record_sql("SELECT COUNT(qs.questionid) as qnum
                  FROM {quiz_slots} qs, {question} q WHERE q.id = qs.questionid
                  AND qs.quizid = ? AND q.qtype != ?", array($quiz_id, 'description'));

    if (!empty($users_graded_attempts)) {
         // Return the part of random color.
        function random_color_part() {
            return str_pad( dechex( mt_rand( 0, 255 ) ), 2, '0', STR_PAD_LEFT);
        }
         // Return the random color.
        function random_color() {
            return random_color_part() . random_color_part() . random_color_part();
        }

        $cat_details = $DB->get_records_sql("SELECT qc.id, COUNT(qs.questionid) as qnum,
        qc.name FROM {quiz_slots} qs, {question} q, {question_categories} qc
        WHERE q.id = qs.questionid AND qc.id = q.category AND
        qs.quizid = ? AND q.qtype != ? GROUP BY qc.id", array($quiz_id, 'description'));

        $cat_name = array();
        $cat_data = array();
        $random_cat_color = array();
        $overall_hardness = array();
        $loggdin_user_hardness = array();
        $total_users_wrong_attemts = array();
        $total_wrong_attemts = array();
        foreach ($cat_details as $cat_detail) {
            $cat_name[] = $cat_detail->name;
            $cat_data[] = $cat_detail->qnum;
            $random_cat_color[] = "#".random_color();

            $sql_attempt = "SELECT qattstep.id as qattstepid, quizatt.id as quizattid,
            qatt.questionid, qattstep.state, qattstep.sequencenumber
            FROM {quiz_attempts} quizatt, {question_attempts} qatt,
            {question_attempt_steps} qattstep, {question} q, {question_categories} qc
            WHERE qatt.questionusageid = quizatt.uniqueid AND
            qattstep.questionattemptid = qatt.id AND q.id = qatt.questionid
            AND qc.id = q.category AND quizatt.quiz = ? AND
            q.category = ? AND q.qtype != ?";

            $total_correct_attempts = $DB->get_records_sql($sql_attempt." AND
            qattstep.sequencenumber >= 2 AND (qattstep.state = 'gradedright' OR
            qattstep.state = 'mangrright')", array($quiz_id, $cat_detail->id, 'description'));

            $users_total_correct_attempts = $DB->get_records_sql($sql_attempt." AND
            quizatt.userid = ? AND qattstep.sequencenumber >= 2 AND
            (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')", array($quiz_id, $cat_detail->id, 'description', $USER->id));

            $users_total_correct_attempts = count($total_quiz_attempted);
            $total_quiz_user_attempts = count($users_graded_attempts);

            $total_no_of_cat_attempts = $cat_detail->qnum * $users_total_correct_attempts;
            $total_no_of_cat_user_attempts = $cat_detail->qnum * $total_quiz_user_attempts;

            $total_wrong_attemts[] = ($total_no_of_cat_attempts - count($total_correct_attempts));
            $total_users_wrong_attemts[] = ($total_no_of_cat_user_attempts - count($users_total_correct_attempts));

            $hardness = (($total_no_of_cat_attempts - count($total_correct_attempts)) / $total_no_of_cat_attempts) * 100;
            $users_hardness = (($total_no_of_cat_user_attempts - count($users_total_correct_attempts)) / $total_no_of_cat_user_attempts) * 100;

            $overall_hardness[] = round($hardness, 2);
            $loggdin_user_hardness[] = round($users_hardness, 2);
        }

        // questionpercat 
        $question_per_cat_data = array('labels' => $cat_name, 'datasets' => array(array('label'
            => get_string('questionspercategory', 'gradereport_quizanalytics'),
            'backgroundColor' => $random_cat_color, 'data' => $cat_data)));

        $question_per_cat_opt = array('legend' => array('display' => false,
            'position' => 'bottom', 'labels' => array('boxWidth' => 13)), 'title' => array('display' => true,
            'position' => 'bottom', 'text' => get_string('questionspercategory', 'gradereport_quizanalytics')));

        // allusers 
        arsort($overall_hardness);
        $max_hardness_keys = array_keys($overall_hardness, max($overall_hardness));

        foreach ($max_hardness_keys as $max_hardness_key) {
            $previous = $max_hardness_key;
            break;
        }
        $random_overall_hardness_color = array();
        $overall_hardness_data = array();
        $cat_name_data = array();
        $cat_count = 0;
        foreach ($overall_hardness as $key => $val) {
            if ($total_wrong_attemts[$key] > 0) {
                $twenty_per_previous_wrong_attempt = (($total_wrong_attemts[$previous] * 20) / 100);
                if ($total_wrong_attemts[$key] >= $twenty_per_previous_wrong_attempt) {
                    if ($cat_count < 10) {
                        $overall_hardness_data[] = $val;
                        $cat_name_data[] = $cat_name[$key];
                        $random_overall_hardness_color[] = "#".random_color();
                        $cat_count++;
                    }
                }
            }
            $previous = $key;
        }

        $all_users_data = array(
            'labels' => $cat_name_data, 'datasets' => array(
            array('label' => get_string('hardness', 'gradereport_quizanalytics'),
            'backgroundColor' => $random_overall_hardness_color,
            'data' => $overall_hardness_data)));

        $all_users_opt = array('legend' => array('display' => false,
            'position' => 'bottom'), 'title' => array('display' => false,
            'position' => 'bottom', 'text' => get_string('hardcatalluser', 'gradereport_quizanalytics')));

        // loggedinuser 
        arsort($loggdin_user_hardness);
        $max_loggdin_user_hardness_keys = array_keys($loggdin_user_hardness, max($loggdin_user_hardness));

        foreach ($max_loggdin_user_hardness_keys as $max_loggdin_user_hardness_key) {
            $previous_key = $max_loggdin_user_hardness_key;
            break;
        }

        $random_user_hardness_color = array();
        $users_hardness_data = array();
        $user_cat_name_data = array();
        $user_cat_count = 0;
        foreach ($loggdin_user_hardness as $key => $val) {
            if ($total_users_wrong_attemts[$key] > 0) {
                $twenty_per_previous_user_wrong_attempt = (($total_users_wrong_attemts[$previous_key] * 20) / 100);
                if ($total_users_wrong_attemts[$key] >= $twenty_per_previous_user_wrong_attempt) {
                    if ($user_cat_count < 10) {
                        $users_hardness_data[] = $val;
                        $user_cat_name_data[] = $cat_name[$key];
                        $random_user_hardness_color[] = "#".random_color();
                        $user_cat_count++;
                    }
                }
            }
            $previous_key = $key;
        }

        $loggedin_user_data = array(
            'labels' => $user_cat_name_data, 'datasets' => array(
            array('label' => get_string('hardness', 'gradereport_quizanalytics'),
            'backgroundColor' => $random_user_hardness_color, 'data' => $users_hardness_data)));

        $loggedin_user_opt = array('legend' => array('display' => false,
          'position' => 'bottom'), 'title' => array('display' => false,
          'position' => 'bottom', 'text' => get_string('hardcatlogginuser', 'gradereport_quizanalytics')));

        // lastattemptsummary 
        $last_attempt_id = $DB->get_record_sql("SELECT quizatt.id FROM {quiz_attempts} quizatt
        WHERE quizatt.state = 'finished' AND quizatt.sumgrades IS NOT NULL
        AND quizatt.quiz = ? AND quizatt.userid= ?
        ORDER BY quizatt.id DESC LIMIT 1", array($quiz_id, $USER->id));

        $attempt_details_sql = "SELECT qatt.questionid, qattstep.state, qattstep.fraction,
        qatt.maxmark FROM {quiz_attempts} quizatt, {question_attempts} qatt,
        {question_attempt_steps} qattstep WHERE qatt.questionusageid = quizatt.uniqueid
        AND qattstep.questionattemptid = qatt.id AND quizatt.userid = ?
        AND quizatt.id = ? AND quizatt.quiz = ?";

        $total_attempted = $DB->get_records_sql($attempt_details_sql."
            AND qattstep.sequencenumber = 2", array($USER->id, $last_attempt_id->id, $quiz_id));

        $right_attempt = $DB->get_records_sql($attempt_details_sql." AND (qattstep.state =
        'gradedright' OR qattstep.state = 'mangrright')", array($USER->id, $last_attempt_id->id, $quiz_id));

        $partial_correct_attempt = $DB->get_records_sql($attempt_details_sql."
        AND (qattstep.state = 'gradedpartial' OR qattstep.state = 'mangrpartial')", array($USER->id, $last_attempt_id->id, $quiz_id));

        $user_scores = array();
        $ques_marks = array();
        $partial_correct_count = 0;
        if (!empty($partial_correct_attempt)) {
            foreach ($partial_correct_attempt as $partial_correct) {
                $partial_correct_count++;
                $user_scores[] = $partial_correct->fraction;
                $ques_marks[] = $partial_correct->maxmark;
            }
            $total_user_scores = array_sum($user_scores);
            $total_ques_marks = array_sum($ques_marks);

            $percentage_of_marks = ($total_user_scores / $total_ques_marks) * 100;

            $num_of_partial_correct = $partial_correct_count * ($percentage_of_marks / 100);

        } else {
            $num_of_partial_correct = 0;
        }

        $correct_attempted = count($right_attempt) + round($num_of_partial_correct);

        if (!empty($total_attempted)) {
            $accuracy_rate = ($correct_attempted / count($total_attempted)) * 100;
        } else {
            $accuracy_rate = 0;
        }

        if (count($total_attempted) != 0) {
            if (count($partial_correct_attempt) != 0) {
                $last_attempt_summary_data = array('labels' => array(
                get_string('noofquestionattempt', 'gradereport_quizanalytics'),
                get_string('noofrightans', 'gradereport_quizanalytics'),
                get_string('noofpartialcorrect', 'gradereport_quizanalytics')),
                'datasets' => array(array(
                'backgroundColor' => array("#2EA0EF", "#79D527", "#FF9827"),
                'data' => array(count($total_attempted), count($right_attempt),
                count($partial_correct_attempt)))));
            } else {
                $last_attempt_summary_data = array('labels' => array(
                get_string('noofquestionattempt', 'gradereport_quizanalytics'),
                get_string('noofrightans', 'gradereport_quizanalytics')),
                'datasets' => array(array(
                'backgroundColor' => array("#2EA0EF", "#79D527"),
                'data' => array(count($total_attempted), count($right_attempt)))));
            }
            $last_attempt_summary_opt = array('legend' => array('display' => false),
            'title' => array('display' => false), 'scales' => array(
            'xAxes' => array(array('ticks' => array('min' => 0),
            'scaleLabel' => array('display' => true,
            'labelString' => get_string('accuaracyrate', 'gradereport_quizanalytics').round($accuracy_rate, 2)."%"))),
            'yAxes' => array(array('barPercentage' => 0.4))));
        } else {
            $last_attempt_summary_data = array();
            $last_attempt_summary_opt = array();
        }

        // attemptssnapshot
        $attempt_sql = "SELECT COUNT(qatt.questionid) as num
                    FROM {quiz_attempts} quizatt, {question_attempts} qatt,
                    {question_attempt_steps} qattstep, {question} q
                    WHERE qatt.questionusageid = quizatt.uniqueid
                    AND qattstep.sequencenumber = 2 AND q.id = qatt.questionid
                    AND qattstep.questionattemptid = qatt.id
                    AND quizatt.userid = ? AND quizatt.quiz= ? AND q.qtype != ?";

        $snap_data = array();
        $snapshot_data = array();
        $snapshot_opt = array();
        if (!empty($users_graded_attempts)) {
            $count = 1;
            foreach ($users_graded_attempts as $attempt_value) {
                $num_of_attempt = $DB->get_record_sql("SELECT COUNT(qatt.questionid) as anum
                    FROM {quiz_attempts} quizatt, {question_attempts} qatt,
                    {question_attempt_steps} qattstep, {question} q
                    WHERE qatt.questionusageid = quizatt.uniqueid AND q.id = qatt.questionid
                    AND qattstep.questionattemptid = qatt.id AND qattstep.sequencenumber = 2
                    AND quizatt.userid = ? AND quizatt.quiz= ? AND quizatt.attempt = ? AND q.qtype != ?",
                    array($USER->id, $quiz_id, $attempt_value->attempt, 'description'));

                $time_diff = ($attempt_value->timefinish - $attempt_value->timestart);
                $time_taken = round(($time_diff / 60), 2);

                $num_of_unattempt = ($total_no_of_question->qnum - $num_of_attempt->anum);

                $correct = $DB->get_record_sql($attempt_sql." AND quizatt.attempt = ?
                AND qattstep.state = ?",
                array($USER->id, $quiz_id, 'description', $attempt_value->attempt, 'gradedright'));

                $incorrect = $DB->get_record_sql($attempt_sql." AND quizatt.attempt = ?
                AND qattstep.state = ?",
                array($USER->id, $quiz_id, 'description', $attempt_value->attempt, 'gradedwrong'));

                $partial_correct = $DB->get_record_sql($attempt_sql." AND quizatt.attempt = ?
                AND qattstep.state = ?",
                array($USER->id, $quiz_id, 'description', $attempt_value->attempt, 'gradedpartial'));

                $snap_data[$count][0] = intval($num_of_unattempt);
                $snap_data[$count][1] = intval($correct->num);
                $snap_data[$count][2] = intval($incorrect->num);
                $snap_data[$count][3] = intval($partial_correct->num);


                $snapshot_data[$count] = array('labels' => array(
                    get_string('unattempted', 'gradereport_quizanalytics'),
                    get_string('correct', 'gradereport_quizanalytics'),
                    get_string('incorrect', 'gradereport_quizanalytics'),
                    get_string('partialcorrect', 'gradereport_quizanalytics')),
                    'datasets' => array(array('label' => 'Attempt'.$count,
                    'backgroundColor' => array('#3e95cd', '#8e5ea2', '#3cba9f', '#e8c3b9'),
                    'data' => $snap_data[$count])));

                $snapshot_opt[$count] = array('title' => array('display' => true,
                'position' => 'bottom', 'text' => get_string('timetaken',
                'gradereport_quizanalytics').$time_taken.'min)'),
                'legend' => array('display' => false, 'position' => 'bottom',
                'labels' => array('boxWidth' => 13)));

                $count++;
            }
        } else {
            $snapshot_data[1] = array('labels' => array(
                get_string('unattempted', 'gradereport_quizanalytics'),
                get_string('correct', 'gradereport_quizanalytics'),
                get_string('incorrect', 'gradereport_quizanalytics'),
                get_string('partialcorrect', 'gradereport_quizanalytics')),
                'datasets' => array(array('label' => 'Attempt1',
                'backgroundColor' => array('#3e95cd', '#8e5ea2', '#3cba9f', '#e8c3b9'),
                'data' => array(0, 0, 0, 0))));

            $snapshot_opt[1] = array('title' => array('display' => true,
            'position' => 'bottom', 'text' => 'Attempts Snapshot( timetaken: 0min )'));
        }


        // timechart 
        if ($quiz->attempts == 1) {
            $scores = array();
            $score_data = array();
            foreach ($total_quiz_attempted as $total_quiz_attempt) {
                $scores[] = ($total_quiz_attempt->sumgrades / $quiz->sumgrades ) * 100;
            }

            $user_score = $DB->get_record('quiz_attempts', array('quiz' => $quiz_id, 'userid' => $USER->id));
            $user_score_data = ($user_score->sumgrades / $quiz->sumgrades ) * 100;

            $score_data[0] = round($user_score_data, 2);
            $score_data[1] = round(max($scores), 2);
            $score_data[2] = round((array_sum($scores) / count($scores)), 2);
            $score_data[3] = round(min($scores), 2);

            $timechart_data = array('labels' => array(
                get_string('userscore', 'gradereport_quizanalytics'),
                get_string('bestscore', 'gradereport_quizanalytics'),
                get_string('avgscore', 'gradereport_quizanalytics'),
                get_string('lowestscore', 'gradereport_quizanalytics')),
                'datasets' => array(array('label' => get_string('score', 'gradereport_quizanalytics'),
                'backgroundColor' => "#3e95cd", 'data' => $score_data)));

            $timechart_opt = array('showTooltips' => false,
            'legend' => array('display' => false),
            'title' => array('display' => true, 'text' => get_string('peerscores', 'gradereport_quizanalytics')));
        } else {
            $timechart_data = array();
            $timechart_opt = array();
        }

        // mixchart
		$total_nth_attempt = array();
        $total_nth_attempt = array();

        $grade_to_pass = ($quiz->sumgrades * $CFG->gradereport_quizanalytics_cutoff) / 100;

        $attempt_to_rich_cutoff = $DB->get_records_sql($attempts_sql."
        AND sumgrades >= ? GROUP BY userid", array($quiz_id, $grade_to_pass));

        foreach ($attempt_to_rich_cutoff as $to_rich_cutoff) {
            $total_nth_attempt[] = $to_rich_cutoff->attempt;
        }
        if (!empty($total_nth_attempt)) {
            $average_nth_attempt = array_sum($total_nth_attempt) / count($total_nth_attempt);
        } else {
            $average_nth_attempt = 0;
        }

        $to_rich_cutoff_array = array();
        for ($i = 0; $i <= round($average_nth_attempt); $i++) {
            $to_rich_cutoff_array[] = round($grade_to_pass, 2);
        }

        $users_attempts = $DB->get_records_sql("SELECT * FROM {quiz_attempts} WHERE
        state = 'finished' AND quiz = ? AND userid = ?", array($quiz_id, $USER->id));

        if (!empty($users_attempts)) {
            $attempt_num = array(0);
            $scored = array(0);
            $attempt_no = 1;
            foreach ($users_attempts as $users_attempt) {
                if (!empty($users_attempt->sumgrades)) {
                    array_push($attempt_num, $attempt_no);
                    array_push($scored, round($users_attempt->sumgrades, 2));
                } else {
                    array_push($attempt_num, $attempt_no.'(NG)');
                    array_push($scored, 0);
                }
                $attempt_no++;
            }
        }
        if (round($average_nth_attempt) >= $attempt_no) {
            for ($j = $attempt_no; $j <= round($average_nth_attempt); $j++) {
                array_push($attempt_num, $j);
            }
        }

        $mixchart_data = array(
            'labels' => $attempt_num,
            'datasets' => array(array(
                'label' => get_string('cutoffscore', 'gradereport_quizanalytics'),
                'borderColor' => "#3e95cd",
                'data' => $to_rich_cutoff_array,
                'fill' => true
                ),
            array(
                'label' => get_string('score', 'gradereport_quizanalytics'),
                'borderColor' => "#8e5ea2",
                'data' => $scored,
                'fill' => false
                ))
            );

        $mixchart_opt = array('title' => array('display' => true, 'position' => 'bottom',
        'text' => get_string('impandpredicanalysis', 'gradereport_quizanalytics')),
        'legend' => array('display' => true, 'position' => 'bottom', 'labels' => array('boxWidth' => 13)));


        // gradeanalysis 
        $grade_analysis_lables = array();
        $random_color = array();
        $grade_analysis_data_array = array();
        if ($CFG->gradereport_quizanalytics_globalboundary == 1) {
                $grade_boundary_details = $CFG->gradereport_quizanalytics_gradeboundary;
                $grade_boundary_detail = explode(",", $grade_boundary_details);
            foreach ($grade_boundary_detail as $grade_boundary) {
                    $grades = explode("-", $grade_boundary);

                    $min_grade = ($grades[0] * $quiz->grade) / 100;
                    $max_grade = ($grades[1] * $quiz->grade) / 100;

                    $grade_analysis_lables[] = $min_grade." - ".$max_grade;
                    $random_color[] = "#".random_color();

                    $user_records = $DB->get_record_sql("SELECT COUNT(qg.id)
                    as numofstudents FROM {quiz_grades} qg, {quiz} q WHERE
                    q.id = qg.quiz AND qg.quiz = ? AND qg.grade BETWEEN ? AND ?",
                    array($quiz_id, $min_grade, $max_grade));

                    $grade_analysis_data_array[] = $user_records->numofstudents;
            }
        } else {
            $grade_boundary_recs = $DB->get_records_sql("SELECT id, mingrade, maxgrade
            FROM {quiz_feedback} WHERE quizid = ?", array($quiz_id));

            foreach ($grade_boundary_recs as $grade_boundary_rec) {
                $min_grade = round($grade_boundary_rec->mingrade);
                $max_grade = round($grade_boundary_rec->maxgrade) - 1;

                $grade_analysis_lables[] = $min_grade." - ".$max_grade;
                $random_color[] = "#".random_color();

                $user_records = $DB->get_record_sql("SELECT COUNT(qg.id) as numofstudents
                FROM {quiz_grades} qg, {quiz} q WHERE q.id = qg.quiz
                AND qg.quiz = ? AND qg.grade BETWEEN ? AND ?",
                array($quiz_id, $min_grade, $max_grade));

                $grade_analysis_data_array[] = $user_records->numofstudents;
            }
        }

        $grade_analysis_data = array('labels' => $grade_analysis_lables, 'datasets' => array(
        array('label' => get_string('noofstudents', 'gradereport_quizanalytics'),
        'backgroundColor' => $random_color, 'data' => $grade_analysis_data_array)));

        $grade_analysis_opt = array('title' => array('display' => true,
        'text' => get_string('noofstudents', 'gradereport_quizanalytics'), 'position' => 'bottom'),
        'legend' => array('display' => false, 'position' => 'bottom', 'labels' => array('boxWidth' => 13)));


        // quesanalysis 
        $total_questions = $DB->get_records_sql("SELECT qs.questionid, q.qtype
        FROM {quiz_slots} qs, {question} q WHERE q.id = qs.questionid AND
        qs.quizid= ? AND q.qtype != ?", array($quiz_id, 'description'));

        $total_unattempted = array();
        $correct_response = array();
        $incorrect_response = array();
        $partial_correct_response = array();
        $ques_labels = array();
        $ques_hardness = array();
        $wrong_and_unattemptd = array();
        $ques_attempts = array();
        $selected_question_id = array();
        $ques_count = 1;

        foreach ($total_questions as $total_question) {
            if ($total_question->qtype == "essay") {
                $question_response_sql = "SELECT COUNT(qatt.id) as qnum FROM
                {question_attempts} qatt, {quiz_attempts} quizatt,
                {question_attempt_steps} qas WHERE qas.questionattemptid = qatt.id
                AND quizatt.uniqueid = qatt.questionusageid AND qas.sequencenumber = 3
                AND quizatt.sumgrades <> 'NULL' AND quizatt.quiz= ?
                AND qatt.questionid = ?";
            } else {
                $question_response_sql = "SELECT COUNT(qatt.id) as qnum FROM
                {question_attempts} qatt, {quiz_attempts} quizatt,
                {question_attempt_steps} qas WHERE qas.questionattemptid = qatt.id
                AND quizatt.uniqueid = qatt.questionusageid AND qas.sequencenumber = 2
                AND quizatt.sumgrades <> 'NULL' AND quizatt.quiz= ?
                AND qatt.questionid = ?";
            }

            $total_correct_response = $DB->get_record_sql($question_response_sql."
            AND (qas.state = 'gradedright' OR qas.state = 'mangrright')",
            array($quiz_id, $total_question->questionid));

            $total_incorrect_response = $DB->get_record_sql($question_response_sql."
            AND (qas.state = 'gradedwrong' OR qas.state = 'mangrwrong')",
            array($quiz_id, $total_question->questionid));

            $total_partial_correct_response = $DB->get_record_sql($question_response_sql."
            AND (qas.state = 'gradedpartial' OR qas.state = 'mangrpartial')",
            array($quiz_id, $total_question->questionid));

            $unattempted = count($total_quiz_attempted) - (
            $total_correct_response->qnum + $total_incorrect_response->qnum + $total_partial_correct_response->qnum);

            $total_unattempted[] = $unattempted;

            $correct_response[] = $total_correct_response->qnum;
            $incorrect_response[] = $total_incorrect_response->qnum;
            $partial_correct_response[] = $total_partial_correct_response->qnum;

            $ques_labels[] = "Q".$ques_count;

            $ques_attempts[] = ($total_correct_response->qnum + $total_incorrect_response->qnum + $total_partial_correct_response->qnum);

            $wrong_and_unattemptd[] = $unattempted + $total_incorrect_response->qnum;

            $ques_hardness[] = round((($unattempted + $total_incorrect_response->qnum) / count($total_quiz_attempted)) * 100, 2);

            $selected_question_id[] = "Q".$ques_count.",".$total_question->questionid;
            $ques_count++;
        }

        arsort($ques_hardness);

        $max_wrun_keys = array_keys($ques_hardness, max($ques_hardness));

        foreach ($max_wrun_keys as $max_wrun_key) {
            $previous = $max_wrun_key;
            break;
        }
        $hardest_ques_data_label = array();
        $total_quiz_attempt_data = array();
        $wrong_and_unattempt_data = array();
        $q_count = 0;
        foreach ($ques_hardness as $key => $val) {
            if ($wrong_and_unattemptd[$key] > 0) {
                $twenty_per_previous_wrong_attempted = (($wrong_and_unattemptd[$previous] * 20) / 100);
                if ($wrong_and_unattemptd[$key] >= $twenty_per_previous_wrong_attempted) {
                    if ($q_count < 10) {
                        $hardest_ques_data_label[] = $ques_labels[$key];
                        $total_quiz_attempt_data[] = count($total_quiz_attempted);
                        $wrong_and_unattempt_data[] = $wrong_and_unattemptd[$key];
                        $q_count++;
                    }
                }
            }
            $previous = $key;
        }

        $hardest_ques_data = array('labels' => $hardest_ques_data_label, 'datasets' => array(
            array('label' => get_string('totalquizattempt', 'gradereport_quizanalytics'),
            'backgroundColor' => "#8e5ea2", 'data' => $total_quiz_attempt_data),
            array('label' => get_string('wrongandunattemptd', 'gradereport_quizanalytics'),
            'backgroundColor' => "#EB2838", 'data' => $wrong_and_unattempt_data)));

        $hardest_ques_opt = array('title' => array('display' => false,
        'text' => get_string('hardestquestion', 'gradereport_quizanalytics')),
        'legend' => array('display' => true, 'position' => 'bottom',
        'labels' => array('boxWidth' => 13)),
        'barPercentage' => 1.0, 'categoryPercentage' => 1.0);

        // Quesanalysis
        $ques_analysis_data = array('labels' => $ques_labels, 'datasets' => array(
        array('data' => $correct_response, 'borderColor' => "#3e95cd", 'fill' => false,
        'label' => get_string('correct', 'gradereport_quizanalytics')),
        array('data' => $incorrect_response, 'borderColor' => "#8e5ea2", 'fill' => false,
        'label' => get_string('incorrect', 'gradereport_quizanalytics')),
        array('data' => $partial_correct_response, 'borderColor' => "#3cba9f",
        'fill' => false, 'label' => get_string('partialcorrect', 'gradereport_quizanalytics')),
        array('data' => $total_unattempted, 'borderColor' => "#c45850", 'fill' => false,
        'label' => get_string('unattempted', 'gradereport_quizanalytics'))));

        $ques_analysis_opt = array('title' => array('display' => false),
        'legend' => array('display' => true, 'position' => 'bottom', 'labels' => array('boxWidth' => 13)));

        $total_array = array();
        $total_array = array(
                  'questionpercat' => array(
                    'data' => $question_per_cat_data,
                    'opt' => $question_per_cat_opt
                  ),
                  'allusers' => array(
                    'data' => $all_users_data,
                    'opt' => $all_users_opt
                  ),
                  'loggedinuser' => array(
                    'data' => $loggedin_user_data,
                    'opt' => $loggedin_user_opt
                  ),
                  'lastattemptsummary' => array(
                    'data' => $last_attempt_summary_data,
                    'opt' => $last_attempt_summary_opt
                  ),
                  'attemptssnapshot' => array(
                    'data' => $snapshot_data,
                    'opt' => $snapshot_opt
                  ),
                  'mixchart' => array(
                    'data' => $mixchart_data,
                    'opt' => $mixchart_opt
                  ),
                  'timechart' => array(
                    'data' => $timechart_data,
                    'opt' => $timechart_opt
                  ),
                  'gradeanalysis' => array(
                    'data' => $grade_analysis_data,
                    'opt' => $grade_analysis_opt
                  ),
                  'quesanalysis' => array(
                    'data' => $ques_analysis_data,
                    'opt' => $ques_analysis_opt
                    ),
                  'hardestques' => array(
                    'data' => $hardest_ques_data,
                    'opt' => $hardest_ques_opt
                    ),
                  'userattempts' => count($users_graded_attempts),
                  'quizattempt' => $quiz->attempts,
                  'allquestion' => $selected_question_id,
                  'quizid' => $quiz_id,
                  'url' => $CFG->wwwroot
                  );
        $total_value = json_encode($total_array);
        echo $total_value;
    }
}
