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

namespace tool_opensearch;

use GuzzleHttp\Exception\ConnectException;
use Psr\Http\Message\RequestInterface as Request;
use PHPUnit\Framework\Attributes\CoversClass;
use tool_opensearch\fixtures\stream_callbacks;
use tool_opensearch\local\client;
use tool_opensearch\local\client_exception;
use tool_opensearch\local\config;
use tool_opensearch\local\connection_test;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/stream_callbacks.php');

/**
 * Tests of the HTTP client and of the connection test.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(client::class)]
#[CoversClass(client_exception::class)]
#[CoversClass(connection_test::class)]
final class client_test extends \advanced_testcase {
    #[\Override]
    protected function tearDown(): void {
        client::phpunit_set_handler(null);
        parent::tearDown();
    }

    /**
     * Configuration with basic authentication.
     *
     * @param array $settings Extra settings.
     * @return config
     */
    private function config(array $settings = []): config {
        return config::load([], (object) ($settings + [
            'enabled' => 1, 'hosts' => 'http://os1:9200', 'username' => 'moodle',
            'password' => \core\encryption::encrypt('s3cret'), 'prefix' => 'test',
        ]));
    }

    /**
     * Basic authentication, JSON body, and the core block list ignored for the configured host.
     */
    public function test_request_basic_auth(): void {
        $this->resetAfterTest();
        // The core blocks private addresses by default; the configured cluster must still be reachable.
        set_config('curlsecurityblockedhosts', "os1\n127.0.0.1");
        $history = stream_callbacks::mock([stream_callbacks::json(200, ['acknowledged' => true])]);
        $body = (new client($this->config()))->call('PUT', '/_index_template/x', ['a' => 1]);
        $this->assertSame(['acknowledged' => true], $body);
        /** @var Request $request */
        $request = $history[0];
        $this->assertSame('http://os1:9200/_index_template/x', (string) $request->getUri());
        $this->assertSame('Basic ' . base64_encode('moodle:s3cret'), $request->getHeaderLine('Authorization'));
        $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
        $this->assertSame('{"a":1}', (string) $request->getBody());
    }

    /**
     * NDJSON bodies; no Authorization header without authentication.
     */
    public function test_request_ndjson_no_auth(): void {
        $this->resetAfterTest();
        $history = stream_callbacks::mock([stream_callbacks::json(200)]);
        (new client($this->config(['auth' => 'none'])))->request('POST', '/_bulk', "{}\n{}\n");
        $this->assertSame('application/x-ndjson', $history[0]->getHeaderLine('Content-Type'));
        $this->assertSame('', $history[0]->getHeaderLine('Authorization'));
    }

    /**
     * An unreachable host makes the client try the next one.
     */
    public function test_next_host_on_connect_error(): void {
        $this->resetAfterTest();
        $refused = fn(Request $r) => new ConnectException('refused', $r, null, ['errno' => 7]);
        $history = stream_callbacks::mock([
            function (Request $r) use ($refused) {
                throw $refused($r);
            },
            stream_callbacks::json(200, ['ok' => 1]),
        ]);
        $config = $this->config(['hosts' => 'http://os1:9200,http://os2:9200']);
        $this->assertSame(['ok' => 1], (new client($config))->call('GET', '/'));
        $this->assertCount(2, $history);
        $this->assertNotSame($history[0]->getUri()->getHost(), $history[1]->getUri()->getHost());
    }

    /**
     * Clear messages for transport and HTTP errors.
     */
    public function test_errors(): void {
        $this->resetAfterTest();
        stream_callbacks::mock([
            fn(Request $r) => throw new ConnectException('x', $r, null, ['errno' => 28]),
            stream_callbacks::json(401),
            stream_callbacks::json(429, ['error' => ['type' => 'es_rejected_execution_exception', 'reason' => 'busy']]),
        ]);
        $client = new client($this->config());
        foreach ([[true, 'Timeout'], [false, '401'], [true, 'busy']] as [$transient, $text]) {
            try {
                $client->call('GET', '/');
                $this->fail('Exception expected');
            } catch (client_exception $e) {
                $this->assertSame($transient, $e->transient);
                $this->assertStringContainsString($text, $e->get_reason());
            }
        }
    }

    /**
     * No hosts: a clear error, without requests.
     */
    public function test_no_hosts(): void {
        $this->resetAfterTest();
        $this->expectException(client_exception::class);
        (new client(config::load([], new \stdClass())))->request('GET', '/');
    }

    /**
     * The connection test checks version, health, ISM and write permission.
     */
    public function test_connection_test(): void {
        $this->resetAfterTest();
        $history = stream_callbacks::mock([
            stream_callbacks::json(200, [
                'cluster_name' => 'c',
                'version' => ['distribution' => 'opensearch', 'number' => '2.19.1'],
            ]),
            stream_callbacks::json(200, ['status' => 'yellow']),
            stream_callbacks::json(200, ['policies' => []]),
            stream_callbacks::json(201, ['_id' => 'abc']),
            stream_callbacks::json(200, ['result' => 'deleted']),
        ]);
        $checks = (new connection_test($this->config()))->run();
        $this->assertCount(4, $checks);
        $this->assertSame([true, true, true, true], array_column($checks, 'ok'));
        $this->assertStringContainsString('2.19.1', $checks[0]['message']);
        $this->assertSame('/test-selftest/_doc/abc', $history[4]->getUri()->getPath());
        $this->assertSame('DELETE', $history[4]->getMethod());
    }

    /**
     * The connection test stops when the cluster refuses the credentials.
     */
    public function test_connection_test_denied(): void {
        $this->resetAfterTest();
        stream_callbacks::mock([stream_callbacks::json(401)]);
        $checks = (new connection_test($this->config()))->run();
        $this->assertCount(1, $checks);
        $this->assertFalse($checks[0]['ok']);
    }
}
