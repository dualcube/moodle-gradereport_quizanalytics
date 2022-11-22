<?php
require_once("$CFG->libdir/externallib.php");
class moodle_gradereport_quizanalytics_external extends external_api
{
    public static function quizanalytics_analytic_parameters()
    {
        return new external_function_parameters(
            array(
                'quizid' => new external_value(PARAM_INT, 'quiz id')
            )
        );
    }
    public static function quizanalytics_analytic($quizid)
    {
        global $DB, $USER, $CFG;
        $quiz = $DB->get_record('quiz', array('id' => $quizid));


        $totalquizattempted = $DB->get_records_sql("SELECT * FROM {quiz_attempts}
          WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ?", array($quizid));

        $usersgradedattempts = $DB->get_records_sql("SELECT * FROM {quiz_attempts}
          WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ? AND userid = ?", array($quizid, $USER->id));

        $totalnoofquestion = $DB->get_record_sql("SELECT COUNT(qs.id) as qnum
                      FROM {quiz_slots} qs, {question} q WHERE q.id = qs.id
                      AND qs.quizid = ? AND q.qtype != ?", array($quizid, 'description'));
        if (!empty($usersgradedattempts)) {
            /**
             * Return the part of random color.
             */
            function random_color_part()
            {
                return str_pad(dechex(mt_rand(0, 255)), 2, '0', STR_PAD_LEFT);
            }
            /**
             * Return the random color.
             */
            function random_color()
            {
                return random_color_part() . random_color_part() . random_color_part();
            }
            $categorys = $DB->get_records_sql("SELECT qc.id, COUNT(qs.id) as qnum,
                qc.name FROM {quiz_slots} qs, {question} q, {question_categories} qc, {question_bank_entries} qbe
                WHERE q.id = qs.id AND qbe.questioncategoryid = qc.id AND
                qs.quizid = ? AND q.qtype != ? GROUP BY qc.id", array($quizid, 'description'));
            $categoryname = $categorydata = $randomcolor = $overallhardness = $userhardness = $userswrongattemts = $wrongattemts = array();
            if (!empty($categorys)) {
                foreach ($categorys as $category) {
                    $categoryname[] = $category->name;
                    $categorydata[] = ($category->qnum) / 10;
                    $randomcolor[] = "#" . random_color();
                   
                    $correctattempts = $DB->get_records_sql("SELECT qattstep.id as qattstepid, quizatt.id as quizattid, qatt.questionid, qattstep.state, qattstep.sequencenumber FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q, {question_categories} qc, {question_bank_entries} qbe WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id AND q.id = qatt.questionid AND  qbe.questioncategoryid = qc.id AND quizatt.quiz = ? AND qattstep.questionattemptid  = ? AND q.qtype != ? AND qattstep.sequencenumber >= 2 AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')", array($quizid, $category->id, 'description'));

                    $userscorrectattempts = $DB->get_records_sql("SELECT qattstep.id as qattstepid, quizatt.id as quizattid, qatt.questionid, qattstep.state, qattstep.sequencenumber FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q, {question_categories} qc, {question_bank_entries} qbe WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id AND q.id = qatt.questionid AND  qbe.questioncategoryid = qc.id AND quizatt.quiz = ? AND qattstep.questionattemptid  = ? AND q.qtype != ? AND quizatt.userid = ? AND qattstep.sequencenumber >= 2 AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')", array($quizid, $category->id, 'description', $USER->id));
                    $categoryattempts = $category->qnum * count($totalquizattempted);
                    $categoryuserattempts = $category->qnum * count($usersgradedattempts);
                    $wrongattemts[] = ($categoryattempts - count($correctattempts));
                    $userswrongattemts[] = ($categoryuserattempts - count($userscorrectattempts));
                    $overallhardness[] = round(((($categoryattempts - count($correctattempts)) / $categoryattempts) * 100), 2);
                    $userhardness[] = round(((($categoryuserattempts -  count($userscorrectattempts)) / $categoryuserattempts) * 100), 2);
                }
            }
            
            /* questionpercat */
            $questionpercategorydata = array('labels' => $categoryname, 'datasets' => array(array(
                'label'
                => get_string('questionspercategory', 'gradereport_quizanalytics'),
                'backgroundColor' => $randomcolor, 'data' => $categorydata
            )));
            $questionpercategoryopt = array('legend' => array(
                'display' => false,
                'position' => 'bottom', 'labels' => array('boxWidth' => 13)
            ), 'title' => array(
                'display' => true,
                'position' => 'bottom', 'text' => get_string('questionspercategory', 'gradereport_quizanalytics')
            ));
            /* allusers */
            if(!empty($overallhardness))
                arsort($overallhardness) ;
            $maxhardnesskeys = array_keys($overallhardness, max($overallhardness));
            if (!empty($maxhardnesskeys)){
                foreach ($maxhardnesskeys as $maxhardnesskey) {
                    $previous = $maxhardnesskey;
                    break;
                }    
            }
            
                
            $randomoverallhardnesscolor = $overallhardnessdata = $categorynamedata = array();
            $count = 0;
            if (!empty($overallhardness)) {
                foreach ($overallhardness as $key => $val) {
                    if ($wrongattemts[$key] > 0) {
                        if ($wrongattemts[$key] >= (($wrongattemts[$previous] * 20) / 100)) {
                            if ($count < 10) {
                                $overallhardnessdata[] = $val;
                                $categorynamedata[] = $categoryname[$key];
                                $randomoverallhardnesscolor[] = "#" . random_color();
                                $count++;
                            }
                        }
                    }
                    $previous = $key;
                }
            }
            
            $allusersdata = array(
                'labels' => $categorynamedata, 'datasets' => array(
                    array(
                        'label' => get_string('hardness', 'gradereport_quizanalytics'),
                        'backgroundColor' => $randomoverallhardnesscolor,
                        'data' => $overallhardnessdata
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
            if(!empty($userhardness))
                arsort($userhardness);
            $hardnessKeys = array_keys($userhardness, max($userhardness));
            if (!empty($hardnessKeys)) {
                foreach ($hardnessKeys as $hardnessKey) {
                    $previouskey = $hardnessKey;
                    break;
                }

            }
            $randomuserhardnesscolor = $usershardnessdata = $usercategorynamedata = array();
            $count = 0;
            if (!empty($userhardness)) {
                foreach ($userhardness as $key => $val) {
                    if ($userswrongattemts[$key] > 0) {
                        if ($userswrongattemts[$key] >= (($userswrongattemts[$previouskey] * 20) / 100) ) {
                            if ($count < 10) {
                                $usershardnessdata[] = $val;
                                $usercategorynamedata[] = $categoryname[$key];
                                $randomuserhardnesscolor[] = "#" . random_color();
                                $count++;
                            }
                        }
                    }
                    $previouskey = $key;
                }
            }
            
            $loggedinuserdata = array(
                'labels' => $usercategorynamedata, 'datasets' => array(
                    array(
                        'label' => get_string('hardness', 'gradereport_quizanalytics'),
                        'backgroundColor' => $randomuserhardnesscolor, 'data' => $usershardnessdata
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
            $attemptdetailssql = "SELECT qatt.questionid, qattstep.state, qattstep.fraction, qatt.maxmark FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.questionattemptid = qatt.id AND quizatt.userid = ? AND quizatt.id = ? AND quizatt.quiz = ?";
            $totalattempted = $DB->get_records_sql($attemptdetailssql . " AND qattstep.sequencenumber = 2", array($USER->id, $lastattemptid->id, $quizid));
            $rightattempt = $DB->get_records_sql($attemptdetailssql . " AND (qattstep.state = 'gradedright' OR qattstep.state = 'mangrright')", array($USER->id, $lastattemptid->id, $quizid));
            $partialcorrectattempt = $DB->get_records_sql($attemptdetailssql . " AND (qattstep.state = 'gradedpartial' OR qattstep.state = 'mangrpartial')", array($USER->id, $lastattemptid->id, $quizid));
            $userscores = $quesmarks = array();
            $count = 0;
            if (!empty($partialcorrectattempt)) {
                foreach ($partialcorrectattempt as $partialcorrect) {
                    $count++;
                    $userscores[] = $partialcorrect->fraction;
                    $quesmarks[] = $partialcorrect->maxmark;
                }
                $totaluserscores = array_sum($userscores);
                $totalquesmarks = array_sum($quesmarks);
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
            } else {
                $lastattemptsummarydata = $lastattemptsummaryopt = array();
            }
            /* attemptssnapshot */
            
            $snapdata = $snapshotdata = $snapshotopt = array();
            if (!empty($usersgradedattempts)) {
                $count = 1;
                foreach ($usersgradedattempts as $attemptvalue) {
                    $numofattempt = $DB->get_record_sql(
                        "SELECT COUNT(qatt.questionid) as anum FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q WHERE qatt.questionusageid = quizatt.uniqueid AND q.id = qatt.questionid AND qattstep.questionattemptid = qatt.id AND qattstep.sequencenumber = 2 AND quizatt.userid = ? AND quizatt.quiz= ? AND quizatt.attempt = ? AND q.qtype != ?", array($USER->id, $quizid, $attemptvalue->attempt, 'description')
                    );

                    $timetaken = round((($attemptvalue->timefinish - $attemptvalue->timestart) / 60), 2);
                    $unattempt = ($totalnoofquestion->qnum - $numofattempt->anum);
                    $correct = $DB->get_record_sql(
                        "SELECT COUNT(qatt.questionid) as num FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.sequencenumber = 2 AND q.id = qatt.questionid AND qattstep.questionattemptid = qatt.id AND quizatt.userid = ? AND quizatt.quiz= ? AND q.qtype != ? AND quizatt.attempt = ? AND qattstep.state = ?", array($USER->id, $quizid, 'description', $attemptvalue->attempt, 'gradedright')
                    );
                    $incorrect = $DB->get_record_sql(
                        "SELECT COUNT(qatt.questionid) as num FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.sequencenumber = 2 AND q.id = qatt.questionid AND qattstep.questionattemptid = qatt.id AND quizatt.userid = ? AND quizatt.quiz= ? AND q.qtype != ? AND quizatt.attempt = ?AND qattstep.state = ?", array($USER->id, $quizid, 'description', $attemptvalue->attempt, 'gradedwrong')
                    );
                    $partialcorrect = $DB->get_record_sql(
                        "SELECT COUNT(qatt.questionid) as num FROM {quiz_attempts} quizatt, {question_attempts} qatt, {question_attempt_steps} qattstep, {question} q WHERE qatt.questionusageid = quizatt.uniqueid AND qattstep.sequencenumber = 2 AND q.id = qatt.questionid AND qattstep.questionattemptid = qatt.id AND quizatt.userid = ? AND quizatt.quiz= ? AND q.qtype != ? AND quizatt.attempt = ? AND qattstep.state = ?", array($USER->id, $quizid, 'description', $attemptvalue->attempt, 'gradedpartial')
                    );
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
                if (!empty($totalquizattempted)) {
                    foreach ($totalquizattempted as $totalquizattempt) {
                        $scores[] = ($totalquizattempt->sumgrades / $quiz->sumgrades) * 100;
                    }
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
            } else {
                $timechartdata = $timechartopt = array();
            }
            /* mixchart */
            $totalnthattempt = array();


            $attempttorichcutoff = $DB->get_records_sql(
                "SELECT * FROM {quiz_attempts} WHERE state = 'finished' AND sumgrades IS NOT NULL AND quiz = ?  AND sumgrades >= ? GROUP BY userid", array($quizid, (($quiz->sumgrades * $CFG->gradereport_quizanalytics_cutoff) / 100)));
            if (!empty($attempttorichcutoff)) {
                foreach ($attempttorichcutoff as $torichcutoff) {
                    $totalnthattempt[] = $torichcutoff->attempt;
                }
            }
            
            if (!empty($totalnthattempt)) {
                $averageattempt = array_sum($totalnthattempt) / count($totalnthattempt);
            } else {
                $averageattempt = 0;
            }
            $cutoffarray = array();
            for ($i = 0; $i <= round($averageattempt); $i++) {
                $cutoffarray[] = round((($quiz->sumgrades * $CFG->gradereport_quizanalytics_cutoff) / 100), 2);
            }
            $usersattempts = $DB->get_records_sql("SELECT * FROM {quiz_attempts} WHERE  state = 'finished' AND quiz = ? AND userid = ?", array($quizid, $USER->id));
            if (!empty($usersattempts)) {
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
            $gradeanalysislables = $randomcolor = $gradeanalysisarray = array();
            if ($CFG->gradereport_quizanalytics_globalboundary == 1) {
                $gradeboundary = explode(",", ($CFG->gradereport_quizanalytics_gradeboundary));
                if (!empty($gradeboundary)) {
                    foreach ($gradeboundary as $gradeboundary) {
                        $grades = explode("-", $gradeboundary);
                        $mingrade = ($grades[0] * $quiz->grade) / 100;
                        $maxgrade = ($grades[1] * $quiz->grade) / 100;
                        $gradeanalysislables[] = $mingrade . " - " . $maxgrade;
                        $randomcolor[] = "#" . random_color();
                        $userrecords = $DB->get_record_sql(
                            "SELECT COUNT(qg.id) as numofstudents FROM {quiz_grades} qg, {quiz} q WHERE q.id = qg.quiz AND qg.quiz = ? AND qg.grade BETWEEN ? AND ?", array($quizid, $mingrade, $maxgrade)
                        );
                        $gradeanalysisarray[] = $userrecords->numofstudents;
                    }
                }
                
            } else {
                $feedbackrecs = $DB->get_records_sql("SELECT id, mingrade, maxgrade
                FROM {quiz_feedback} WHERE quizid = ?", array($quizid));
                if (!empty($feedbackrecs)) {
                    foreach ($feedbackrecs as $feedbackrec) {
                        $mingrade = round($feedbackrec->mingrade);
                        $maxgrade = round($feedbackrec->maxgrade) - 1;

                        $gradeanalysislables[] = $mingrade . " - " . $maxgrade;
                        $randomcolor[] = "#" . random_color();

                        $userrecords = $DB->get_record_sql(
                            "SELECT COUNT(qg.id) as numofstudents FROM {quiz_grades} qg, {quiz} q WHERE q.id = qg.quiz AND qg.quiz = ? AND qg.grade BETWEEN ? AND ?", array($quizid, $mingrade, $maxgrade)
                        );

                        $gradeanalysisarray[] = $userrecords->numofstudents;
                    }
                }
                
            }
            $gradeanalysisdata = array('labels' => $gradeanalysislables, 'datasets' => array(
                array(
                    'label' => get_string('noofstudents', 'gradereport_quizanalytics'),
                    'backgroundColor' => $randomcolor, 'data' => $gradeanalysisarray
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
            $totalunattempted = $correctresponse = $incorrectresponse = $partialcorrectresponse = $queslabels = $queshardness = $wrongandunattemptd =$selectedquestionid = array();
            $count = 1;
            if (!empty($totalquestions)) {
                foreach ($totalquestions as $totalquestion) {
                    if ($totalquestion->qtype == "essay") {
                        $questionresponsesql = "SELECT COUNT(qatt.id) as qnum FROM {question_attempts} qatt, {quiz_attempts} quizatt, {question_attempt_steps} qas WHERE qas.questionattemptid = qatt.id AND quizatt.uniqueid = qatt.questionusageid AND qas.sequencenumber = 3 AND qatt.questionid = ?";
                    } else {
                        $questionresponsesql = "SELECT COUNT(qatt.id) as qnum FROM {question_attempts} qatt, {quiz_attempts} quizatt, {question_attempt_steps} qas WHERE qas.questionattemptid = qatt.id AND quizatt.uniqueid = qatt.questionusageid AND qas.sequencenumber = 2 AND quizatt.sumgrades <> 'NULL' AND quizatt.quiz= ? AND qatt.questionid = ?";
                    }
                    $totalcorrectresponse = $DB->get_record_sql( $questionresponsesql . " AND (qas.state = 'gradedright' OR qas.state = 'mangrright')", array($quizid, $totalquestion->id)
                    );
                    $totalincorrectresponse = $DB->get_record_sql( $questionresponsesql . " AND (qas.state = 'gradedwrong' OR qas.state = 'mangrwrong')", array($quizid, $totalquestion->id)
                    );
                    $totalpartialcorrectresponse = $DB->get_record_sql( $questionresponsesql . " AND (qas.state = 'gradedpartial' OR qas.state = 'mangrpartial')", array($quizid, $totalquestion->id)
                    );
                    $unattempted = count($totalquizattempted) - ($totalcorrectresponse->qnum + $totalincorrectresponse->qnum + $totalpartialcorrectresponse->qnum);
                    $totalunattempted[] = $unattempted;
                    $correctresponse[] = $totalcorrectresponse->qnum;
                    $incorrectresponse[] = $totalincorrectresponse->qnum;
                    $partialcorrectresponse[] = $totalpartialcorrectresponse->qnum;
                    $queslabels[] = "Q" . $count;
                    $wrongandunattemptd[] = $unattempted + $totalincorrectresponse->qnum;

                    $queshardness[] = round((($unattempted + $totalincorrectresponse->qnum) / count($totalquizattempted)) * 100, 2);

                    $selectedquestionid[] = "Q" . $count . "," . $totalquestion->id;
                    $count++;
                }
            }
            if(!empty($queshardness))
                arsort($queshardness);
            $maxwrunkeys = array_keys($queshardness, max($queshardness));
            if (!empty($maxwrunkeys)) {
                foreach ($maxwrunkeys as $maxwrunkey) {
                    $previous = $maxwrunkey;
                    break;
                }
            }
            
            $hardestquesdatalabel = $totalquizattemptdata = $wrongandunattemptdata = array();
            $count = 0;
            if (!empty($queshardness)) {
                foreach ($queshardness as $key => $val) {
                    if ($wrongandunattemptd[$key] > 0) {
                        $twentyperpreviouswrongattempted = (($wrongandunattemptd[$previous] * 20) / 100);
                        if ($wrongandunattemptd[$key] >= $twentyperpreviouswrongattempted) {
                            if ($count < 10) {
                                $hardestquesdatalabel[] = $queslabels[$key];
                                $totalquizattemptdata[] = count($totalquizattempted);
                                $wrongandunattemptdata[] = $wrongandunattemptd[$key];
                                $count++;
                            }
                        }
                    }
                    $previous = $key;
                }
            }
            
            $hardestquesdata = array('labels' => $hardestquesdatalabel, 'datasets' => array(
                array(
                    'label' => get_string('totalquizattempt', 'gradereport_quizanalytics'),
                    'backgroundColor' => "#8e5ea2", 'data' => $totalquizattemptdata
                ),
                array(
                    'label' => get_string('wrongandunattemptd', 'gradereport_quizanalytics'),
                    'backgroundColor' => "#EB2838", 'data' => $wrongandunattemptdata
                )
            ));
            $hardestquesopt = array('title' => array('display' => false, 'text' => get_string('hardestquestion', 'gradereport_quizanalytics')), 'legend' => array('display' => true, 'position' => 'bottom', 'labels' => array('boxWidth' => 13)), 'barPercentage' => 1.0, 'categoryPercentage' => 1.0);
            /*Quesanalysis*/
            $quesanalysisdata = array('labels' => $queslabels, 'datasets' => array(
                array(
                    'data' => $correctresponse, 'borderColor' => "#3e95cd", 'fill' => false,
                    'label' => get_string('correct', 'gradereport_quizanalytics')
                ),
                array(
                    'data' => $incorrectresponse, 'borderColor' => "#8e5ea2", 'fill' => false,
                    'label' => get_string('incorrect', 'gradereport_quizanalytics')
                ),
                array(
                    'data' => $partialcorrectresponse, 'borderColor' => "#3cba9f",
                    'fill' => false, 'label' => get_string('partialcorrect', 'gradereport_quizanalytics')
                ),
                array(
                    'data' => $totalunattempted, 'borderColor' => "#c45850", 'fill' => false,
                    'label' => get_string('unattempted', 'gradereport_quizanalytics')
                )
            ));
            $quesanalysisopt = array('title' => array('display' => false), 'legend' => array('display' => true, 'position' => 'bottom', 'labels' => array('boxWidth' => 13)));
            $totalarray = array();
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
    }
    public static function quizanalytics_analytic_returns()
    {
        return new external_value(PARAM_RAW, 'The updated JSON output');
    }
}
?>
