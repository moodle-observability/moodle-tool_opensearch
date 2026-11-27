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

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\RequestInterface;
use tool_opensearch\local\client;
use tool_opensearch\local\queue;
use tool_opensearch\local\registry;

/**
 * Tests of the "alerts" stream (alerts of tool_taskmonitor).
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(hook_callbacks::class)]
final class alerts_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        if (!class_exists(\tool_taskmonitor\hook\alert_raised::class)) {
            $this->markTestSkipped('tool_taskmonitor is not installed');
        }
        $this->resetAfterTest();
        set_config('enabled', 1, 'tool_opensearch');
        set_config('hosts', 'http://opensearch:9200', 'tool_opensearch');
        set_config('prefix', 'test', 'tool_opensearch');
        api::reset();
        queue::reset();
    }

    #[\Override]
    protected function tearDown(): void {
        client::phpunit_set_handler(null);
        queue::reset();
        api::reset();
        parent::tearDown();
    }

    /**
     * The stream is declared, with its own retention, when tool_taskmonitor is installed.
     */
    public function test_stream(): void {
        registry::reset();
        $stream = registry::get(hook_callbacks::ALERTS_STREAM);
        $this->assertNotNull($stream);
        $this->assertSame('tool_opensearch', $stream->component);
        $this->assertSame(hook_callbacks::ALERTS_RETENTION, $stream->retentiondays);
        $this->assertSame(['type' => 'date', 'format' => 'epoch_second'], $stream->properties['since']);
    }

    /**
     * An alert is sent right away to the alerts data stream; when the cluster fails it waits in the queue.
     */
    public function test_alert_raised(): void {
        global $DB;
        $history = new \ArrayObject();
        $mock = new MockHandler([
            new Response(200, [], json_encode(['items' => [['create' => ['status' => 201]]]])),
            new Response(503, [], '{}'),
        ]);
        client::phpunit_set_handler(function (RequestInterface $request, array $options) use ($mock, $history) {
            $history[] = $request;
            return $mock($request, $options);
        });
        $alert = ['id' => 'cron', 'type' => 'cron', 'severity' => 'critical', 'title' => 'Cron stopped',
            'details' => 'Last start 20 minutes ago.', 'since' => 1767225600];
        hook_callbacks::alert_raised(new \tool_taskmonitor\hook\alert_raised($alert, 'raised'));

        $lines = explode("\n", trim((string) $history[0]->getBody()));
        $this->assertSame(['create' => ['_index' => 'test-alerts']], json_decode($lines[0], true));
        $doc = json_decode($lines[1], true);
        $this->assertSame('cron', $doc['alert_id']);
        $this->assertSame('raised', $doc['state']);
        $this->assertSame(1767225600, $doc['since']);
        $this->assertSame('test', $doc['moodle']['site']);

        hook_callbacks::alert_raised(new \tool_taskmonitor\hook\alert_raised($alert, 'resolved'));
        queue::flush();
        $this->assertSame(1, $DB->count_records('tool_opensearch_queue', ['stream' => 'alerts']));
    }

    /**
     * Nothing is sent when sending is disabled.
     */
    public function test_disabled(): void {
        set_config('enabled', 0, 'tool_opensearch');
        api::reset();
        $history = new \ArrayObject();
        client::phpunit_set_handler(function (RequestInterface $request) use ($history) {
            $history[] = $request;
            throw new \coding_exception('No request expected');
        });
        hook_callbacks::alert_raised(new \tool_taskmonitor\hook\alert_raised(['id' => 'x'], 'raised'));
        $this->assertCount(0, $history);
    }
}
