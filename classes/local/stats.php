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

namespace tool_opensearch\local;

/**
 * State and counters of each stream ({tool_opensearch_stream}), read by the metrics without touching the queue.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stats {
    /** Counters that only grow. */
    public const COUNTERS = ['sent', 'failed', 'dropped'];

    /**
     * Record of a stream, created when missing.
     *
     * @param string $stream Stream name.
     * @return \stdClass
     */
    public static function get(string $stream): \stdClass {
        global $DB;
        $record = $DB->get_record('tool_opensearch_stream', ['stream' => $stream]);
        if ($record) {
            return $record;
        }
        $record = (object) [
            'stream' => $stream, 'queued' => 0, 'oldest' => 0, 'sent' => 0, 'failed' => 0, 'dropped' => 0,
            'lastsuccess' => 0, 'lasterror' => null, 'lasterrortime' => 0, 'schemahash' => null,
            'timemodified' => \core\di::get(\core\clock::class)->time(),
        ];
        try {
            $record->id = $DB->insert_record('tool_opensearch_stream', $record);
        } catch (\dml_write_exception $e) {
            // Created at the same time by another process.
            $record = $DB->get_record('tool_opensearch_stream', ['stream' => $stream], '*', MUST_EXIST);
        }
        return $record;
    }

    /**
     * Updates fields of a stream; counters in $increments are added to the stored values.
     *
     * @param string $stream Stream name.
     * @param array $fields Fields to set (name => value).
     * @param array $increments Counters to add to (sent, failed, dropped => amount).
     * @return void
     */
    public static function update(string $stream, array $fields, array $increments = []): void {
        global $DB;
        $record = self::get($stream);
        foreach ($fields as $name => $value) {
            $record->$name = $value;
        }
        foreach ($increments as $name => $amount) {
            if (in_array($name, self::COUNTERS, true)) {
                $record->$name = (int) $record->$name + (int) $amount;
            }
        }
        if (isset($fields['lasterror'])) {
            $record->lasterror = \core_text::substr((string) $fields['lasterror'], 0, 1000);
        }
        $record->timemodified = \core\di::get(\core\clock::class)->time();
        $DB->update_record('tool_opensearch_stream', $record);
    }

    /**
     * Records of every stream that has state, by name.
     *
     * @return \stdClass[]
     */
    public static function all(): array {
        global $DB;
        $records = [];
        foreach ($DB->get_records('tool_opensearch_stream', null, 'stream') as $record) {
            $records[$record->stream] = $record;
        }
        return $records;
    }

    /**
     * Documents in the queue, as counted by the last sending (cheap: no COUNT on the queue).
     *
     * @return int
     */
    public static function queued_total(): int {
        global $DB;
        return (int) $DB->get_field_sql('SELECT COALESCE(SUM(queued), 0) FROM {tool_opensearch_stream}');
    }
}
