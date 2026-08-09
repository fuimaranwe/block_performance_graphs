<?php
class block_performance_graphs_edit_form extends block_edit_form {
    protected function specific_definition($mform) {
        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));

        $types = [
            'bar' => get_string('type_bar', 'block_performance_graphs'),
            'pie' => get_string('type_pie', 'block_performance_graphs'),
            'radial' => get_string('type_radial', 'block_performance_graphs'),
            'line' => 'Line Chart',
            'area' => 'Area Chart'
        ];
        $mform->addElement('select', 'config_chart_type', get_string('config_chart_type', 'block_performance_graphs'), $types);
        $mform->setDefault('config_chart_type', 'bar');

        $colors = [
            '#008FFB' => 'Blue (Default)',
            '#00E396' => 'Green',
            '#FEB019' => 'Yellow',
            '#FF4560' => 'Red',
            '#775DD0' => 'Purple',
            '#3F51B5' => 'Indigo',
            '#546E7A' => 'Blue Grey',
            '#D4526E' => 'Pink',
            '#8D5B4C' => 'Brown',
            '#F86624' => 'Orange'
        ];
        $mform->addElement('select', 'config_chart_color', 'Chart Color', $colors);
        $mform->setDefault('config_chart_color', '#008FFB');

        $mform->addElement('advcheckbox', 'config_enable_threshold', 'Enable Threshold Coloring', 'Color bars green/red based on a passing grade');
        $mform->addElement('text', 'config_passing_grade', 'Passing Grade Threshold (Bar Chart)', 'value="50"');
        $mform->setType('config_passing_grade', PARAM_INT);
        $mform->setDefault('config_passing_grade', 50);
        $mform->hideIf('config_passing_grade', 'config_enable_threshold', 'notchecked');

        // Target Mode: Class Performance vs Specific Student
        $modes = [
            'class' => 'Class Performance',
            'student' => 'Specific Student Performance'
        ];
        $mform->addElement('select', 'config_target_mode', 'Target Mode', $modes);
        $mform->setDefault('config_target_mode', 'class');

        // Dynamic Course and Student lookups
        global $DB, $USER, $SITE;

        $courses = \block_performance_graphs\data_provider::get_available_courses();

        if (!empty($courses)) {
            $mform->addElement('select', 'config_course', 'Course', $courses);

            // Fetch students grouped by course
            $studentgroups = [];
            foreach ($courses as $courseid => $coursename) {
                $studentgroups[$coursename] = \block_performance_graphs\data_provider::get_available_students($courseid);
            }

            if (!empty($studentgroups)) {
                $mform->addElement('selectgroups', 'config_student', 'Select Student', $studentgroups);
                $mform->hideIf('config_student', 'config_target_mode', 'eq', 'class');
            }

            // Metrics for Class Mode
            $class_metrics = [
                'completion' => 'General Completion Rate',
                'quiz_averages' => 'Average Quiz Scores'
            ];
            $mform->addElement('select', 'config_class_metric', 'Class Metric', $class_metrics);
            $mform->hideIf('config_class_metric', 'config_target_mode', 'eq', 'student');

            // Metrics for Student Mode
            $student_metrics = [
                'activity_completion' => 'Activity Completion Progress',
                'all_scores' => 'Scores in all Quizzes & Assignments'
            ];
            $mform->addElement('select', 'config_student_metric', 'Student Metric', $student_metrics);
            $mform->hideIf('config_student_metric', 'config_target_mode', 'eq', 'class');

            $mform->addElement('advcheckbox', 'config_class_average', 'Overlay Class Average', 'Show class average for comparison');
            $mform->hideIf('config_class_average', 'config_target_mode', 'eq', 'class');
            $mform->hideIf('config_class_average', 'config_student_metric', 'eq', 'activity_completion');

            // Add JavaScript to dynamically filter the Student dropdown based on the selected Course
            $js = "
            require(['jquery'], function($) {
                var courseSelect = $('#id_config_course');
                var studentSelect = $('#id_config_student');
                if (!courseSelect.length || !studentSelect.length) return;
                
                // Store all optgroups in memory since CSS display:none doesn't work on optgroups in many browsers
                var allGroups = studentSelect.find('optgroup').clone();
                
                function filterStudents(isInitialLoad) {
                    var selectedCourseName = courseSelect.find('option:selected').text();
                    var currentStudent = studentSelect.val();
                    
                    studentSelect.empty();
                    
                    var targetGroup = allGroups.filter(function() {
                        return $(this).attr('label') === selectedCourseName;
                    }).clone();
                    
                    studentSelect.append(targetGroup);
                    
                    if (isInitialLoad && currentStudent) {
                        studentSelect.val(currentStudent);
                    }
                }
                
                courseSelect.on('change', function() { filterStudents(false); });
                // Set timeout to ensure the form is fully rendered
                setTimeout(function() { filterStudents(true); }, 100);
            });
            ";
            $this->page->requires->js_amd_inline($js);
        }
    }
}
