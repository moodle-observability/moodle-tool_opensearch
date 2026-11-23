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

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Group;
use tool_opensearch\fixtures\stream_callbacks;
use tool_opensearch\local\client;
use tool_opensearch\local\config;
use tool_opensearch\local\connection_test;
use tool_opensearch\local\queue;
use tool_opensearch\local\registry;
use tool_opensearch\local\schema_manager;
use tool_opensearch\local\shipper;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/stream_callbacks.php');

/**
 * Tests against a real OpenSearch, only when TOOL_OPENSEARCH_TEST_HOSTS is set (for example http://127.0.0.1:9200,
 * with the security plugin disabled).
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversNothing]
#[Group('tool_opensearch_integration')]
final class integration_test extends \advanced_testcase {
    /** @var string Prefix of this run. */
    private string $prefix;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $hosts = getenv('TOOL_OPENSEARCH_TEST_HOSTS');
        if (!$hosts) {
            $this->markTestSkipped('TOOL_OPENSEARCH_TEST_HOSTS is not set');
        }
        $this->resetAfterTest();
        stream_callbacks::install(make_request_directory());
        $this->prefix = 'phpunit-' . strtolower(random_string(6));
        set_config('hosts', $hosts, 'tool_opensearch');
        set_config('auth', 'none', 'tool_opensearch');
        set_config('prefix', $this->prefix, 'tool_opensearch');
        set_config('replicas', 0, 'tool_opensearch');
        api::reset();
        registry::streams();
        $this->resetDebugging();
    }

    #[\Override]
    protected function tearDown(): void {
        if (isset($this->prefix)) {
            $client = new client(config::load());
            $client->request('DELETE', '/_data_stream/' . $this->prefix . '-events');
            $client->request('DELETE', '/_index_template/' . $this->prefix . '-events');
            $client->request('DELETE', '/_plugins/_ism/policies/' . $this->prefix . '-events-ism');
            $client->request('DELETE', '/' . $this->prefix . '-selftest');
        }
        queue::reset();
        parent::tearDown();
    }

    /**
     * Connection test, templates, queue, _bulk, search and delete by query, end to end.
     */
    public function test_end_to_end(): void {
        global $DB;
        $config = config::load();
        $checks = (new connection_test($config))->run();
        $this->assertSame(
            [],
            array_values(array_filter($checks, fn(array $check): bool => !$check['ok'])),
            json_encode($checks)
        );

        $this->assertSame(['events' => 'updated'], (new schema_manager($config))->sync());

        api::index('events', ['name' => 'login', 'userid' => 2]);
        api::index('events', ['name' => 'logout', 'userid' => 3]);
        api::index('events', ['name' => 'x', 'notmapped' => 1]);
        queue::flush();
        $totals = (new shipper($config))->run();
        $this->assertSame(['up' => 1, 'sent' => 2, 'failed' => 1, 'retried' => 0], $totals);
        $this->assertSame(0, $DB->count_records('tool_opensearch_queue'));

        (new client($config))->call('POST', '/' . $this->prefix . '-events/_refresh');
        $found = api::search('events', ['query' => ['term' => ['userid' => 2]]]);
        $this->assertSame(1, $found['hits']['total']['value']);
        $this->assertSame($this->prefix, $found['hits']['hits'][0]['_source']['moodle']['site']);

        $this->assertSame(1, api::delete_by_query('events', ['term' => ['userid' => 2]]));
    }
}
