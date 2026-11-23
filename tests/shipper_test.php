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
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use tool_opensearch\fixtures\stream_callbacks;
use tool_opensearch\local\client;
use tool_opensearch\local\config;
use tool_opensearch\local\queue;
use tool_opensearch\local\registry;
use tool_opensearch\local\schema_manager;
use tool_opensearch\local\shipper;
use tool_opensearch\local\stats;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/stream_callbacks.php');

/**
 * Tests of the sending of the queue.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(shipper::class)]
#[CoversClass(task\ship_queue::class)]
final class shipper_test extends \advanced_testcase {
    /** @var \core\clock Frozen clock. */
    private \core\clock $clock;

    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        stream_callbacks::install(make_request_directory());
        registry::streams();
        $this->resetDebugging();
        $this->clock = $this->mock_clock_with_frozen(1767225600);
        // The templates are already in the cluster.
        $manager = new schema_manager(config::load());
        foreach (registry::streams() as $name => $stream) {
            stats::update($name, ['schemahash' => $manager->hash($stream)]);
        }
    }

    #[\Override]
    protected function tearDown(): void {
        queue::reset();
        client::phpunit_set_handler(null);
        parent::tearDown();
    }

    /**
     * Queues documents.
     *
     * @param int $count How many.
     * @param string $stream Stream.
     * @return void
     */
    private function queue(int $count, string $stream = 'events'): void {
        for ($i = 0; $i < $count; $i++) {
            api::index($stream, ['userid' => $i]);
        }
        queue::flush();
    }

    /**
     * Mixed _bulk answer: 201 leaves the queue, 400 is discarded and counted, 429 waits with backoff.
     */
    public function test_bulk_items(): void {
        global $DB;
        $this->queue(3);
        $history = stream_callbacks::mock([
            stream_callbacks::json(200, ['version' => ['number' => '2.19.1']]),
            stream_callbacks::json(200, ['errors' => true, 'items' => [
                ['create' => ['status' => 201]],
                ['create' => ['status' => 400, 'error' => ['type' => 'strict_dynamic_mapping_exception', 'reason' => 'x']]],
                ['create' => ['status' => 429, 'error' => ['type' => 'es_rejected_execution_exception', 'reason' => 'y']]],
            ]]),
        ]);
        $totals = (new shipper(config::load()))->run();
        $this->assertSame(['up' => 1, 'sent' => 1, 'failed' => 1, 'retried' => 1], $totals);

        $body = (string) $history[1]->getBody();
        $lines = explode("\n", trim($body));
        $this->assertCount(6, $lines);
        $this->assertSame(['create' => ['_index' => 'test-events']], json_decode($lines[0], true));
        $this->assertSame(2, json_decode($lines[5], true)['userid']);

        $left = $DB->get_record('tool_opensearch_queue', []);
        $this->assertEquals(1, $left->attempts);
        $this->assertGreaterThanOrEqual($this->clock->time() + 24, $left->nextattempt);
        $this->assertLessThanOrEqual($this->clock->time() + 36, $left->nextattempt);

        $record = stats::get('events');
        $this->assertEquals(1, $record->sent);
        $this->assertEquals(1, $record->failed);
        $this->assertEquals(1, $record->queued);
        $this->assertEquals($this->clock->time(), $record->lastsuccess);
        $this->assertEquals(1, get_config('tool_opensearch', 'up'));
    }

    /**
     * A failed request keeps the whole batch; documents not due yet are not sent.
     */
    public function test_request_failure_and_backoff(): void {
        global $DB;
        $this->queue(2);
        stream_callbacks::mock([stream_callbacks::json(200), stream_callbacks::json(503)]);
        $totals = (new shipper(config::load()))->run();
        $this->assertSame(2, $totals['retried']);
        $this->assertSame(2, $DB->count_records('tool_opensearch_queue', ['attempts' => 1]));

        // Not due yet: only the ping.
        $history = stream_callbacks::mock([stream_callbacks::json(200)]);
        (new shipper(config::load()))->run();
        $this->assertCount(1, $history);

        // Due again: sent.
        $this->clock->bump(60);
        stream_callbacks::mock([stream_callbacks::json(200), stream_callbacks::json(200, ['items' => [
            ['create' => ['status' => 201]], ['create' => ['status' => 201]],
        ]])]);
        $this->assertSame(2, (new shipper(config::load()))->run()['sent']);
        $this->assertSame(0, $DB->count_records('tool_opensearch_queue'));
    }

    /**
     * Unreachable cluster: nothing is sent, "up" is 0 and the documents wait.
     */
    public function test_cluster_down(): void {
        global $DB;
        $this->queue(1);
        $refused = fn(RequestInterface $r) => throw new ConnectException('x', $r, null, ['errno' => 7]);
        stream_callbacks::mock([$refused, $refused]);
        $totals = (new shipper(config::load()))->run();
        $this->assertSame(0, $totals['up']);
        $this->assertEquals(0, get_config('tool_opensearch', 'up'));
        $this->assertSame(1, $DB->count_records('tool_opensearch_queue', ['attempts' => 0]));
        $this->assertStringContainsString('refused', stats::get('events')->lasterror);
    }

    /**
     * Batches respect the document limit.
     */
    public function test_batch_limit(): void {
        set_config('batchdocs', 2, 'tool_opensearch');
        $this->queue(3);
        $ok = fn(int $n) => stream_callbacks::json(200, ['items' => array_fill(0, $n, ['create' => ['status' => 201]])]);
        $history = stream_callbacks::mock([stream_callbacks::json(200), $ok(2), $ok(1)]);
        $this->assertSame(3, (new shipper(config::load()))->run()['sent']);
        $this->assertCount(3, $history);
    }

    /**
     * The templates are sent before the first document of a stream.
     */
    public function test_schema_before_first_document(): void {
        global $DB;
        $DB->set_field('tool_opensearch_stream', 'schemahash', null);
        $this->queue(1);
        $history = stream_callbacks::mock([
            stream_callbacks::json(200),
            stream_callbacks::json(200, ['acknowledged' => true]),
            stream_callbacks::json(404),
            stream_callbacks::json(404),
            stream_callbacks::json(201),
            stream_callbacks::json(200),
            stream_callbacks::json(200, ['items' => [['create' => ['status' => 201]]]]),
        ]);
        $this->assertSame(1, (new shipper(config::load()))->run()['sent']);
        $this->assertSame('/_index_template/test-events', $history[1]->getUri()->getPath());
        $this->assertSame('/_bulk', $history[6]->getUri()->getPath());
    }

    /**
     * Documents of a stream nobody declares anymore are discarded.
     */
    public function test_unknown_stream(): void {
        global $DB;
        $DB->insert_record('tool_opensearch_queue', ['stream' => 'gone', 'payload' => '{}', 'attempts' => 0,
            'nextattempt' => 0, 'timecreated' => 1]);
        stream_callbacks::mock([stream_callbacks::json(200)]);
        $this->assertSame(1, (new shipper(config::load()))->run()['failed']);
        $this->assertSame(0, $DB->count_records('tool_opensearch_queue'));
    }

    /**
     * Backoff grows up to 30 minutes, with jitter.
     */
    public function test_backoff(): void {
        $this->assertGreaterThanOrEqual(24, shipper::backoff(1));
        $this->assertLessThanOrEqual(36, shipper::backoff(1));
        $this->assertGreaterThanOrEqual(48, shipper::backoff(2));
        $this->assertLessThanOrEqual(2160, shipper::backoff(50));
    }

    /**
     * The task does nothing when sending is disabled.
     */
    public function test_task_disabled(): void {
        set_config('enabled', 0, 'tool_opensearch');
        $this->expectOutputString("tool_opensearch is disabled.\n");
        (new task\ship_queue())->execute();
    }
}
