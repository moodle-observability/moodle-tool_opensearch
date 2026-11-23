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

namespace tool_opensearch;

use tool_opensearch\local\stats;

/**
 * Hook callbacks.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class hook_callbacks {
    /**
     * Queue and sending metrics for tool_prometheus, read from {tool_opensearch_stream} only (no COUNT on the queue).
     *
     * @param \tool_prometheus\hook\collect_metrics $hook Hook.
     * @return void
     */
    public static function collect_metrics(\tool_prometheus\hook\collect_metrics $hook): void {
        if (!local\config::load()->enabled) {
            return;
        }
        $c = 'tool_opensearch';
        $hook->add_gauge(
            $c,
            'moodle_tool_opensearch_up',
            'Whether the last run of the ship_queue task reached OpenSearch (1 or 0).',
            (int) get_config('tool_opensearch', 'up')
        );
        $now = \core\di::get(\core\clock::class)->time();
        foreach (stats::all() as $name => $record) {
            $labels = ['stream' => $name];
            $hook->add_gauge(
                $c,
                'moodle_tool_opensearch_queue_documents',
                'Documents waiting in the queue, at the last run of the ship_queue task.',
                (int) $record->queued,
                $labels
            );
            $hook->add_gauge(
                $c,
                'moodle_tool_opensearch_queue_oldest_age_seconds',
                'Age of the oldest queued document, in seconds (0 if none).',
                $record->oldest ? max(0, $now - (int) $record->oldest) : 0,
                $labels
            );
            $hook->add_counter(
                $c,
                'moodle_tool_opensearch_documents_sent_total',
                'Documents accepted by OpenSearch.',
                (int) $record->sent,
                $labels
            );
            $hook->add_counter(
                $c,
                'moodle_tool_opensearch_documents_failed_total',
                'Documents rejected by OpenSearch (mapping errors) and discarded.',
                (int) $record->failed,
                $labels
            );
            $hook->add_counter(
                $c,
                'moodle_tool_opensearch_documents_dropped_total',
                'Documents discarded because the queue was full.',
                (int) $record->dropped,
                $labels
            );
            $hook->add_gauge(
                $c,
                'moodle_tool_opensearch_last_success_timestamp_seconds',
                'Unix time of the last document accepted by OpenSearch (0 if none).',
                (int) $record->lastsuccess,
                $labels
            );
        }
    }
}
