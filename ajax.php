<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

require_login();
require_sesskey();

$blockid = required_param('blockid', PARAM_INT);
$requestedcourseid = optional_param('courseid', 0, PARAM_INT);
$requestedstudentid = optional_param('studentid', 0, PARAM_INT);

$blockinstance = $DB->get_record('block_instances', [
    'id' => $blockid,
    'blockname' => 'performance_graphs',
], '*', MUST_EXIST);
$blockcontext = context_block::instance($blockid);
$parentcontext = $blockcontext->get_parent_context();

// A block on another user's Dashboard is private to that user and site managers.
if ($parentcontext->contextlevel === CONTEXT_USER && (int) $parentcontext->instanceid !== (int) $USER->id) {
    require_capability('moodle/site:manageblocks', context_system::instance());
}
if (!$blockinstance->visible) {
    require_capability('moodle/site:manageblocks', $parentcontext);
}

$decodedconfig = base64_decode($blockinstance->configdata, true);
$config = ($decodedconfig === false || $decodedconfig === '')
    ? false
    : unserialize($decodedconfig, ['allowed_classes' => ['stdClass']]);
if (!$config instanceof stdClass) {
    $config = new stdClass();
}

$configuredcourseid = !empty($config->course) ? (int) $config->course : 0;
$courseid = $requestedcourseid ?: $configuredcourseid;
if (!$courseid) {
    throw new invalid_parameter_exception('A course is required.');
}

// Course and activity blocks may only query their containing course.
$coursecontext = $parentcontext->get_course_context(false);
if ($coursecontext && (int) $coursecontext->instanceid !== $courseid) {
    throw new invalid_parameter_exception('The requested course does not belong to this block.');
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);
$config->course = $courseid;

if (($config->target_mode ?? 'class') === 'student') {
    $students = \block_performance_graphs\data_provider::get_available_students($courseid);
    if ($requestedstudentid && array_key_exists($requestedstudentid, $students)) {
        $config->student = $requestedstudentid;
    } elseif ($students) {
        $config->student = (int) array_key_first($students);
    } elseif ($requestedstudentid) {
        throw new invalid_parameter_exception('The requested student is not available in this course.');
    }
}

$chartdata = \block_performance_graphs\data_provider::get_performance_data($config);
if (($config->target_mode ?? 'class') === 'student') {
    $chartdata['_students'] = [];
    foreach ($students as $id => $name) {
        $chartdata['_students'][] = ['id' => $id, 'name' => $name];
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode($chartdata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
