<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Performance graphs block.
 *
 * @package    block_performance_graphs
 * @copyright  2026 Ahmet Bülbül
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_performance_graphs extends block_base {
    /** Initialise the block. */
    public function init(): void {
        $this->title = get_string('pluginname', 'block_performance_graphs');
    }

    /**
     * Limit placement to the two page types supported by the data-access model.
     *
     * @return array<string, bool>
     */
    public function applicable_formats(): array {
        return [
            'all' => false,
            'course-view' => true,
            'my' => true,
        ];
    }

    /**
     * Return block content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $OUTPUT, $PAGE, $SITE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $config = isset($this->config) ? clone $this->config : new stdClass();
        // A block placed inside a course is always bound to that course, including its initial render.
        if ($config && (int) $PAGE->course->id !== (int) $SITE->id) {
            $config->course = (int) $PAGE->course->id;
        }
        $courses = \block_performance_graphs\data_provider::get_available_courses();
        if (empty($config->course) && $courses) {
            $config->course = (int) array_key_first($courses);
        }
        if (empty($config->target_mode) && !empty($config->course)) {
            $coursecontext = context_course::instance((int) $config->course);
            $config->target_mode = has_capability('moodle/grade:viewall', $coursecontext) ? 'class' : 'student';
            if ($config->target_mode === 'student') {
                $config->student = (int) $USER->id;
            }
        }
        $chartdata = \block_performance_graphs\data_provider::get_performance_data($config);
        $currentcourse = !empty($config->course) ? (int) $config->course : 0;
        $coursesarray = [];

        foreach ($courses as $id => $name) {
            $coursesarray[] = [
                'id' => $id,
                'name' => $name,
                'selected' => ((int) $id === $currentcourse),
            ];
        }

        $targetmode = !empty($config->target_mode) ? $config->target_mode : 'class';
        $studentsarray = [];
        if ($targetmode === 'student' && $currentcourse) {
            $students = \block_performance_graphs\data_provider::get_available_students($currentcourse);
            $currentstudent = !empty($config->student) ? (int) $config->student : 0;
            foreach ($students as $id => $name) {
                $studentsarray[] = [
                    'id' => $id,
                    'name' => $name,
                    'selected' => ((int) $id === $currentstudent),
                ];
            }
        }

        $elementid = 'performance-graph-' . bin2hex(random_bytes(8));
        $templatedata = [
            'element_id' => $elementid,
            'block_id' => $this->instance->id,
            'current_course' => $currentcourse,
            'courses' => $coursesarray,
            'has_multiple_courses' => count($coursesarray) > 1,
            'students' => $studentsarray,
            'has_students' => !empty($studentsarray),
            'is_student_mode' => $targetmode === 'student',
            'course_label' => get_string('course'),
            'student_label' => get_string('student', 'block_performance_graphs'),
            'loading_label' => get_string('loading', 'block_performance_graphs'),
            'load_error_label' => get_string('loaderror', 'block_performance_graphs'),
        ];

        $this->content = new stdClass();
        $this->content->text = $OUTPUT->render_from_template('block_performance_graphs/chart', $templatedata);
        $this->content->footer = '';

        // This API serialises arguments safely; chart data must never be interpolated into JavaScript source.
        $PAGE->requires->js_call_amd('block_performance_graphs/chart', 'init', [$elementid, $chartdata]);

        return $this->content;
    }
}
