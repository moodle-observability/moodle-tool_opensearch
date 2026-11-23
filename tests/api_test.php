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

use PHPUnit\Framework\Attributes\CoversClass;
use tool_opensearch\fixtures\stream_callbacks;
use tool_opensearch\hook\register_streams;
use tool_opensearch\local\client;
use tool_opensearch\local\queue;
use tool_opensearch\local\registry;
use tool_opensearch\local\stats;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/stream_callbacks.php');

/**
 * Tests of the public API, of the streams registry and of the queue.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(api::class)]
#[CoversClass(queue::class)]
#[CoversClass(registry::class)]
#[CoversClass(register_streams::class)]
#[CoversClass(stats::class)]
final class api_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        stream_callbacks::install(make_request_directory());
    }

    #[\Override]
    protected function tearDown(): void {
        queue::reset();
        client::phpunit_set_handler(null);
        api::reset();
        parent::tearDown();
    }

    /**
     * Streams come from the hook; a failing callback and invalid or duplicated names are reported, not fatal.
     */
    public function test_registry(): void {
        $streams = registry::streams();
        $this->assertDebuggingCalledCount(3);
        $this->assertSame(['events'], array_keys($streams));
        $this->assertSame('local_test', $streams['events']->component);
        $this->assertSame(30, $streams['events']->retention());
        set_config('retention_events', 90, 'tool_opensearch');
        $this->assertSame(90, $streams['events']->retention());
        $this->assertArrayHasKey('@timestamp', $streams['events']->mapping_properties());
    }

    /**
     * index() buffers the document and writes it to the queue with @timestamp and moodle.site.
     */
    public function test_index(): void {
        global $DB;
        $this->mock_clock_with_frozen(1767225600);
        registry::streams();
        $this->resetDebugging();
        api::index('events', ['name' => 'login', 'userid' => 2]);
        $this->assertSame(1, queue::buffered());
        $this->assertSame(0, $DB->count_records('tool_opensearch_queue'));
        queue::flush();
        $row = $DB->get_record('tool_opensearch_queue', []);
        $this->assertSame('events', $row->stream);
        $this->assertSame([
            'name' => 'login',
            'userid' => 2,
            '@timestamp' => '2026-01-01T00:00:00.000+00:00',
            'moodle' => ['site' => 'test', 'shortname' => get_site()->shortname],
        ], json_decode($row->payload, true));
        $this->assertEquals(1767225600, $row->timecreated);
    }

    /**
     * A full buffer is written without waiting for the end of the request.
     */
    public function test_buffer_limit(): void {
        global $DB;
        registry::streams();
        $this->resetDebugging();
        for ($i = 0; $i < queue::BUFFER_DOCS; $i++) {
            api::index('events', ['userid' => $i]);
        }
        $this->assertSame(0, queue::buffered());
        $this->assertSame(queue::BUFFER_DOCS, $DB->count_records('tool_opensearch_queue'));
    }

    /**
     * Unknown streams are reported and ignored; nothing is queued when sending is disabled.
     */
    public function test_unknown_stream_and_disabled(): void {
        registry::streams();
        $this->resetDebugging();
        api::index('nope', ['a' => 1]);
        $this->assertDebuggingCalled();
        $this->assertSame(0, queue::buffered());

        set_config('enabled', 0, 'tool_opensearch');
        api::reset();
        api::index('events', ['userid' => 1]);
        $this->assertSame(0, queue::buffered());
        $this->assertFalse(api::is_enabled());
    }

    /**
     * Above the queue limit, new documents are dropped and counted.
     */
    public function test_queue_full(): void {
        global $DB;
        registry::streams();
        $this->resetDebugging();
        set_config('queuemax', 3, 'tool_opensearch');
        stats::update('events', ['queued' => 2]);
        api::reset();
        registry::streams();
        $this->resetDebugging();
        for ($i = 0; $i < 3; $i++) {
            api::index('events', ['userid' => $i]);
        }
        queue::flush();
        $this->assertSame(1, $DB->count_records('tool_opensearch_queue'));
        $record = stats::get('events');
        $this->assertEquals(2, $record->dropped);
        $this->assertNotEmpty($record->lasterror);
    }

    /**
     * index_now() sends right away, and queues the document when the cluster fails.
     */
    public function test_index_now(): void {
        global $DB;
        registry::streams();
        $this->resetDebugging();
        $history = stream_callbacks::mock([
            stream_callbacks::json(200, ['errors' => false, 'items' => [['create' => ['status' => 201]]]]),
            stream_callbacks::json(503),
        ]);
        $this->assertTrue(api::index_now('events', ['userid' => 1]));
        $this->assertStringStartsWith('{"create":{"_index":"test-events"}}', (string) $history[0]->getBody());
        $this->assertEquals(1, stats::get('events')->sent);

        $this->assertFalse(api::index_now('events', ['userid' => 2]));
        queue::flush();
        $this->assertSame(1, $DB->count_records('tool_opensearch_queue'));
    }

    /**
     * search() and delete_by_query() use the data stream of the stream.
     */
    public function test_search_and_delete(): void {
        registry::streams();
        $this->resetDebugging();
        $history = stream_callbacks::mock([
            stream_callbacks::json(200, ['hits' => ['hits' => []]]),
            stream_callbacks::json(200, ['deleted' => 4]),
        ]);
        $query = ['term' => ['userid' => 2]];
        $this->assertSame(['hits' => ['hits' => []]], api::search('events', ['query' => $query]));
        $this->assertSame(4, api::delete_by_query('events', $query));
        $this->assertSame('/test-events/_search', $history[0]->getUri()->getPath());
        $this->assertSame('/test-events/_delete_by_query', $history[1]->getUri()->getPath());
        $this->assertSame(['query' => $query], json_decode((string) $history[1]->getBody(), true));

        $this->expectException(local\client_exception::class);
        api::search('nope', []);
    }
}
