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

use PHPUnit\Framework\Attributes\CoversClass;
use tool_opensearch\local\stats;

/**
 * Tests of the metrics and of the privacy provider.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
#[CoversClass(privacy\provider::class)]
final class hook_callbacks_test extends \advanced_testcase {
    /**
     * Metrics for tool_prometheus, when it is installed.
     */
    public function test_collect_metrics(): void {
        if (!class_exists(\tool_prometheus\hook\collect_metrics::class)) {
            $this->markTestSkipped('tool_prometheus is not installed');
        }
        $this->resetAfterTest();
        $this->mock_clock_with_frozen(1000);
        set_config('enabled', 1, 'tool_opensearch');
        set_config('hosts', 'http://os:9200', 'tool_opensearch');
        set_config('up', 1, 'tool_opensearch');
        stats::update('events', ['queued' => 5, 'oldest' => 900, 'lastsuccess' => 990], ['sent' => 7, 'dropped' => 2]);

        $hook = new \tool_prometheus\hook\collect_metrics();
        hook_callbacks::collect_metrics($hook);
        $values = [];
        foreach ($hook->get_metrics() as $metric) {
            foreach ($metric->get_samples() as $sample) {
                $values[$metric->name . json_encode($sample['labels'])] = $sample['value'];
            }
        }
        $this->assertSame(1, $values['moodle_tool_opensearch_up[]']);
        $this->assertSame(5, $values['moodle_tool_opensearch_queue_documents{"stream":"events"}']);
        $this->assertSame(100, $values['moodle_tool_opensearch_queue_oldest_age_seconds{"stream":"events"}']);
        $this->assertSame(7, $values['moodle_tool_opensearch_documents_sent_total{"stream":"events"}']);
        $this->assertSame(2, $values['moodle_tool_opensearch_documents_dropped_total{"stream":"events"}']);
    }

    /**
     * Privacy metadata: the queue and the external location.
     */
    public function test_privacy_metadata(): void {
        $collection = privacy\provider::get_metadata(new \core_privacy\local\metadata\collection('tool_opensearch'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertSame(['tool_opensearch_queue', 'opensearch'], $names);
    }
}
