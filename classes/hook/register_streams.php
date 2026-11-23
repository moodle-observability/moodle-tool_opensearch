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

namespace tool_opensearch\hook;

use tool_opensearch\local\stream;

/**
 * Collects the streams (OpenSearch data streams) that plugins write to.
 *
 * Each stream becomes the data stream "<prefix>-<name>", with an index template built from the declared mapping
 * (strict: undeclared fields are rejected) and a retention policy. Documents are sent with
 * \tool_opensearch\api::index(). Callbacks must be fast and must not query external services.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\core\attribute\label('Allows plugins to declare the OpenSearch streams they send documents to.')]
#[\core\attribute\tags('opensearch', 'logs')]
final class register_streams {
    /** @var stream[] Streams by name. */
    private array $streams = [];

    /** @var string[] Problems found while registering. */
    private array $errors = [];

    /**
     * Declares a stream.
     *
     * @param string $component Frankenstyle name of the plugin that owns the stream.
     * @param string $name Stream name: lower case letters, digits and "_", starting with a letter (at most 40).
     * @param array $properties OpenSearch mapping of the document fields ("properties" of the mapping).
     *     "@timestamp" (date) and "moodle" (object with "site") are added by tool_opensearch.
     * @param int $retentiondays Default retention, in days; the administrator may change it.
     * @param string $description Short description shown to administrators.
     * @return void
     */
    public function add_stream(
        string $component,
        string $name,
        array $properties,
        int $retentiondays,
        string $description = ''
    ): void {
        if (!stream::valid_name($name)) {
            $this->errors[] = "{$component}: invalid stream name '{$name}'";
            return;
        }
        if (isset($this->streams[$name])) {
            $this->errors[] = "{$component}: stream '{$name}' is already declared by {$this->streams[$name]->component}";
            return;
        }
        $this->streams[$name] = new stream($component, $name, $properties, max(1, $retentiondays), $description);
    }

    /**
     * Declared streams, by name.
     *
     * @return stream[]
     */
    public function get_streams(): array {
        return $this->streams;
    }

    /**
     * Problems found while registering (invalid or duplicated names).
     *
     * @return string[]
     */
    public function get_errors(): array {
        return $this->errors;
    }
}
