<?php
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/enrollib.php");
require_once("$CFG->libdir/filelib.php");

class moodle_gradereport_quizanalytics_external extends external_api {
    public static function gradereport_quizanalytics_graded_users_selector_parameters() {
        return new external_function_parameters(
            array(
                'report' => new external_value(PARAM_TEXT, 'The user id to operate on'),
                'course' => new external_value(PARAM_TEXT, 'The user id to operate on'),
                'user_id' => new external_value(PARAM_RAW, 'The user id to operate on'),
                'group_id' => new external_value(PARAM_RAW, 'The user id to operate on'),
                'include_all' => new external_value(PARAM_TEXT, 'The user id to operate on')
            )
        );

}

    public static function gradereport_quizanalytics_graded_users_selector_returns() {
        return new external_single_structure(
            array(
            '   status' => new external_value(PARAM_RAW, 'status: true if success')
            )
        );
    }

    public static function authorizedotnet_payment_processing($report, $course, $user_id, $group_id, $include_all) {
        global $USER;
        $select = grade_get_graded_users_select($report, $course, $user_id, $group_id, $include_all);
        $output = html_writer::tag('div', $this->output->render($select), array('id' => 'graded_users_selector'));
        $output .= html_writer::tag('p', '', array('style' => 'page-break-after: always;'));
        return $output;
    }
}