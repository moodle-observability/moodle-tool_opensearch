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
use tool_opensearch\local\schema_manager;

/**
 * Sends the index templates and the retention policies again, also restoring ones removed in the cluster.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class sync_schema extends \core\task\scheduled_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_sync_schema', 'tool_opensearch');
    }

    #[\Override]
    public function execute(): void {
        $config = config::load();
        if (!$config->enabled) {
            mtrace('tool_opensearch is disabled.');
            return;
        }
        foreach ((new schema_manager($config))->sync(true) as $stream => $result) {
            mtrace("{$stream}: {$result}");
        }
    }
}
