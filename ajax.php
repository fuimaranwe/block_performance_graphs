<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$blockid = required_param('blockid', PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$studentid = optional_param('studentid', 0, PARAM_INT);

global $DB;
$blockinstance = $DB->get_record('block_instances', ['id' => $blockid], '*', MUST_EXIST);
$config = unserialize(base64_decode($blockinstance->configdata));

if ($courseid) {
    $config->course = $courseid;
}
if ($studentid) {
    $config->student = $studentid;
}

$chart_data = \block_performance_graphs\data_provider::get_performance_data($config);

if (isset($config->target_mode) && $config->target_mode === 'student' && $courseid) {
    $students = \block_performance_graphs\data_provider::get_available_students($courseid);
    $students_array = [];
    foreach ($students as $id => $name) {
        $students_array[] = ['id' => $id, 'name' => $name];
    }
    $chart_data['_students'] = $students_array;
}

echo json_encode($chart_data);
