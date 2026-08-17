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
 * Privacy provider tests.
 *
 * @package    block_performance_graphs
 * @copyright  2026 Ahmet Bülbül
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_performance_graphs\privacy\provider
 */
final class privacy_provider_test extends \advanced_testcase {
    /** The null-provider reason must resolve to a real language string. */
    public function test_reason_string_exists(): void {
        $reason = \block_performance_graphs\privacy\provider::get_reason();
        $this->assertSame('privacy:metadata', $reason);
        $this->assertStringNotContainsString('[[', get_string($reason, 'block_performance_graphs'));
    }
}
