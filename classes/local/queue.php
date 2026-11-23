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
 * Buffer of the request and queue in the database: documents wait there until the ship_queue task sends them.
 *
 * Nothing here talks to OpenSearch, so a slow or unreachable cluster never delays a page.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class queue {
    /** Documents kept in memory before writing to the queue. */
    public const BUFFER_DOCS = 500;

    /** Bytes kept in memory before writing to the queue. */
    public const BUFFER_BYTES = 1048576;

    /** @var array Buffered documents: [stream, JSON]. */
    private static array $buffer = [];

    /** @var int Bytes in the buffer. */
    private static int $bytes = 0;

    /** @var int Documents written to the queue by this request (not counted yet by the task). */
    private static int $pending = 0;

    /** @var bool Whether the shutdown flush is registered. */
    private static bool $registered = false;

    /**
     * Adds an encoded document to the buffer; written to the queue at the end of the request or when the buffer fills.
     *
     * @param string $stream Stream name.
     * @param string $json Document encoded as JSON.
     * @return void
     */
    public static function add(string $stream, string $json): void {
        self::$buffer[] = [$stream, $json];
        self::$bytes += strlen($json);
        if (!self::$registered) {
            \core_shutdown_manager::register_function([self::class, 'flush']);
            self::$registered = true;
        }
        if (count(self::$buffer) >= self::BUFFER_DOCS || self::$bytes >= self::BUFFER_BYTES) {
            self::flush();
        }
    }

    /**
     * Writes the buffer to the queue, dropping the documents above the queue limit.
     *
     * @return void
     */
    public static function flush(): void {
        global $DB;
        if (!self::$buffer) {
            return;
        }
        $buffer = self::$buffer;
        self::$buffer = [];
        self::$bytes = 0;

        try {
            $config = config::load();
            $now = \core\di::get(\core\clock::class)->time();
            $room = max(0, $config->queuemax - stats::queued_total() - self::$pending);
            $rows = [];
            $dropped = [];
            foreach ($buffer as [$stream, $json]) {
                if (count($rows) >= $room) {
                    $dropped[$stream] = ($dropped[$stream] ?? 0) + 1;
                    continue;
                }
                $rows[] = ['stream' => $stream, 'payload' => $json, 'attempts' => 0, 'nextattempt' => 0, 'timecreated' => $now];
            }
            if ($rows) {
                $DB->insert_records('tool_opensearch_queue', $rows);
                self::$pending += count($rows);
            }
            foreach ($dropped as $stream => $count) {
                stats::update($stream, ['lasterror' => get_string('error_queuefull', 'tool_opensearch'),
                    'lasterrortime' => $now], ['dropped' => $count]);
            }
        } catch (\Throwable $e) {
            // The page must not fail because of the queue.
            debugging('tool_opensearch: could not write to the queue: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Documents still in the buffer of this request.
     *
     * @return int
     */
    public static function buffered(): int {
        return count(self::$buffer);
    }

    /**
     * Empties the buffer without writing it (tests).
     *
     * @return void
     */
    public static function reset(): void {
        self::$buffer = [];
        self::$bytes = 0;
        self::$pending = 0;
    }
}
