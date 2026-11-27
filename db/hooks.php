<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Hook callbacks.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    // Queue and sending metrics when tool_prometheus is installed (ignored otherwise).
    [
        'hook' => \tool_prometheus\hook\collect_metrics::class,
        'callback' => [\tool_opensearch\hook_callbacks::class, 'collect_metrics'],
    ],
    // The "alerts" stream: tool_taskmonitor alerts kept in OpenSearch (ignored without tool_taskmonitor).
    [
        'hook' => \tool_opensearch\hook\register_streams::class,
        'callback' => [\tool_opensearch\hook_callbacks::class, 'register_streams'],
    ],
    [
        'hook' => \tool_taskmonitor\hook\alert_raised::class,
        'callback' => [\tool_opensearch\hook_callbacks::class, 'alert_raised'],
    ],
];
