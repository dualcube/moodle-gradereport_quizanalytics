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
global $CFG;
$source  = required_param('source', PARAM_RAW);
$user_id  = required_param('userid', PARAM_INT);

require_login();

if (!empty($source) && !empty($user_id)) {
    $image_data = $source;
    if (!file_exists($CFG->dirroot.'/grade/report/quizanalytics/images/')) {
        mkdir($CFG->dirroot.'/grade/report/quizanalytics/images/', 0755, true);
    }
    if (!file_exists($CFG->dirroot.'/grade/report/quizanalytics/images/'.$user_id)) {
        mkdir($CFG->dirroot.'/grade/report/quizanalytics/images/'.$user_id, 0755, true);
    }
    $old_files = glob($CFG->dirroot.'/grade/report/quizanalytics/images/'.$user_id.'/*');
    foreach ($old_files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    $filtered_data = substr($image_data, strpos($image_data, ",") + 1);
    $unencoded_data = base64_decode($filtered_data);
    $file_name = '/'.rand().".png";
    $file_path = $CFG->dirroot.'/grade/report/quizanalytics/images/'.$user_id.$file_name;
    $file_url = $CFG->wwwroot.'/grade/report/quizanalytics/images/'.$user_id.$file_name;
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    $fp = fopen( $file_path, 'wb' );
    fwrite( $fp, $unencoded_data);
    fclose( $fp );
    echo $file_url;
}
