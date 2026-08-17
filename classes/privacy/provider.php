<?php
// This file is part of Moodle - http://moodle.org/.
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace block_performance_graphs\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for the Performance Graphs block.
 *
 * @package    block_performance_graphs
 * @copyright  2026 Ahmet Bülbül
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /** Return the language string explaining why this is a null provider. */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
