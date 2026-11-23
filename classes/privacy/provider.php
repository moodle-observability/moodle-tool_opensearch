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

namespace tool_opensearch\privacy;

use core_privacy\local\metadata\collection;

/**
 * Privacy provider.
 *
 * tool_opensearch only carries documents produced by other plugins: they wait in the queue for a short time and are
 * sent to OpenSearch. Each producer declares the fields it sends and answers the export and deletion requests,
 * using \tool_opensearch\api::search() and \tool_opensearch\api::delete_by_query().
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements \core_privacy\local\metadata\provider {
    #[\Override]
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('tool_opensearch_queue', [
            'stream' => 'privacy:metadata:tool_opensearch_queue:stream',
            'payload' => 'privacy:metadata:tool_opensearch_queue:payload',
            'timecreated' => 'privacy:metadata:tool_opensearch_queue:timecreated',
        ], 'privacy:metadata:tool_opensearch_queue');
        $collection->add_external_location_link('opensearch', [
            'payload' => 'privacy:metadata:opensearch:payload',
        ], 'privacy:metadata:opensearch');
        return $collection;
    }
}
