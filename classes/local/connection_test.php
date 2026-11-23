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
 * Connection test: cluster version, health, ISM plugin, write permission and response time.
 *
 * The write check creates a document in the "<prefix>-selftest" index and deletes it.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class connection_test {
    /** @var config Configuration. */
    private config $config;

    /** @var client Client. */
    private client $client;

    /**
     * Constructor.
     *
     * @param config $config Configuration.
     */
    public function __construct(config $config) {
        $this->config = $config;
        $this->client = new client($config);
    }

    /**
     * Runs the checks; stops at the first one that cannot reach the cluster.
     *
     * @return array List of checks: name, ok, message, ms.
     */
    public function run(): array {
        $checks = [];
        if (!$this->config->hosts) {
            return [$this->result('connection', false, get_string('error_nohosts', 'tool_opensearch'), 0)];
        }

        $info = $this->check(
            $checks,
            'connection',
            fn(): array => $this->client->call('GET', '/'),
            fn(array $body): string => get_string('test_connection_ok', 'tool_opensearch', (object) [
                'distribution' => $body['version']['distribution'] ?? 'elasticsearch',
                'version' => $body['version']['number'] ?? '?',
                'cluster' => $body['cluster_name'] ?? '?',
            ])
        );
        if ($info === null) {
            return $checks;
        }
        if (($info['version']['distribution'] ?? '') !== 'opensearch') {
            $checks[] = $this->result('distribution', false, get_string('test_distribution_bad', 'tool_opensearch'), 0);
        }

        $this->check(
            $checks,
            'health',
            fn(): array => $this->client->call('GET', '/_cluster/health'),
            fn(array $body): string => get_string('test_health_ok', 'tool_opensearch', $body['status'] ?? '?'),
            fn(array $body): bool => ($body['status'] ?? '') !== 'red'
        );

        $this->check(
            $checks,
            'ism',
            fn(): array => $this->client->call('GET', '/_plugins/_ism/policies?size=1'),
            fn(array $body): string => get_string('test_ism_ok', 'tool_opensearch')
        );

        $index = '/' . $this->config->prefix . '-selftest';
        $this->check($checks, 'write', function () use ($index): array {
            $created = $this->client->call(
                'POST',
                $index . '/_doc?refresh=true',
                ['@timestamp' => gmdate('c'), 'check' => 'tool_opensearch']
            );
            $this->client->call('DELETE', $index . '/_doc/' . rawurlencode((string) ($created['_id'] ?? '')));
            return $created;
        }, fn(array $body): string => get_string('test_write_ok', 'tool_opensearch', ltrim($index, '/')));

        return $checks;
    }

    /**
     * Runs one check and records the result.
     *
     * @param array $checks Results so far.
     * @param string $name Check name.
     * @param callable $call Request; returns the decoded body.
     * @param callable $message Message of a success, from the body.
     * @param callable|null $ok Whether the body is a success (default: always).
     * @return array|null Body, or null when the check failed.
     */
    private function check(array &$checks, string $name, callable $call, callable $message, ?callable $ok = null): ?array {
        $start = microtime(true);
        try {
            $body = $call();
        } catch (client_exception $e) {
            $checks[] = $this->result($name, false, $e->get_reason(), $start);
            return null;
        }
        $passed = $ok === null || $ok($body);
        $checks[] = $this->result($name, $passed, $message($body), $start);
        return $passed ? $body : null;
    }

    /**
     * Result of a check.
     *
     * @param string $name Check name.
     * @param bool $ok Whether it passed.
     * @param string $message Message.
     * @param float $start Start time (0: no time).
     * @return array
     */
    private function result(string $name, bool $ok, string $message, float $start): array {
        return [
            'name' => get_string('test_' . $name, 'tool_opensearch'),
            'ok' => $ok,
            'message' => $message,
            'ms' => $start > 0 ? (int) round((microtime(true) - $start) * 1000) : null,
        ];
    }
}
