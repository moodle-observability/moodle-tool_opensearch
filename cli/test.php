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

/**
 * Tests the connection to OpenSearch.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['help' => false], ['h' => 'help']);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}
if ($options['help']) {
    cli_writeln("Tests the connection to OpenSearch: version, cluster health, ISM plugin, write permission.

Usage:
    php admin/tool/opensearch/cli/test.php

Exit code: 0 when every check passes, 1 otherwise.");
    exit(0);
}

$config = \tool_opensearch\local\config::load();
if ($config->error !== '') {
    cli_problem($config->error);
}
$failed = false;
foreach ((new \tool_opensearch\local\connection_test($config))->run() as $check) {
    $failed = $failed || !$check['ok'];
    cli_writeln(sprintf(
        '[%s] %s: %s%s',
        $check['ok'] ? 'OK' : 'FAIL',
        $check['name'],
        $check['message'],
        $check['ms'] !== null ? " ({$check['ms']} ms)" : ''
    ));
}
exit($failed ? 1 : 0);
