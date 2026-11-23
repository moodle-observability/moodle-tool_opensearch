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
use tool_opensearch\local\client;
use tool_opensearch\local\config;
use tool_opensearch\local\registry;
use tool_opensearch\local\schema_manager;
use tool_opensearch\local\stats;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/stream_callbacks.php');

/**
 * Tests of the index templates and retention policies.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(schema_manager::class)]
#[CoversClass(task\sync_schema::class)]
final class schema_manager_test extends \advanced_testcase {
    #[\Override]
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        stream_callbacks::install(make_request_directory());
        registry::streams();
        $this->resetDebugging();
    }

    #[\Override]
    protected function tearDown(): void {
        client::phpunit_set_handler(null);
        parent::tearDown();
    }

    /**
     * Template: data stream, strict mapping, compression, shards and replicas from the settings.
     */
    public function test_template(): void {
        set_config('replicas', 0, 'tool_opensearch');
        $template = (new schema_manager(config::load()))->template(registry::get('events'));
        $this->assertSame(['test-events*'], $template['index_patterns']);
        $this->assertEquals(new \stdClass(), $template['data_stream']);
        $this->assertSame(0, $template['template']['settings']['index.number_of_replicas']);
        $this->assertSame('best_compression', $template['template']['settings']['index.codec']);
        $this->assertSame('strict', $template['template']['mappings']['dynamic']);
        $this->assertSame(['type' => 'date'], $template['template']['mappings']['properties']['@timestamp']);
        $this->assertSame(['type' => 'long'], $template['template']['mappings']['properties']['userid']);
    }

    /**
     * Policy: rollover, deletion after the retention, applied to the backing indices.
     */
    public function test_policy(): void {
        set_config('retention_events', 7, 'tool_opensearch');
        $manager = new schema_manager(config::load());
        $policy = $manager->policy(registry::get('events'))['policy'];
        $this->assertSame('test-events-ism', $manager->policy_id(registry::get('events')));
        $this->assertSame('7d', $policy['states'][0]['transitions'][0]['conditions']['min_index_age']);
        $this->assertSame(['test-events'], $policy['ism_template'][0]['index_patterns']);
    }

    /**
     * Sync sends only what changed; a new policy version uses the sequence numbers and moves the old indices.
     */
    public function test_sync(): void {
        $history = stream_callbacks::mock([
            stream_callbacks::json(200, ['acknowledged' => true]),
            stream_callbacks::json(404),
            stream_callbacks::json(404),
            stream_callbacks::json(201),
            stream_callbacks::json(200, ['updated_indices' => 0]),
        ]);
        $manager = new schema_manager(config::load());
        $this->assertFalse($manager->in_sync());
        $this->assertSame(['events' => 'updated'], $manager->sync());
        $this->assertCount(5, $history);
        // No data stream yet: the mapping update is skipped (404).
        $this->assertSame('/test-events/_mapping', $history[1]->getUri()->getPath());
        $this->assertSame('PUT', $history[3]->getMethod());
        $this->assertSame('/_plugins/_ism/policies/test-events-ism', $history[3]->getUri()->getPath());
        $this->assertSame('/_plugins/_ism/add/.ds-test-events-*', $history[4]->getUri()->getPath());
        $this->assertTrue($manager->in_sync());
        $this->assertSame(['events' => 'unchanged'], $manager->sync());

        // The retention changed: the policy is replaced.
        set_config('retention_events', 10, 'tool_opensearch');
        $history = stream_callbacks::mock([
            stream_callbacks::json(200, ['acknowledged' => true]),
            stream_callbacks::json(200, ['acknowledged' => true]),
            stream_callbacks::json(200, ['_seq_no' => 4, '_primary_term' => 1]),
            stream_callbacks::json(200),
            stream_callbacks::json(200),
            stream_callbacks::json(200),
        ]);
        // A new client picks up the new answers.
        $manager = new schema_manager(config::load());
        $this->assertSame(['events' => 'updated'], $manager->sync());
        $this->assertSame(
            ['properties' => registry::get('events')->mapping_properties()],
            json_decode((string) $history[1]->getBody(), true)
        );
        $this->assertSame('if_seq_no=4&if_primary_term=1', $history[3]->getUri()->getQuery());
        $this->assertSame('/_plugins/_ism/change_policy/.ds-test-events-*', $history[5]->getUri()->getPath());
    }

    /**
     * An incompatible mapping (a field changing type) is reported as the error of the stream.
     */
    public function test_sync_mapping_conflict(): void {
        stream_callbacks::mock([
            stream_callbacks::json(200, ['acknowledged' => true]),
            stream_callbacks::json(400, ['error' => ['type' => 'illegal_argument_exception',
                'reason' => 'mapper [userid] cannot be changed from type [long] to [keyword]']]),
        ]);
        $result = (new schema_manager(config::load()))->sync();
        $this->assertStringContainsString('cannot be changed', $result['events']);
    }

    /**
     * Errors are kept per stream; invalidate() makes the next run send everything again.
     */
    public function test_sync_error_and_invalidate(): void {
        stream_callbacks::mock([stream_callbacks::json(403, ['error' => ['type' => 'security_exception', 'reason' => 'no']])]);
        $result = (new schema_manager(config::load()))->sync();
        $this->assertStringContainsString('403', $result['events']);
        $this->assertStringContainsString('403', stats::get('events')->lasterror);

        stats::update('events', ['schemahash' => 'x']);
        schema_manager::invalidate();
        $this->assertNull(stats::get('events')->schemahash);
    }
}
