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

use tool_opensearch\local\client;
use tool_opensearch\local\client_exception;
use tool_opensearch\local\config;
use tool_opensearch\local\queue;
use tool_opensearch\local\registry;

/**
 * Public API for the plugins that send documents to OpenSearch.
 *
 * Declare the stream with the \tool_opensearch\hook\register_streams hook, then call api::index(). Documents are
 * queued in the database and sent by the ship_queue task: index() never waits for the cluster and never throws.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class api {
    /** @var config|null Configuration of this request. */
    private static ?config $config = null;

    /** @var string|null Short name of the site, for this request. */
    private static ?string $shortname = null;

    /**
     * Whether sending is enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        return self::config()->enabled;
    }

    /**
     * Queues a document. Does nothing when sending is disabled; unknown streams are reported with debugging().
     *
     * "@timestamp" (ISO 8601) is added when missing, and "moodle.site" always receives the prefix of the site.
     *
     * @param string $stream Stream name, declared in register_streams.
     * @param array $doc Document, matching the mapping of the stream.
     * @return void
     */
    public static function index(string $stream, array $doc): void {
        $json = self::encode($stream, $doc);
        if ($json !== null) {
            queue::add($stream, $json);
        }
    }

    /**
     * Sends a document now (short timeout), and queues it when that fails. For low volumes, such as alerts.
     *
     * @param string $stream Stream name, declared in register_streams.
     * @param array $doc Document.
     * @return bool Whether the document was accepted by OpenSearch now (false: queued, disabled or invalid).
     */
    public static function index_now(string $stream, array $doc): bool {
        $json = self::encode($stream, $doc);
        if ($json === null) {
            return false;
        }
        $config = self::config();
        $body = json_encode(['create' => ['_index' => $config->data_stream($stream)]]) . "\n" . $json . "\n";
        try {
            [$status, $response] = (new client($config))->request('POST', '/_bulk', $body, true);
            $item = $response['items'][0]['create'] ?? [];
            if ($status === 200 && ($item['status'] ?? 0) >= 200 && ($item['status'] ?? 0) < 300) {
                local\stats::update($stream, ['lastsuccess' => \core\di::get(\core\clock::class)->time()], ['sent' => 1]);
                return true;
            }
        } catch (client_exception $e) {
            // Queued below.
            unset($e);
        }
        queue::add($stream, $json);
        return false;
    }

    /**
     * Searches the documents of a stream (for example to export the data of a user).
     *
     * @param string $stream Stream name.
     * @param array $query Body of _search (query, size, sort...).
     * @return array Decoded answer of OpenSearch.
     * @throws client_exception When the cluster cannot answer.
     */
    public static function search(string $stream, array $query): array {
        $config = self::require_stream($stream);
        return (new client($config))->call('POST', '/' . $config->data_stream($stream) . '/_search', $query);
    }

    /**
     * Deletes the documents of a stream matching a query (for example to delete the data of a user).
     *
     * @param string $stream Stream name.
     * @param array $query Query (the value of "query" in _delete_by_query).
     * @return int Deleted documents.
     * @throws client_exception When the cluster cannot answer.
     */
    public static function delete_by_query(string $stream, array $query): int {
        $config = self::require_stream($stream);
        $path = '/' . $config->data_stream($stream) . '/_delete_by_query?conflicts=proceed&refresh=true';
        $response = (new client($config))->call('POST', $path, ['query' => $query]);
        return (int) ($response['deleted'] ?? 0);
    }

    /**
     * Forgets the configuration and the streams of this request (tests, and after the settings change).
     *
     * @return void
     */
    public static function reset(): void {
        self::$config = null;
        self::$shortname = null;
        registry::reset();
    }

    /**
     * Configuration of this request.
     *
     * @return config
     */
    private static function config(): config {
        return self::$config ??= config::load();
    }

    /**
     * Short name of the site (front page course), which identifies the site in filters and dashboards.
     *
     * @return string Empty during install, before the site exists.
     */
    private static function site_shortname(): string {
        if (self::$shortname === null) {
            try {
                self::$shortname = (string) get_site()->shortname;
            } catch (\Throwable $e) {
                return '';
            }
        }
        return self::$shortname;
    }

    /**
     * Checks the stream and the configuration of search and deletion.
     *
     * @param string $stream Stream name.
     * @return config
     * @throws client_exception
     */
    private static function require_stream(string $stream): config {
        $config = self::config();
        if (!registry::get($stream)) {
            throw new client_exception(get_string('error_unknownstream', 'tool_opensearch', $stream), false);
        }
        if (!$config->enabled) {
            throw new client_exception(get_string('error_disabled', 'tool_opensearch'), false);
        }
        return $config;
    }

    /**
     * Validates and encodes a document.
     *
     * @param string $stream Stream name.
     * @param array $doc Document.
     * @return string|null JSON, or null when the document must not be sent.
     */
    private static function encode(string $stream, array $doc): ?string {
        $config = self::config();
        if (!$config->enabled) {
            return null;
        }
        if (!registry::get($stream)) {
            debugging(
                "tool_opensearch: unknown stream '{$stream}'; declare it with the register_streams hook.",
                DEBUG_DEVELOPER
            );
            return null;
        }
        if (!isset($doc['@timestamp'])) {
            $doc['@timestamp'] = \core\di::get(\core\clock::class)->now()
                ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.vP');
        }
        $doc['moodle'] = ['site' => $config->prefix, 'shortname' => self::site_shortname()]
            + (is_array($doc['moodle'] ?? null) ? $doc['moodle'] : []);
        try {
            return json_encode($doc, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (\JsonException $e) {
            debugging("tool_opensearch: document of '{$stream}' cannot be encoded: " . $e->getMessage(), DEBUG_DEVELOPER);
            return null;
        }
    }
}
