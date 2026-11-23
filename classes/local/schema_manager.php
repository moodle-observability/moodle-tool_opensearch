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
 * Index templates (data streams with a strict mapping) and ISM retention policies of the streams.
 *
 * Only sends what changed: the hash of the template and of the policy is kept in {tool_opensearch_stream}.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class schema_manager {
    /** Rollover of the backing indices: whichever comes first. */
    public const ROLLOVER = ['min_index_age' => '7d', 'min_primary_shard_size' => '30gb'];

    /** @var config Configuration. */
    private config $config;

    /** @var client Client. */
    private client $client;

    /**
     * Constructor.
     *
     * @param config $config Configuration.
     * @param client|null $client Client (created from the configuration if null).
     */
    public function __construct(config $config, ?client $client = null) {
        $this->config = $config;
        $this->client = $client ?? new client($config);
    }

    /**
     * Index template of a stream.
     *
     * @param stream $stream Stream.
     * @return array
     */
    public function template(stream $stream): array {
        return [
            'index_patterns' => [$this->config->data_stream($stream->name) . '*'],
            'data_stream' => new \stdClass(),
            'priority' => 200,
            'template' => [
                'settings' => [
                    'index.number_of_shards' => $this->config->shards,
                    'index.number_of_replicas' => $this->config->replicas,
                    'index.codec' => 'best_compression',
                ],
                'mappings' => [
                    'dynamic' => 'strict',
                    'properties' => $stream->mapping_properties(),
                ],
            ],
            '_meta' => ['managed_by' => 'tool_opensearch', 'component' => $stream->component],
        ];
    }

    /**
     * ISM policy of a stream: rollover while "hot", deletion after the retention.
     *
     * @param stream $stream Stream.
     * @return array
     */
    public function policy(stream $stream): array {
        return [
            'policy' => [
                'description' => "Retention of {$this->config->data_stream($stream->name)} (managed by tool_opensearch).",
                'default_state' => 'hot',
                'states' => [
                    [
                        'name' => 'hot',
                        'actions' => [['rollover' => self::ROLLOVER]],
                        'transitions' => [
                            ['state_name' => 'delete', 'conditions' => ['min_index_age' => $stream->retention() . 'd']],
                        ],
                    ],
                    [
                        'name' => 'delete',
                        'actions' => [['delete' => new \stdClass()]],
                        'transitions' => [],
                    ],
                ],
                'ism_template' => [
                    // For data streams, OpenSearch matches the data stream name, not the backing index name.
                    ['index_patterns' => [$this->config->data_stream($stream->name)], 'priority' => 100],
                ],
            ],
        ];
    }

    /**
     * Name of the ISM policy of a stream.
     *
     * @param stream $stream Stream.
     * @return string
     */
    public function policy_id(stream $stream): string {
        return $this->config->data_stream($stream->name) . '-ism';
    }

    /**
     * Hash of the template and of the policy of a stream.
     *
     * @param stream $stream Stream.
     * @return string
     */
    public function hash(stream $stream): string {
        return sha1(json_encode([$this->template($stream), $this->policy($stream)]));
    }

    /**
     * Sends the template and the policy of the streams that changed.
     *
     * @param bool $force Whether to send them even when the hash did not change.
     * @return array Result by stream name: "unchanged", "updated" or the error message.
     */
    public function sync(bool $force = false): array {
        $results = [];
        $stats = stats::all();
        foreach (registry::streams() as $name => $stream) {
            $hash = $this->hash($stream);
            if (!$force && isset($stats[$name]) && $stats[$name]->schemahash === $hash) {
                $results[$name] = 'unchanged';
                continue;
            }
            try {
                $this->client->call('PUT', '/_index_template/' . $this->config->data_stream($name), $this->template($stream));
                $this->put_mapping($stream);
                $this->put_policy($stream);
                stats::update($name, ['schemahash' => $hash]);
                $results[$name] = 'updated';
            } catch (client_exception $e) {
                stats::update($name, ['lasterror' => $e->get_reason(),
                    'lasterrortime' => \core\di::get(\core\clock::class)->time()]);
                $results[$name] = $e->get_reason();
            }
        }
        return $results;
    }

    /**
     * Forgets what was sent, so that the next run of ship_queue sends every template and policy again.
     *
     * Called when the settings change; it does not talk to the cluster, so saving the settings stays fast.
     *
     * @return void
     */
    public static function invalidate(): void {
        global $DB;
        $DB->set_field('tool_opensearch_stream', 'schemahash', null);
        \tool_opensearch\api::reset();
    }

    /**
     * Whether every stream has its current template and policy in the cluster (as far as this site knows).
     *
     * @return bool
     */
    public function in_sync(): bool {
        $stats = stats::all();
        foreach (registry::streams() as $name => $stream) {
            if (!isset($stats[$name]) || $stats[$name]->schemahash !== $this->hash($stream)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Adds new fields to the backing indices of an existing data stream, so that documents with them are accepted
     * before the next rollover. Does nothing when the data stream does not exist yet.
     *
     * @param stream $stream Stream.
     * @return void
     * @throws client_exception When the mapping is incompatible (a field changed type: needs a rollover).
     */
    private function put_mapping(stream $stream): void {
        [$status, $body] = $this->client->request(
            'PUT',
            '/' . $this->config->data_stream($stream->name) . '/_mapping',
            ['properties' => $stream->mapping_properties()]
        );
        if ($status !== 404 && ($status < 200 || $status > 299)) {
            throw client::status_error($status, $body);
        }
    }

    /**
     * Creates or replaces the ISM policy and applies it to the existing backing indices.
     *
     * @param stream $stream Stream.
     * @return void
     * @throws client_exception
     */
    private function put_policy(stream $stream): void {
        $id = $this->policy_id($stream);
        [$status, $current] = $this->client->request('GET', '/_plugins/_ism/policies/' . $id);
        $query = '';
        if ($status === 200 && isset($current['_seq_no'], $current['_primary_term'])) {
            $query = '?if_seq_no=' . (int) $current['_seq_no'] . '&if_primary_term=' . (int) $current['_primary_term'];
        } else if ($status !== 404) {
            throw client::status_error($status, $current);
        }
        $this->client->call('PUT', '/_plugins/_ism/policies/' . $id . $query, $this->policy($stream));
        // The ism_template only applies to indices created after it: attach the policy to existing backing indices
        // without one (sites upgraded from 0.1.0), and move the managed ones to the new version. Failures here
        // (no backing index yet, already managed) are expected and ignored.
        $indices = '/.ds-' . $this->config->data_stream($stream->name) . '-*';
        $this->client->request('POST', '/_plugins/_ism/add' . $indices, ['policy_id' => $id]);
        if ($query !== '') {
            $this->client->request('POST', '/_plugins/_ism/change_policy' . $indices, ['policy_id' => $id]);
        }
    }
}
