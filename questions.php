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
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
$url = new moodle_url('/grade/report/quizanalytics/questions.php');
$quiz_id  = required_param('quizid', PARAM_INT);
$question_id  = required_param('quesid', PARAM_INT);
// set url of the page
$PAGE->set_url($url);    
$PAGE->set_context(context_system::instance());
// requires login
require_login();

$output = $PAGE->get_renderer('mod_quiz');
// Getting a single database record from quiz table where id = $quiz_id
$quiz = $DB->get_record('quiz', array('id' => $quiz_id));
// Getting how many questions attempted
$attempt = $DB->get_record_sql("SELECT quizatt.id, quizatt.uniqueid
            FROM {quiz_attempts} quizatt WHERE quizatt.state = 'finished'
            AND quizatt.sumgrades IS NOT NULL AND quizatt.quiz = ? AND
            quizatt.userid = ? ORDER BY quizatt.id DESC LIMIT 1", array($quiz->id, $USER->id));

$display_options = mod_quiz_display_options::make_from_quiz($quiz,
            mod_quiz_display_options::AFTER_CLOSE);

$attempt_obj = quiz_attempt::create($attempt->id);
// Getting a single database record from quiz_slot table where questionid = $question_id and quizid = $quiz->id
$quiz_slot = $DB->get_record('quiz_slots', array('questionid' => $question_id, 'quizid' => $quiz->id));

echo $output->review_page($attempt_obj, array($quiz_slot->slot), 0, 1, 1, $display_options, array());
