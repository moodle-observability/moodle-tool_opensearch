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
 * The cluster could not be reached or refused the request.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_exception extends \moodle_exception {
    /** @var bool Whether trying again later may work (timeout, connection refused, 429, 5xx). */
    public bool $transient;

    /** @var int HTTP status (0 when there was no response). */
    public int $status;

    /**
     * Constructor.
     *
     * @param string $message Clear message, without secrets.
     * @param bool $transient Whether trying again later may work.
     * @param int $status HTTP status (0 when there was no response).
     */
    public function __construct(string $message, bool $transient, int $status = 0) {
        $this->transient = $transient;
        $this->status = $status;
        parent::__construct('error_client', 'tool_opensearch', '', $message);
    }

    /**
     * Message without the language string wrapper.
     *
     * @return string
     */
    public function get_reason(): string {
        return (string) $this->a;
    }
}
