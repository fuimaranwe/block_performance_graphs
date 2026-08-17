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
 * Regression tests for chart data formatting.
 *
 * @package    block_performance_graphs
 * @copyright  2026 Ahmet Bülbül
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_performance_graphs\data_provider
 */
final class data_provider_test extends \advanced_testcase {
    /** Line and area choices must survive data formatting. */
    public function test_cartesian_chart_type_is_preserved(): void {
        $method = new \ReflectionMethod(data_provider::class, 'format_chart_data');
        $method->setAccessible(true);

        foreach (['line', 'area'] as $type) {
            $result = $method->invoke(null, ['Quiz'], [75], $type, 'Score', true);
            $this->assertSame($type, $result['chart']['type']);
        }
    }

    /** Pie completion callouts must calculate a percentage rather than suffix a count. */
    public function test_pie_completion_callout_is_a_percentage(): void {
        $method = new \ReflectionMethod(data_provider::class, 'add_completion_callout');
        $method->setAccessible(true);
        $chartdata = [
            'chart' => ['type' => 'pie'],
            'series' => [3, 7],
        ];

        $arguments = [&$chartdata, 'Completed'];
        $method->invokeArgs(null, $arguments);

        $this->assertSame('30%', $chartdata['_stat_callout']['value']);
    }

    /** Radial score charts must retain every percentage for concentric rings. */
    public function test_radial_chart_preserves_all_percentages(): void {
        $method = new \ReflectionMethod(data_provider::class, 'format_chart_data');
        $method->setAccessible(true);

        $result = $method->invoke(null, ['Quiz', 'Assignment'], [75, 82.5], 'radial', 'Score', true);

        $this->assertSame('radialBar', $result['chart']['type']);
        $this->assertSame([75.0, 82.5], $result['series']);
        $this->assertSame(['Quiz', 'Assignment'], $result['labels']);
    }
}
