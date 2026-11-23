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

use tool_opensearch\hook\register_streams;

/**
 * Streams declared by the plugins, collected once per request.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class registry {
    /** @var stream[]|null Streams of this request. */
    private static ?array $streams = null;

    /**
     * Declared streams, by name.
     *
     * @return stream[]
     */
    public static function streams(): array {
        if (self::$streams === null) {
            $hook = new register_streams();
            // Each callback runs on its own: one broken plugin must not hide the streams of the others.
            foreach (\core\di::get(\core\hook\manager::class)->getListenersForEvent($hook) as $callback) {
                try {
                    $callback($hook);
                } catch (\Throwable $e) {
                    debugging('tool_opensearch: a register_streams callback failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                }
            }
            foreach ($hook->get_errors() as $error) {
                debugging('tool_opensearch: ' . $error, DEBUG_DEVELOPER);
            }
            self::$streams = $hook->get_streams();
        }
        return self::$streams;
    }

    /**
     * A declared stream, or null.
     *
     * @param string $name Stream name.
     * @return stream|null
     */
    public static function get(string $name): ?stream {
        return self::streams()[$name] ?? null;
    }

    /**
     * Forgets the streams of this request (tests, and after the hook callbacks change).
     *
     * @return void
     */
    public static function reset(): void {
        self::$streams = null;
    }
}
