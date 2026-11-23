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

namespace tool_opensearch\fixtures;

use GuzzleHttp\Handler\MockHandler;
use Psr\Http\Message\RequestInterface as Request;
use tool_opensearch\api;
use tool_opensearch\hook\register_streams;
use tool_opensearch\local\client;
use tool_opensearch\local\queue;
use tool_opensearch\local\registry;

/**
 * Streams declared in the tests, and helpers shared by the test cases.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class stream_callbacks {
    /**
     * Declares the "events" stream.
     *
     * @param register_streams $hook Hook.
     */
    public static function register(register_streams $hook): void {
        $hook->add_stream('local_test', 'events', [
            'name' => ['type' => 'keyword'],
            'userid' => ['type' => 'long'],
        ], 30, 'Test events.');
        $hook->add_stream('local_test', 'events', [], 1);
        $hook->add_stream('local_test', 'Bad-Name', [], 1);
    }

    /**
     * Always fails.
     *
     * @param register_streams $hook Hook.
     */
    public static function fail(register_streams $hook): void {
        throw new \moodle_exception('error');
    }

    /**
     * Installs the callbacks of this fixture as the only hook callbacks, and enables the plugin.
     *
     * @param string $dir Temporary directory of the test.
     * @param bool $enabled Whether to enable sending.
     * @return void
     */
    public static function install(string $dir, bool $enabled = true): void {
        $file = $dir . '/hooks.php';
        $callbacks = [
            ['hook' => register_streams::class, 'callback' => [self::class, 'fail']],
            ['hook' => register_streams::class, 'callback' => [self::class, 'register']],
        ];
        file_put_contents($file, '<?php $callbacks = ' . var_export($callbacks, true) . ';');
        \core\di::set(\core\hook\manager::class, \core\hook\manager::phpunit_get_instance(['local_test' => $file]));
        set_config('enabled', $enabled ? 1 : 0, 'tool_opensearch');
        set_config('hosts', 'http://os1:9200, http://os2:9200', 'tool_opensearch');
        set_config('prefix', 'test', 'tool_opensearch');
        api::reset();
        queue::reset();
        registry::reset();
    }

    /**
     * Replaces the network with queued answers; records the requests.
     *
     * @param array $answers Responses or exceptions, in order.
     * @return \ArrayObject Recorded requests (GuzzleHttp\Psr7\Request).
     */
    public static function mock(array $answers): \ArrayObject {
        $mock = new MockHandler($answers);
        $history = new \ArrayObject();
        client::phpunit_set_handler(function (Request $request, array $options) use ($mock, $history) {
            $history[] = $request;
            return $mock($request, $options);
        });
        return $history;
    }

    /**
     * JSON response.
     *
     * @param int $status HTTP status.
     * @param array $body Body.
     * @return \GuzzleHttp\Psr7\Response
     */
    public static function json(int $status, array $body = []): \GuzzleHttp\Psr7\Response {
        return new \GuzzleHttp\Psr7\Response($status, ['Content-Type' => 'application/json'], json_encode($body));
    }
}
