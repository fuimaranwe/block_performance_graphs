<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_performance_graphs;

defined('MOODLE_INTERNAL') || die();

/**
 * Provides authorised, presentation-ready performance data.
 *
 * @package    block_performance_graphs
 * @copyright  2026 Ahmet Bülbül
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_provider {
    /** Supported chart types. */
    private const CHART_TYPES = ['bar', 'pie', 'radial', 'line', 'area'];

    /**
     * Return courses that the current user can access from this page.
     *
     * @return array<int, string>
     */
    public static function get_available_courses(): array {
        global $PAGE, $SITE, $USER;

        $courses = [];
        if ((int) $PAGE->course->id === (int) $SITE->id) {
            $usercourses = enrol_get_users_courses($USER->id, true, 'id, shortname');
            foreach ($usercourses as $course) {
                if (!self::can_access_course((int) $course->id)) {
                    continue;
                }
                $context = \context_course::instance($course->id);
                $courses[$course->id] = format_string($course->shortname, true, [
                    'context' => $context,
                    'escape' => false,
                ]);
            }
        } elseif (self::can_access_course((int) $PAGE->course->id)) {
            $context = \context_course::instance($PAGE->course->id);
            $courses[$PAGE->course->id] = format_string($PAGE->course->shortname, true, [
                'context' => $context,
                'escape' => false,
            ]);
        }

        return $courses;
    }

    /**
     * Return students whose grades the current user may view in a course.
     *
     * Separate-group restrictions are applied to users without accessallgroups.
     *
     * @param int $courseid Course ID.
     * @return array<int, string>
     */
    public static function get_available_students(int $courseid): array {
        global $DB, $USER;

        if (!self::can_access_course($courseid)) {
            return [];
        }

        $context = \context_course::instance($courseid);
        if (!has_capability('moodle/grade:viewall', $context)) {
            if (is_enrolled($context, $USER, '', true) && has_capability('moodle/grade:view', $context)) {
                return [$USER->id => fullname($USER)];
            }
            return [];
        }

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $allowedids = null;
        if (groups_get_course_groupmode($course) === SEPARATEGROUPS &&
                !has_capability('moodle/site:accessallgroups', $context)) {
            $usergroups = groups_get_user_groups($courseid, $USER->id)[0] ?? [];
            $allowedids = [];
            foreach ($usergroups as $groupid) {
                $allowedids += groups_get_members($groupid, 'u.id');
            }
            $allowedids = array_keys($allowedids);
        }

        $enrolled = get_enrolled_users(
            $context,
            '',
            0,
            'u.id, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename',
            'u.lastname, u.firstname',
            0,
            0,
            true
        );
        $students = [];
        foreach ($enrolled as $user) {
            if ($allowedids !== null && !in_array((int) $user->id, $allowedids, true)) {
                continue;
            }
            if (!has_capability('moodle/grade:view', $context, $user) ||
                    has_capability('moodle/grade:viewall', $context, $user)) {
                continue;
            }
            $students[$user->id] = fullname($user);
        }

        return $students;
    }

    /**
     * Build chart data for the current user.
     *
     * @param ?object $config Block configuration.
     * @return array<string, mixed>
     */
    public static function get_performance_data(?object $config): array {
        global $DB, $USER;

        $type = self::normalise_chart_type($config->chart_type ?? 'bar');
        if (!$config || empty($config->course)) {
            return self::decorate_chart(self::get_empty_chart($type), $type);
        }

        $courseid = (int) $config->course;
        if (!self::can_access_course($courseid)) {
            return self::decorate_chart(self::get_empty_chart($type), $type);
        }

        $course = $DB->get_record('course', ['id' => $courseid], 'id, shortname', MUST_EXIST);
        $context = \context_course::instance($courseid);
        $coursename = format_string($course->shortname, true, ['context' => $context, 'escape' => false]);
        $mode = ($config->target_mode ?? 'class') === 'student' ? 'student' : 'class';
        $metric = '';
        $title = '';
        $chartdata = self::get_empty_chart($type);

        if ($mode === 'class') {
            if (!has_capability('moodle/grade:viewall', $context)) {
                return self::decorate_chart($chartdata, $type);
            }
            $studentids = array_keys(self::get_available_students($courseid));
            $metric = in_array(($config->class_metric ?? ''), ['completion', 'quiz_averages'], true)
                ? $config->class_metric : 'completion';
            $title = $coursename;
            if ($metric === 'completion') {
                $chartdata = self::get_class_completion_data($courseid, $studentids, $type);
            } else {
                $chartdata = self::get_class_quiz_averages($courseid, $studentids, $type);
            }
        } else {
            $metric = in_array(($config->student_metric ?? ''), ['activity_completion', 'all_scores'], true)
                ? $config->student_metric : 'activity_completion';
            $studentid = !empty($config->student) ? (int) $config->student : (int) $USER->id;
            $availablestudents = self::get_available_students($courseid);
            if (!array_key_exists($studentid, $availablestudents)) {
                return self::decorate_chart($chartdata, $type);
            }

            $user = $DB->get_record(
                'user',
                ['id' => $studentid],
                'id, firstname, lastname, firstnamephonetic, lastnamephonetic, middlename, alternatename',
                MUST_EXIST
            );
            $title = fullname($user) . ' - ' . $coursename;
            if ($metric === 'activity_completion') {
                $chartdata = self::get_student_completion_progress($courseid, $studentid, $type);
            } else {
                $showaverage = !empty($config->class_average) && has_capability('moodle/grade:viewall', $context);
                $chartdata = self::get_student_activity_scores($courseid, $studentid, $type, $showaverage);
            }
        }

        $chartdata['title'] = ['text' => $title];
        $color = self::normalise_color($config->chart_color ?? '#0f6cbf');
        $chartdata['colors'] = ($type === 'pie' || $type === 'radial')
            ? self::generate_color_palette($color, 5) : [$color, '#6f42c1'];

        if ($type === 'bar' && !empty($config->enable_threshold)) {
            $threshold = max(0, min(100, (float) ($config->passing_grade ?? 50)));
            $chartdata['threshold'] = [
                'value' => $threshold,
                'fail_color' => '#dc3545',
                'pass_color' => '#198754',
            ];
        }

        if (!empty($chartdata['series'])) {
            if ($metric === 'completion' && $mode === 'class') {
                self::add_completion_callout($chartdata, get_string('classcompletionrate', 'block_performance_graphs'));
            } elseif ($metric === 'activity_completion' && $mode === 'student') {
                self::add_completion_callout($chartdata, get_string('activitiescompleted', 'block_performance_graphs'));
            }
        }

        return self::decorate_chart($chartdata, $type);
    }

    /**
     * Add a correctly calculated completion callout.
     *
     * @param array $chartdata Chart data, modified in place.
     * @param string $label Callout label.
     */
    private static function add_completion_callout(array &$chartdata, string $label): void {
        $charttype = $chartdata['chart']['type'] ?? 'bar';
        if ($charttype === 'radialBar') {
            $percentage = (float) ($chartdata['series'][0] ?? 0);
        } elseif ($charttype === 'pie') {
            $completed = (float) ($chartdata['series'][0] ?? 0);
            $total = array_sum($chartdata['series']);
            $percentage = $total > 0 ? ($completed / $total) * 100 : 0;
        } else {
            $completed = (float) ($chartdata['series'][0]['data'][0] ?? 0);
            $remaining = (float) ($chartdata['series'][0]['data'][1] ?? 0);
            $percentage = ($completed + $remaining) > 0 ? ($completed / ($completed + $remaining)) * 100 : 0;
        }
        $chartdata['_stat_callout'] = ['value' => round($percentage) . '%', 'label' => $label];
    }

    /**
     * Add shared, localised presentation metadata.
     *
     * @param array $chartdata Chart data.
     * @param string $type Requested chart type.
     * @return array
     */
    private static function decorate_chart(array $chartdata, string $type): array {
        $chartdata['no_data_text'] = $chartdata['no_data_text'] ?? get_string('nodata', 'block_performance_graphs');
        $chartdata['table_summary'] = get_string('viewchartdata', 'block_performance_graphs');
        $chartdata['table_caption'] = get_string('chartdata', 'block_performance_graphs');
        $chartdata['category_label'] = get_string('category', 'block_performance_graphs');
        $chartdata['value_label'] = get_string('value', 'block_performance_graphs');
        $chartdata['chart_aria_label'] = get_string('chartarialabel', 'block_performance_graphs',
            get_string('type_' . $type, 'block_performance_graphs'));
        return $chartdata;
    }

    /** Check whether the current user can access a course. */
    private static function can_access_course(int $courseid): bool {
        global $DB, $USER;

        $course = $DB->get_record('course', ['id' => $courseid]);
        if (!$course) {
            return false;
        }
        return \can_access_course($course, $USER, '', true);
    }

    /** Return a safe chart type. */
    private static function normalise_chart_type(string $type): string {
        return in_array($type, self::CHART_TYPES, true) ? $type : 'bar';
    }

    /** Return a safe CSS colour. */
    private static function normalise_color(string $color): string {
        return preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#0f6cbf';
    }

    /** Generate related chart colours. */
    private static function generate_color_palette(string $basecolor, int $count): array {
        $colors = [$basecolor];
        $hex = ltrim($basecolor, '#');
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));
        for ($index = 1; $index < $count; $index++) {
            $factor = $index / ($count + 1);
            $colors[] = sprintf(
                '#%02x%02x%02x',
                (int) round($red + (255 - $red) * $factor),
                (int) round($green + (255 - $green) * $factor),
                (int) round($blue + (255 - $blue) * $factor)
            );
        }
        return $colors;
    }

    /** Return an empty chart structure. */
    private static function get_empty_chart(string $type): array {
        if ($type === 'pie') {
            return ['series' => [], 'labels' => [], 'chart' => ['type' => 'pie']];
        }
        if ($type === 'radial') {
            return ['series' => [], 'labels' => [], 'chart' => ['type' => 'radialBar']];
        }
        return ['series' => [], 'xaxis' => ['categories' => []], 'chart' => ['type' => $type]];
    }

    /** Class completion, including students without a course_completions row. */
    private static function get_class_completion_data(int $courseid, array $studentids, string $type): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'id, enablecompletion', MUST_EXIST);
        if (empty($course->enablecompletion)) {
            $chart = self::get_empty_chart($type);
            $chart['no_data_text'] = get_string('completionnotenabled', 'block_performance_graphs');
            return $chart;
        }
        if (!$studentids) {
            return self::format_chart_data([], [], $type, get_string('students'), false);
        }
        [$insql, $params] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'student');
        $params['courseid'] = $courseid;
        $records = $DB->get_records_sql(
            "SELECT userid, timecompleted
               FROM {course_completions}
              WHERE course = :courseid AND userid $insql",
            $params
        );
        $completed = 0;
        foreach ($studentids as $studentid) {
            if (!empty($records[$studentid]->timecompleted)) {
                $completed++;
            }
        }
        $data = [$completed, count($studentids) - $completed];
        return self::format_chart_data(
            [get_string('completed', 'block_performance_graphs'), get_string('inprogress', 'block_performance_graphs')],
            $data,
            $type,
            get_string('students'),
            false
        );
    }

    /** Class quiz averages for authorised, active students only. */
    private static function get_class_quiz_averages(int $courseid, array $studentids, string $type): array {
        global $DB;

        if (!$studentids) {
            return self::format_chart_data([], [], $type, get_string('averagescore', 'block_performance_graphs'), true);
        }
        [$insql, $params] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'student');
        $params['courseid'] = $courseid;
        $visibilitysql = self::get_grade_visibility_sql($params);
        $records = $DB->get_records_sql(
            "SELECT q.id, q.name, AVG((gg.finalgrade / gi.grademax) * 100) AS avgpercent
               FROM {quiz} q
               JOIN {grade_items} gi
                 ON gi.iteminstance = q.id AND gi.itemmodule = 'quiz' AND gi.itemtype = 'mod'
               JOIN {grade_grades} gg ON gg.itemid = gi.id
              WHERE q.course = :courseid
                AND gg.userid $insql
                AND gg.finalgrade IS NOT NULL
                AND gi.grademax > 0
                $visibilitysql
           GROUP BY q.id, q.name
           ORDER BY q.id",
            $params
        );
        $context = \context_course::instance($courseid);
        $labels = [];
        $data = [];
        foreach ($records as $record) {
            $labels[] = format_string($record->name, true, ['context' => $context, 'escape' => false]);
            $data[] = round((float) $record->avgpercent, 1);
        }
        return self::format_chart_data($labels, $data, $type, get_string('averagescore', 'block_performance_graphs'), true);
    }

    /** Student completion over modules actually visible to that student. */
    private static function get_student_completion_progress(int $courseid, int $studentid, string $type): array {
        global $DB;

        $modinfo = get_fast_modinfo($courseid, $studentid);
        $moduleids = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->deletioninprogress && $cm->uservisible && $cm->completion != COMPLETION_TRACKING_NONE) {
                $moduleids[] = $cm->id;
            }
        }
        if (!$moduleids) {
            $chart = self::format_chart_data([], [], $type, get_string('activities', 'block_performance_graphs'), false);
            $chart['no_data_text'] = get_string('notrackableactivities', 'block_performance_graphs');
            return $chart;
        }
        [$insql, $params] = $DB->get_in_or_equal($moduleids, SQL_PARAMS_NAMED, 'cm');
        $params['studentid'] = $studentid;
        $records = $DB->get_records_sql(
            "SELECT coursemoduleid, completionstate
               FROM {course_modules_completion}
              WHERE userid = :studentid AND coursemoduleid $insql",
            $params
        );
        $completed = 0;
        foreach ($moduleids as $moduleid) {
            if (isset($records[$moduleid]) && in_array((int) $records[$moduleid]->completionstate, [
                    COMPLETION_COMPLETE,
                    COMPLETION_COMPLETE_PASS,
                    COMPLETION_COMPLETE_FAIL,
                ], true)) {
                $completed++;
            }
        }
        return self::format_chart_data(
            [get_string('completed', 'block_performance_graphs'), get_string('remaining', 'block_performance_graphs')],
            [$completed, count($moduleids) - $completed],
            $type,
            get_string('activities', 'block_performance_graphs'),
            false
        );
    }

    /** Student assignment and quiz scores, respecting hidden grades. */
    private static function get_student_activity_scores(
        int $courseid,
        int $studentid,
        string $type,
        bool $showaverage
    ): array {
        global $DB;

        $params = ['courseid' => $courseid, 'studentid' => $studentid];
        $visibilitysql = self::get_grade_visibility_sql($params);
        $records = $DB->get_records_sql(
            "SELECT gi.id, gi.itemname, gi.itemmodule, gi.grademax,
                    (gg.finalgrade / gi.grademax) * 100 AS percent
               FROM {grade_items} gi
               JOIN {grade_grades} gg ON gg.itemid = gi.id
              WHERE gi.courseid = :courseid
                AND gg.userid = :studentid
                AND gi.itemtype = 'mod'
                AND gi.itemmodule IN ('assign', 'quiz')
                AND gg.finalgrade IS NOT NULL
                AND gi.grademax > 0
                $visibilitysql
           ORDER BY gi.sortorder, gi.id",
            $params
        );
        $context = \context_course::instance($courseid);
        $labels = [];
        $data = [];
        foreach ($records as $record) {
            $fallback = get_string('activityfallback', 'block_performance_graphs', ucfirst($record->itemmodule));
            $labels[] = format_string($record->itemname ?: $fallback, true, [
                'context' => $context,
                'escape' => false,
            ]);
            $data[] = round((float) $record->percent, 1);
        }

        $chartdata = self::format_chart_data($labels, $data, $type, get_string('score', 'block_performance_graphs'), true);
        if ($showaverage && $records && in_array($type, ['bar', 'line', 'area'], true)) {
            $averages = self::get_item_averages($courseid, array_keys($records));
            $classdata = [];
            foreach ($records as $record) {
                $classdata[] = isset($averages[$record->id])
                    ? round(((float) $averages[$record->id] / (float) $record->grademax) * 100, 1) : null;
            }
            $chartdata['series'][] = [
                'name' => get_string('classaverage', 'block_performance_graphs'),
                'data' => $classdata,
                'type' => 'line',
            ];
        }
        return $chartdata;
    }

    /** Return averages for grade items across authorised active students. */
    private static function get_item_averages(int $courseid, array $itemids): array {
        global $DB;

        $studentids = array_keys(self::get_available_students($courseid));
        if (!$studentids || !$itemids) {
            return [];
        }
        [$itemsql, $itemparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'item');
        [$studentsql, $studentparams] = $DB->get_in_or_equal($studentids, SQL_PARAMS_NAMED, 'avgstudent');
        $params = ['courseid' => $courseid] + $itemparams + $studentparams;
        $visibilitysql = self::get_grade_visibility_sql($params);
        $records = $DB->get_records_sql(
            "SELECT gg.itemid, AVG(gg.finalgrade) AS averagegrade
               FROM {grade_grades} gg
               JOIN {grade_items} gi ON gi.id = gg.itemid
              WHERE gg.itemid $itemsql
                AND gg.userid $studentsql
                AND gi.courseid = :courseid
                AND gg.finalgrade IS NOT NULL
                $visibilitysql
           GROUP BY gg.itemid",
            $params
        );
        $averages = [];
        foreach ($records as $record) {
            $averages[$record->itemid] = $record->averagegrade;
        }
        return $averages;
    }

    /**
     * Return SQL which excludes grades hidden from the current user.
     *
     * @param array $params Query parameters, modified in place.
     * @return string
     */
    private static function get_grade_visibility_sql(array &$params): string {
        global $USER;

        // Courseid is always supplied by callers.
        $context = \context_course::instance((int) $params['courseid']);
        if (has_capability('moodle/grade:viewhidden', $context, $USER)) {
            return '';
        }
        $params['itemvisibleafter'] = time();
        $params['gradevisibleafter'] = $params['itemvisibleafter'];
        return "AND (gi.hidden = 0 OR (gi.hidden > 1 AND gi.hidden <= :itemvisibleafter))
                AND (gg.hidden = 0 OR (gg.hidden > 1 AND gg.hidden <= :gradevisibleafter))";
    }

    /** Format values for the selected chart type. */
    private static function format_chart_data(
        array $labels,
        array $data,
        string $type,
        string $seriesname,
        bool $valuesarepercent
    ): array {
        if (!$labels || !$data) {
            return self::get_empty_chart($type);
        }
        if ($type === 'pie') {
            return ['series' => array_values($data), 'labels' => $labels, 'series_name' => $seriesname,
                'chart' => ['type' => 'pie']];
        }
        if ($type === 'radial') {
            if ($valuesarepercent) {
                $percentages = array_map(static function($value): float {
                    return max(0, min(100, (float) $value));
                }, $data);
            } else {
                $total = array_sum($data);
                $percentages = array_map(static function($value) use ($total): float {
                    return $total > 0 ? round(((float) $value / $total) * 100, 1) : 0;
                }, $data);
                if (count($percentages) === 2) {
                    $percentages = [$percentages[0]];
                    $labels = [get_string('progress', 'block_performance_graphs')];
                }
            }
            return ['series' => $percentages, 'labels' => $labels, 'series_name' => $seriesname,
                'chart' => ['type' => 'radialBar']];
        }
        return [
            'series' => [['name' => $seriesname, 'data' => array_values($data)]],
            'xaxis' => ['categories' => $labels],
            'chart' => ['type' => $type],
        ];
    }
}
