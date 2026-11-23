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
 * A stream declared by a plugin: becomes the data stream "<prefix>-<name>".
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stream {
    /** Names reserved by tool_opensearch. */
    public const RESERVED = ['selftest'];

    /** @var string Plugin that owns the stream. */
    public readonly string $component;

    /** @var string Stream name. */
    public readonly string $name;

    /** @var array Mapping of the document fields. */
    public readonly array $properties;

    /** @var int Default retention, in days. */
    public readonly int $retentiondays;

    /** @var string Short description. */
    public readonly string $description;

    /**
     * Constructor.
     *
     * @param string $component Plugin that owns the stream.
     * @param string $name Stream name.
     * @param array $properties Mapping of the document fields.
     * @param int $retentiondays Default retention, in days.
     * @param string $description Short description.
     */
    public function __construct(string $component, string $name, array $properties, int $retentiondays, string $description = '') {
        $this->component = $component;
        $this->name = $name;
        $this->properties = $properties;
        $this->retentiondays = $retentiondays;
        $this->description = $description;
    }

    /**
     * Whether a stream name is valid and not reserved.
     *
     * @param string $name Name.
     * @return bool
     */
    public static function valid_name(string $name): bool {
        return (bool) preg_match('/^[a-z][a-z0-9_]{0,39}$/', $name) && !in_array($name, self::RESERVED, true);
    }

    /**
     * Retention in days: the administrator's setting, or the default of the producer.
     *
     * @return int
     */
    public function retention(): int {
        $days = (int) get_config('tool_opensearch', 'retention_' . $this->name);
        return $days > 0 ? $days : $this->retentiondays;
    }

    /**
     * Full mapping properties, with the fields added by tool_opensearch.
     *
     * @return array
     */
    public function mapping_properties(): array {
        return [
            '@timestamp' => ['type' => 'date'],
            'moodle' => ['properties' => ['site' => ['type' => 'keyword']]],
        ] + $this->properties;
    }
}
