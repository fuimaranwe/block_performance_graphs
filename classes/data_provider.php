<?php
namespace block_performance_graphs;

class data_provider {
    public static function get_available_courses() {
        global $USER, $SITE, $PAGE;
        $courses = [];
        if ($PAGE->course->id == $SITE->id) {
            $usercourses = enrol_get_users_courses($USER->id, true, 'id, shortname');
            foreach ($usercourses as $c) {
                $courses[$c->id] = format_string($c->shortname);
            }
        } else {
            $courses[$PAGE->course->id] = format_string($PAGE->course->shortname);
        }
        return $courses;
    }

    public static function get_available_students($courseid) {
        global $USER;
        $students = [];
        $context = \context_course::instance($courseid);
        
        if (has_capability('moodle/grade:viewall', $context)) {
            $enrolled = get_enrolled_users($context, 'mod/assign:submit', 0, 'u.id, u.firstname, u.lastname');
            if ($enrolled) {
                foreach ($enrolled as $u) {
                    $students[$u->id] = fullname($u);
                }
            }
        } else {
            $students[$USER->id] = fullname($USER);
        }
        return $students;
    }

    public static function get_performance_data($config) {
        if (!$config) {
            return self::get_empty_chart('bar');
        }

        $type = !empty($config->chart_type) ? $config->chart_type : 'bar';
        $mode = !empty($config->target_mode) ? $config->target_mode : 'class';
        $courseid = !empty($config->course) ? $config->course : 0;
        $color = !empty($config->chart_color) ? $config->chart_color : '#008FFB';
        
        if (!$courseid) {
            return self::get_empty_chart($type);
        }

        global $DB, $USER;
        $title = '';
        $course = $DB->get_record('course', ['id' => $courseid], 'shortname');
        $coursename = $course ? format_string($course->shortname) : '';
        $context = \context_course::instance($courseid);

        $chart_data = self::get_empty_chart($type);

        if ($mode === 'class') {
            if (!has_capability('moodle/grade:viewall', $context)) {
                return self::get_empty_chart($type);
            }
            $title = $coursename;
            $metric = !empty($config->class_metric) ? $config->class_metric : 'completion';
            
            if ($metric === 'completion') {
                $chart_data = self::get_class_completion_data($courseid, $type);
            } else if ($metric === 'quiz_averages') {
                $chart_data = self::get_class_quiz_averages($courseid, $type);
            }
        } else if ($mode === 'student') {
            $metric = !empty($config->student_metric) ? $config->student_metric : 'activity_completion';
            $studentid = !empty($config->student) ? $config->student : 0;
            
            if (!$studentid) {
                return self::get_empty_chart($type);
            }

            if ($studentid != $USER->id && !has_capability('moodle/grade:viewall', $context)) {
                $studentid = $USER->id;
            }

            $user = $DB->get_record('user', ['id' => $studentid], 'firstname, lastname');
            $username = $user ? fullname($user) : 'Student';
            $title = $username . ' - ' . $coursename;
            
            if ($metric === 'activity_completion') {
                $chart_data = self::get_student_completion_progress($courseid, $studentid, $type);
            } else if ($metric === 'all_scores') {
                $show_average = !empty($config->class_average);
                $chart_data = self::get_student_activity_scores($courseid, $studentid, $type, $show_average);
            }
        }
        
        if (!empty($title)) {
            $chart_data['title'] = ['text' => $title, 'align' => 'left'];
        }
        if (!empty($color)) {
            if ($type === 'pie' || $type === 'radial') {
                $chart_data['colors'] = self::generate_color_palette($color, 5);
            } else {
                $chart_data['colors'] = [$color];
            }
        }

        if ($type === 'bar' && !empty($config->enable_threshold) && isset($config->passing_grade) && $config->passing_grade > 0) {
            $threshold = (float)$config->passing_grade;
            if (!isset($chart_data['plotOptions'])) {
                $chart_data['plotOptions'] = [];
            }
            if (!isset($chart_data['plotOptions']['bar'])) {
                $chart_data['plotOptions']['bar'] = [];
            }
            if (!isset($chart_data['plotOptions']['bar']['colors'])) {
                $chart_data['plotOptions']['bar']['colors'] = [];
            }
            $chart_data['plotOptions']['bar']['colors']['ranges'] = [
                ['from' => 0, 'to' => $threshold - 0.01, 'color' => '#FF4560'],
                ['from' => $threshold, 'to' => 100, 'color' => '#00E396']
            ];
        }
        
        $chart_data['chart']['animations'] = [
            'enabled' => true,
            'easing' => 'easeinout',
            'speed' => 800,
            'animateGradually' => ['enabled' => true, 'delay' => 150],
            'dynamicAnimation' => ['enabled' => true, 'speed' => 350]
        ];
        $chart_data['tooltip'] = ['theme' => 'light', 'style' => ['fontSize' => '14px']];

        if ($type === 'bar') {
            if (!isset($chart_data['plotOptions'])) $chart_data['plotOptions'] = [];
            if (!isset($chart_data['plotOptions']['bar'])) $chart_data['plotOptions']['bar'] = [];
            $chart_data['plotOptions']['bar']['borderRadius'] = 6;
            
            $chart_data['fill'] = [
                'type' => 'gradient',
                'gradient' => [
                    'shade' => 'light', 'type' => 'vertical', 'shadeIntensity' => 0.25,
                    'inverseColors' => false, 'opacityFrom' => 0.9, 'opacityTo' => 0.7, 'stops' => [0, 100]
                ]
            ];
        } else if ($type === 'line' || $type === 'area') {
            $chart_data['stroke'] = ['curve' => 'smooth', 'width' => 3];
            if ($type === 'area') {
                $chart_data['fill'] = [
                    'type' => 'gradient',
                    'gradient' => [
                        'shadeIntensity' => 1, 'opacityFrom' => 0.7, 'opacityTo' => 0.2, 'stops' => [0, 100]
                    ]
                ];
            }
        }

        $chart_data['noData'] = [
            'text' => 'No data available',
            'align' => 'center',
            'verticalAlign' => 'middle',
            'style' => ['color' => '#888', 'fontSize' => '16px']
        ];

        if (!empty($chart_data['series'])) {
            if ($metric === 'completion' && $mode === 'class') {
                $completed = $chart_data['series'][0]['data'][0] ?? 0;
                $in_progress = $chart_data['series'][0]['data'][1] ?? 0;
                if (($completed + $in_progress) > 0) {
                    $chart_data['_stat_callout'] = ['value' => round(($completed / ($completed + $in_progress)) * 100) . '%', 'label' => 'Class Completion Rate'];
                }
            } else if ($metric === 'activity_completion' && $mode === 'student') {
                if ($type === 'radial' || $type === 'pie') {
                    $chart_data['_stat_callout'] = ['value' => ($chart_data['series'][0] ?? 0) . '%', 'label' => 'Activities Completed'];
                } else {
                    $completed = $chart_data['series'][0]['data'][0] ?? 0;
                    $in_progress = $chart_data['series'][0]['data'][1] ?? 0;
                    if (($completed + $in_progress) > 0) {
                        $chart_data['_stat_callout'] = ['value' => round(($completed / ($completed + $in_progress)) * 100) . '%', 'label' => 'Activities Completed'];
                    }
                }
            }
        }

        if (isset($chart_data['series'][0]['data']) && count($chart_data['series'][0]['data']) == 1 && $chart_data['series'][0]['data'][0] === 0) {
            $cat = $chart_data['xaxis']['categories'][0] ?? '';
            if (in_array($cat, ['No Quizzes Found', 'No Grades Found', 'No trackable activities'])) {
                $chart_data['series'] = [];
                $chart_data['noData']['text'] = $cat;
            }
        }

        return $chart_data;
    }

    private static function generate_color_palette($base_color, $count) {
        $colors = [$base_color];
        $base_color = ltrim($base_color, '#');
        if (strlen($base_color) == 6) {
            list($r, $g, $b) = array(
                hexdec(substr($base_color, 0, 2)),
                hexdec(substr($base_color, 2, 2)),
                hexdec(substr($base_color, 4, 2))
            );
            
            for ($i = 1; $i < $count; $i++) {
                // lighten the color
                $r = min(255, $r + 30);
                $g = min(255, $g + 30);
                $b = min(255, $b + 30);
                $colors[] = sprintf("#%02x%02x%02x", $r, $g, $b);
            }
        }
        return $colors;
    }
    
    private static function get_empty_chart($type) {
        if ($type === 'pie') {
            return ['series' => [], 'labels' => [], 'chart' => ['type' => 'pie', 'width' => '100%']];
        } else if ($type === 'radial') {
            return ['series' => [], 'labels' => [], 'chart' => ['type' => 'radialBar', 'height' => 350]];
        } else if ($type === 'line' || $type === 'area') {
            return ['series' => [], 'xaxis' => ['categories' => []], 'chart' => ['type' => $type, 'height' => 350]];
        }
        return ['series' => [], 'xaxis' => ['categories' => []], 'chart' => ['type' => 'bar', 'height' => 350]];
    }

    private static function get_class_completion_data($courseid, $type) {
        global $DB;
        
        $completed = 0;
        $in_progress = 0;
        
        try {
            $sql = "SELECT userid, timecompleted FROM {course_completions} WHERE course = :course";
            $records = $DB->get_records_sql($sql, ['course' => $courseid]);
            foreach ($records as $r) {
                if ($r->timecompleted > 0) {
                    $completed++;
                } else {
                    $in_progress++;
                }
            }
        } catch (\Exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        
        $labels = ['Completed', 'In Progress'];
        $data = [$completed, $in_progress];
        
        return self::format_chart_data($labels, $data, $type, 'Students');
    }

    private static function get_class_quiz_averages($courseid, $type) {
        global $DB;
        
        $labels = [];
        $data = [];
        
        try {
            // Find all quizzes in this course and their average grades
            $sql = "SELECT q.id, q.name, AVG((gg.finalgrade / gi.grademax) * 100) AS avg_percent
                    FROM {quiz} q
                    JOIN {grade_items} gi ON gi.iteminstance = q.id AND gi.itemmodule = 'quiz' AND gi.itemtype = 'mod'
                    JOIN {grade_grades} gg ON gg.itemid = gi.id
                    WHERE q.course = :course AND gg.finalgrade IS NOT NULL AND gi.grademax > 0
                    GROUP BY q.id, q.name";
                    
            $records = $DB->get_records_sql($sql, ['course' => $courseid]);
            foreach ($records as $r) {
                $name = format_string($r->name);
                // Break long titles into multiple lines to prevent overlap in ApexCharts
                $labels[] = explode("\n", wordwrap($name, 15, "\n"));
                $data[] = round($r->avg_percent, 1);
            }
        } catch (\Exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        
        if (empty($labels)) {
            $labels[] = 'No Quizzes Found';
            $data[] = 0;
        }
        
        return self::format_chart_data($labels, $data, $type, 'Average Score (%)');
    }

    private static function get_student_completion_progress($courseid, $studentid, $type) {
        global $DB;
        
        $completed = 0;
        $total = 0;
        
        try {
            $sql = "SELECT cm.id, cmc.completionstate 
                    FROM {course_modules} cm
                    LEFT JOIN {course_modules_completion} cmc ON cmc.coursemoduleid = cm.id AND cmc.userid = :userid
                    WHERE cm.course = :course AND cm.completion > 0";
                    
            $records = $DB->get_records_sql($sql, ['userid' => $studentid, 'course' => $courseid]);
            foreach ($records as $r) {
                $total++;
                if ($r->completionstate == 1 || $r->completionstate == 2) {
                    $completed++;
                }
            }
        } catch (\Exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        
        $incomplete = $total - $completed;
        if ($total == 0) {
            $labels = ['No trackable activities'];
            $data = [0];
        } else {
            $labels = ['Completed', 'Remaining'];
            $data = [$completed, $incomplete];
        }
        
        return self::format_chart_data($labels, $data, $type, 'Activities');
    }

    private static function get_student_activity_scores($courseid, $studentid, $type, $show_average = false) {
        global $DB;
        
        $labels = [];
        $data = [];
        $class_data = [];
        
        try {
            $sql = "SELECT gi.id, gi.itemname, gi.itemmodule, gi.grademax, (gg.finalgrade / gi.grademax) * 100 AS percent
                    FROM {grade_items} gi
                    JOIN {grade_grades} gg ON gg.itemid = gi.id
                    WHERE gi.courseid = :course AND gg.userid = :userid 
                      AND gi.itemtype = 'mod' AND gi.itemmodule IN ('assign', 'quiz') 
                      AND gg.finalgrade IS NOT NULL AND gi.grademax > 0";
                      
            $records = $DB->get_records_sql($sql, ['course' => $courseid, 'userid' => $studentid]);
            foreach ($records as $r) {
                $name = !empty($r->itemname) ? $r->itemname : ucfirst($r->itemmodule) . ' Activity';
                $name = format_string($name);
                $labels[] = explode("\n", wordwrap($name, 15, "\n"));
                $data[] = round($r->percent, 1);
                
                if ($show_average) {
                    $avg_sql = "SELECT AVG((finalgrade / :gmax) * 100) AS avg_percent 
                                FROM {grade_grades} 
                                WHERE itemid = :itemid AND finalgrade IS NOT NULL";
                    $avg_record = $DB->get_record_sql($avg_sql, ['gmax' => $r->grademax, 'itemid' => $r->id]);
                    $class_data[] = $avg_record && $avg_record->avg_percent !== null ? round($avg_record->avg_percent, 1) : 0;
                }
            }
        } catch (\Exception $e) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        
        if (empty($labels)) {
            $labels[] = 'No Grades Found';
            $data[] = 0;
            if ($show_average) {
                $class_data[] = 0;
            }
        }
        
        $chart_data = self::format_chart_data($labels, $data, $type, 'Score (%)');
        
        if ($show_average && in_array($type, ['bar', 'line', 'area'])) {
            $chart_data['series'][] = [
                'name' => 'Class Average',
                'data' => $class_data,
                'type' => 'line'
            ];
        }
        
        return $chart_data;
    }

    private static function format_chart_data($labels, $data, $type, $seriesName) {
        if ($type === 'pie') {
            return [
                'series' => array_values($data),
                'labels' => $labels,
                'chart' => ['type' => 'pie', 'width' => '100%']
            ];
        } else if ($type === 'radial') {
            $total = array_sum($data);
            $percentages = [];
            foreach ($data as $d) {
                $percentages[] = $total > 0 ? round(($d / $total) * 100) : 0;
            }
            // For radial with one main item (like progress), just return first percentage.
            if (count($percentages) == 2 && ($labels[0] == 'Completed' || $labels[0] == 'Completed Activities')) {
                return [
                    'series' => [$percentages[0]],
                    'labels' => ['Progress'],
                    'chart' => ['type' => 'radialBar', 'height' => 350]
                ];
            }
            return [
                'series' => $percentages,
                'labels' => $labels,
                'chart' => ['type' => 'radialBar', 'height' => 350]
            ];
        } else {
            return [
                'series' => [['name' => $seriesName, 'data' => array_values($data)]],
                'xaxis' => ['categories' => $labels],
                'chart' => ['type' => 'bar', 'height' => 350]
            ];
        }
    }
}
