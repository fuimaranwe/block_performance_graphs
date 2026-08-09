<?php
class block_performance_graphs extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_performance_graphs');
    }

    public function get_content() {
        if ($this->content !== null) {
            return $this->content;
        }
        global $OUTPUT;

        $chart_data = \block_performance_graphs\data_provider::get_performance_data(isset($this->config) ? $this->config : null);
        
        $courses = \block_performance_graphs\data_provider::get_available_courses();
        $courses_array = [];
        $current_course = isset($this->config->course) ? $this->config->course : 0;
        foreach ($courses as $id => $name) {
            $courses_array[] = ['id' => $id, 'name' => $name, 'selected' => ($id == $current_course)];
        }

        $students_array = [];
        $target_mode = isset($this->config->target_mode) ? $this->config->target_mode : 'class';
        if ($target_mode === 'student' && $current_course) {
            $students = \block_performance_graphs\data_provider::get_available_students($current_course);
            $current_student = isset($this->config->student) ? $this->config->student : 0;
            foreach ($students as $id => $name) {
                $students_array[] = ['id' => $id, 'name' => $name, 'selected' => ($id == $current_student)];
            }
        }

        $template_data = [
            'element_id' => 'chart_' . uniqid(),
            'block_id' => $this->instance->id,
            'sesskey' => sesskey(),
            'chart_config' => json_encode($chart_data),
            'courses' => $courses_array,
            'has_multiple_courses' => count($courses_array) > 1,
            'students' => $students_array,
            'has_multiple_students' => count($students_array) > 1,
            'is_student_mode' => $target_mode === 'student'
        ];

        $this->content = new stdClass();
        $this->content->text = $OUTPUT->render_from_template('block_performance_graphs/chart', $template_data);
        $this->content->footer = '';
        return $this->content;
    }
}
