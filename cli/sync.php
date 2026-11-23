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
 * Sends the index templates and retention policies of the streams to OpenSearch.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(['help' => false, 'force' => false, 'show' => false], ['h' => 'help', 'f' => 'force']);
if ($unrecognised) {
    cli_error(get_string('cliunknowoption', 'admin', implode(PHP_EOL . '  ', $unrecognised)));
}
if ($options['help']) {
    cli_writeln("Sends the index templates and retention (ISM) policies of the streams to OpenSearch.

Options:
    -f, --force   Send them even when they did not change.
    --show        Print the templates and policies as JSON, without sending them.
    -h, --help    Print this help.

Usage:
    php admin/tool/opensearch/cli/sync.php --force");
    exit(0);
}

$config = \tool_opensearch\local\config::load();
$manager = new \tool_opensearch\local\schema_manager($config);
if ($options['show']) {
    foreach (\tool_opensearch\local\registry::streams() as $name => $stream) {
        cli_writeln("# {$config->data_stream($name)}");
        cli_writeln(json_encode(
            ['template' => $manager->template($stream), 'policy' => $manager->policy($stream)],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ));
    }
    exit(0);
}
if (!$config->enabled) {
    cli_error(get_string('error_disabled', 'tool_opensearch'));
}
$failed = false;
foreach ($manager->sync((bool) $options['force']) as $stream => $result) {
    $failed = $failed || !in_array($result, ['unchanged', 'updated'], true);
    cli_writeln("{$stream}: {$result}");
}
exit($failed ? 1 : 0);
