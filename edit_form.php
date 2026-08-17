<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Configuration form for the Performance Graphs block.
 *
 * @package    block_performance_graphs
 * @copyright  2026 Ahmet Bülbül
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_performance_graphs_edit_form extends block_edit_form {
    /** Define configuration fields. */
    protected function specific_definition($mform): void {
        $component = 'block_performance_graphs';
        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));

        $types = [
            'bar' => get_string('type_bar', $component),
            'pie' => get_string('type_pie', $component),
            'radial' => get_string('type_radial', $component),
            'line' => get_string('type_line', $component),
            'area' => get_string('type_area', $component),
        ];
        $mform->addElement('select', 'config_chart_type', get_string('config_chart_type', $component), $types);
        $mform->setDefault('config_chart_type', 'bar');

        $colors = [
            '#0f6cbf' => get_string('colorblue', $component),
            '#198754' => get_string('colorgreen', $component),
            '#ffc107' => get_string('coloryellow', $component),
            '#dc3545' => get_string('colorred', $component),
            '#6f42c1' => get_string('colorpurple', $component),
            '#3f51b5' => get_string('colorindigo', $component),
            '#546e7a' => get_string('colorbluegrey', $component),
            '#d4526e' => get_string('colorpink', $component),
            '#8d5b4c' => get_string('colorbrown', $component),
            '#f86624' => get_string('colororange', $component),
        ];
        $mform->addElement('select', 'config_chart_color', get_string('chartcolor', $component), $colors);
        $mform->setDefault('config_chart_color', '#0f6cbf');

        $mform->addElement(
            'advcheckbox',
            'config_enable_threshold',
            get_string('enablethreshold', $component),
            get_string('enablethreshold_help', $component)
        );
        $mform->addElement('text', 'config_passing_grade', get_string('passinggrade', $component));
        $mform->setType('config_passing_grade', PARAM_INT);
        $mform->setDefault('config_passing_grade', 50);
        $mform->addRule('config_passing_grade', get_string('invalidthreshold', $component), 'numeric', null, 'client');
        $mform->hideIf('config_passing_grade', 'config_enable_threshold', 'notchecked');

        $modes = [
            'class' => get_string('modeclass', $component),
            'student' => get_string('modestudent', $component),
        ];
        $mform->addElement('select', 'config_target_mode', get_string('targetmode', $component), $modes);
        $mform->setDefault('config_target_mode', 'class');

        $courses = \block_performance_graphs\data_provider::get_available_courses();
        if (!$courses) {
            $mform->addElement('static', 'config_nocourses', '', get_string('noavailablecourses', $component));
            return;
        }
        $mform->addElement('select', 'config_course', get_string('course'), $courses);
        $firstcoursecontext = context_course::instance((int) array_key_first($courses));
        if (!has_capability('moodle/grade:viewall', $firstcoursecontext)) {
            $mform->setDefault('config_target_mode', 'student');
        }

        $studentgroups = [];
        foreach ($courses as $courseid => $coursename) {
            $studentgroups[$coursename] = \block_performance_graphs\data_provider::get_available_students((int) $courseid);
        }
        if (array_filter($studentgroups)) {
            $mform->addElement('selectgroups', 'config_student', get_string('student', $component), $studentgroups);
            $mform->hideIf('config_student', 'config_target_mode', 'eq', 'class');
        }

        $classmetrics = [
            'completion' => get_string('metriccompletion', $component),
            'quiz_averages' => get_string('metricquizaverages', $component),
        ];
        $mform->addElement('select', 'config_class_metric', get_string('classmetric', $component), $classmetrics);
        $mform->hideIf('config_class_metric', 'config_target_mode', 'eq', 'student');

        $studentmetrics = [
            'activity_completion' => get_string('metricactivitycompletion', $component),
            'all_scores' => get_string('metricallscores', $component),
        ];
        $mform->addElement('select', 'config_student_metric', get_string('studentmetric', $component), $studentmetrics);
        $mform->hideIf('config_student_metric', 'config_target_mode', 'eq', 'class');

        $mform->addElement(
            'advcheckbox',
            'config_class_average',
            get_string('overlayclassaverage', $component),
            get_string('overlayclassaverage_help', $component)
        );
        $mform->hideIf('config_class_average', 'config_target_mode', 'eq', 'class');
        $mform->hideIf('config_class_average', 'config_student_metric', 'eq', 'activity_completion');
    }

    /** Validate course, student, and threshold selections server-side. */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $component = 'block_performance_graphs';
        $courseid = (int) ($data['config_course'] ?? 0);
        $courses = \block_performance_graphs\data_provider::get_available_courses();
        if ($courseid && !array_key_exists($courseid, $courses)) {
            $errors['config_course'] = get_string('invalidcourse', $component);
        }
        if (($data['config_target_mode'] ?? 'class') === 'student' && !empty($data['config_student'])) {
            $students = \block_performance_graphs\data_provider::get_available_students($courseid);
            if (!array_key_exists((int) $data['config_student'], $students)) {
                $errors['config_student'] = get_string('invalidstudent', $component);
            }
        }
        $threshold = (int) ($data['config_passing_grade'] ?? 50);
        if ($threshold < 0 || $threshold > 100) {
            $errors['config_passing_grade'] = get_string('invalidthreshold', $component);
        }
        return $errors;
    }
}
