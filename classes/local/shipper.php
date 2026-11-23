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
 * Sends the queue to OpenSearch with _bulk, item by item: sent documents leave the queue, temporary failures wait
 * (exponential backoff with jitter) and documents rejected by the mapping are discarded and counted.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class shipper {
    /** First retry delay, in seconds. */
    public const BACKOFF_MIN = 30;

    /** Longest retry delay, in seconds. */
    public const BACKOFF_MAX = 1800;

    /** @var config Configuration. */
    private config $config;

    /** @var client Client. */
    private client $client;

    /** @var \core\clock Clock. */
    private \core\clock $clock;

    /** @var array Totals of this run: sent, failed, retried. */
    private array $totals = ['sent' => 0, 'failed' => 0, 'retried' => 0];

    /**
     * Constructor.
     *
     * @param config $config Configuration.
     * @param client|null $client Client (created from the configuration if null).
     */
    public function __construct(config $config, ?client $client = null) {
        $this->config = $config;
        $this->client = $client ?? new client($config);
        $this->clock = \core\di::get(\core\clock::class);
    }

    /**
     * Delay before the next attempt: 30 s, 1 min, 2 min... up to 30 min, with 20% of jitter.
     *
     * @param int $attempts Failed attempts so far (at least 1).
     * @return int Seconds.
     */
    public static function backoff(int $attempts): int {
        $delay = min(self::BACKOFF_MAX, self::BACKOFF_MIN * (2 ** min(max(0, $attempts - 1), 10)));
        return (int) round($delay * random_int(80, 120) / 100);
    }

    /**
     * Sends what is due, stream by stream, until the queue is empty or the time is up.
     *
     * @param int $maxseconds Time limit, so that runs of the task do not overlap.
     * @return array Totals: up (1 or 0), sent, failed, retried.
     */
    public function run(int $maxseconds = 50): array {
        global $DB;
        $deadline = $this->clock->time() + $maxseconds;
        $up = 1;
        try {
            $this->client->call('GET', '/');
        } catch (client_exception $e) {
            $up = 0;
            foreach (array_keys(registry::streams()) as $name) {
                stats::update($name, ['lasterror' => $e->get_reason(), 'lasterrortime' => $this->clock->time()]);
            }
        }
        set_config('up', $up, 'tool_opensearch');

        // Data streams need their template before the first document.
        $schema = new schema_manager($this->config, $this->client);
        $schemas = $up && !$schema->in_sync() ? $schema->sync() : [];

        if ($up) {
            $streams = $DB->get_fieldset_sql(
                'SELECT DISTINCT stream FROM {tool_opensearch_queue} WHERE nextattempt <= ?',
                [$this->clock->time()]
            );
            foreach ($streams as $stream) {
                if (isset($schemas[$stream]) && !in_array($schemas[$stream], ['unchanged', 'updated'], true)) {
                    // Without its template, the first document would create a plain index with a dynamic mapping.
                    continue;
                }
                while ($this->clock->time() < $deadline && $this->ship_batch($stream)) {
                    continue;
                }
            }
        }
        $this->refresh_counts();
        return ['up' => $up] + $this->totals;
    }

    /**
     * Sends one batch of a stream.
     *
     * @param string $stream Stream name.
     * @return bool Whether another batch may be sent now.
     */
    public function ship_batch(string $stream): bool {
        global $DB;
        $now = $this->clock->time();
        $rows = $DB->get_records_select(
            'tool_opensearch_queue',
            'stream = ? AND nextattempt <= ?',
            [$stream, $now],
            'id',
            'id, payload, attempts',
            0,
            $this->config->batchdocs
        );
        if (!$rows) {
            return false;
        }
        if (!registry::get($stream)) {
            // The plugin that declared the stream is gone: nothing can accept these documents.
            $DB->delete_records_list('tool_opensearch_queue', 'id', array_keys($rows));
            stats::update($stream, ['lasterror' => get_string('error_unknownstream', 'tool_opensearch', $stream),
                'lasterrortime' => $now], ['failed' => count($rows)]);
            $this->totals['failed'] += count($rows);
            return true;
        }

        $index = $this->config->data_stream($stream);
        $action = json_encode(['create' => ['_index' => $index]]) . "\n";
        $body = '';
        $batch = [];
        foreach ($rows as $row) {
            $line = $action . $row->payload . "\n";
            if ($batch && strlen($body) + strlen($line) > $this->config->batchbytes) {
                break;
            }
            $body .= $line;
            $batch[] = $row;
        }

        try {
            [$status, $response] = $this->client->request('POST', '/_bulk', $body);
        } catch (client_exception $e) {
            $this->retry($stream, $batch, $e->get_reason());
            return false;
        }
        if ($status < 200 || $status > 299 || !isset($response['items']) || count($response['items']) !== count($batch)) {
            $this->retry($stream, $batch, client::status_error($status, $response)->get_reason());
            return false;
        }

        $sent = [];
        $retry = [];
        $failed = [];
        $error = '';
        foreach ($response['items'] as $i => $item) {
            $result = $item['create'] ?? reset($item);
            $code = (int) ($result['status'] ?? 0);
            if (($code >= 200 && $code < 300) || $code === 409) {
                $sent[] = $batch[$i]->id;
            } else if ($code === 429 || $code >= 500 || $code === 0) {
                $retry[] = $batch[$i];
                $error = client::reason($result);
            } else {
                $failed[] = $batch[$i]->id;
                $error = client::reason($result);
            }
        }
        if ($sent || $failed) {
            $DB->delete_records_list('tool_opensearch_queue', 'id', array_merge($sent, $failed));
        }
        $fields = $sent ? ['lastsuccess' => $now] : [];
        if ($error !== '') {
            $fields += ['lasterror' => $error, 'lasterrortime' => $now];
        }
        stats::update($stream, $fields, ['sent' => count($sent), 'failed' => count($failed)]);
        $this->totals['sent'] += count($sent);
        $this->totals['failed'] += count($failed);
        if ($retry) {
            $this->retry($stream, $retry, $error);
            return false;
        }
        // More may be due: the batch was cut by size, or the query hit the document limit.
        return count($batch) < count($rows) || count($rows) >= $this->config->batchdocs;
    }

    /**
     * Keeps documents in the queue for a later attempt.
     *
     * @param string $stream Stream name.
     * @param \stdClass[] $rows Queue rows (id, attempts).
     * @param string $error Reason.
     * @return void
     */
    private function retry(string $stream, array $rows, string $error): void {
        global $DB;
        $attempts = 1 + max(array_map(fn(\stdClass $row): int => (int) $row->attempts, $rows));
        [$insql, $params] = $DB->get_in_or_equal(array_map(fn(\stdClass $row): int => (int) $row->id, $rows));
        $DB->set_field_select('tool_opensearch_queue', 'attempts', $attempts, "id {$insql}", $params);
        $DB->set_field_select(
            'tool_opensearch_queue',
            'nextattempt',
            $this->clock->time() + self::backoff($attempts),
            "id {$insql}",
            $params
        );
        stats::update($stream, ['lasterror' => $error, 'lasterrortime' => $this->clock->time()]);
        $this->totals['retried'] += count($rows);
    }

    /**
     * Stores the size and the age of the queue of each stream, for the metrics.
     *
     * @return void
     */
    private function refresh_counts(): void {
        global $DB;
        $counts = $DB->get_records_sql('SELECT stream, COUNT(1) AS queued, MIN(timecreated) AS oldest
                                          FROM {tool_opensearch_queue}
                                      GROUP BY stream');
        $names = array_unique(array_merge(array_keys(stats::all()), array_keys($counts)));
        foreach ($names as $name) {
            stats::update($name, [
                'queued' => (int) ($counts[$name]->queued ?? 0),
                'oldest' => (int) ($counts[$name]->oldest ?? 0),
            ]);
        }
    }
}
