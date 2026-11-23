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

namespace tool_opensearch\output;

use tool_opensearch\local\config;
use tool_opensearch\local\registry;
use tool_opensearch\local\stats;

/**
 * Connection test page: configuration summary, check results and state of each stream.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class test_page implements \core\output\renderable, \core\output\templatable {
    /** @var config Configuration. */
    private config $config;

    /** @var array|null Check results, or null when the test did not run. */
    private ?array $checks;

    /**
     * Constructor.
     *
     * @param config $config Configuration.
     * @param array|null $checks Check results, or null when the test did not run.
     */
    public function __construct(config $config, ?array $checks) {
        $this->config = $config;
        $this->checks = $checks;
    }

    #[\Override]
    public function export_for_template(\core\output\renderer_base $output): array {
        $stats = stats::all();
        $streams = [];
        foreach (array_unique(array_merge(array_keys(registry::streams()), array_keys($stats))) as $name) {
            $record = $stats[$name] ?? null;
            $stream = registry::get($name);
            $streams[] = [
                'name' => $name,
                'datastream' => $this->config->data_stream($name),
                'component' => $stream ? $stream->component : get_string('streamunknown', 'tool_opensearch'),
                'retention' => $stream ? $stream->retention() : '-',
                'queued' => $record ? (int) $record->queued : 0,
                'sent' => $record ? (int) $record->sent : 0,
                'failed' => $record ? (int) $record->failed : 0,
                'dropped' => $record ? (int) $record->dropped : 0,
                'lastsuccess' => $record && $record->lastsuccess ? userdate($record->lastsuccess) : '-',
                'lasterror' => $record && $record->lasterror ? $record->lasterror : '',
                'lasterrortime' => $record && $record->lasterrortime ? userdate($record->lasterrortime) : '',
            ];
        }
        return [
            'enabled' => $this->config->enabled,
            'hosts' => implode(', ', $this->config->hosts) ?: '-',
            'auth' => get_string('auth_' . $this->config->auth, 'tool_opensearch'),
            'prefix' => $this->config->prefix,
            'configerror' => $this->config->error,
            'ran' => $this->checks !== null,
            'checks' => $this->checks ?? [],
            'passed' => $this->checks !== null && !in_array(false, array_column($this->checks, 'ok'), true),
            'runurl' => (new \moodle_url('/admin/tool/opensearch/test.php', ['run' => 1, 'sesskey' => sesskey()]))->out(false),
            'settingsurl' => (new \moodle_url('/admin/settings.php', ['section' => 'tool_opensearch']))->out(false),
            'streams' => $streams,
            'hasstreams' => $streams !== [],
        ];
    }
}
