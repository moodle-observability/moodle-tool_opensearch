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

namespace tool_opensearch\task;

use tool_opensearch\local\config;
use tool_opensearch\local\shipper;

/**
 * Sends the queued documents to OpenSearch (_bulk), for at most 50 seconds.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class ship_queue extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_ship_queue', 'tool_opensearch');
    }

    #[\Override]
    public function execute(): void {
        $config = config::load();
        if (!$config->enabled) {
            mtrace('tool_opensearch is disabled.');
            return;
        }
        $totals = (new shipper($config))->run(50);
        mtrace(sprintf(
            'OpenSearch %s: %d sent, %d rejected, %d kept for a retry.',
            $totals['up'] ? 'up' : 'unreachable',
            $totals['sent'],
            $totals['failed'],
            $totals['retried']
        ));
    }
}
