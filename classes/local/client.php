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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;

/**
 * Small OpenSearch REST client on top of the core HTTP client (Guzzle).
 *
 * The cluster usually lives in an internal network, which the core blocks by default
 * ($CFG->curlsecurityblockedhosts). The client only ever talks to the hosts configured by the administrator, so it
 * turns that check off ("ignoresecurity"); redirects are not followed.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client {
    /** Timeout of requests made during a web request, in seconds. */
    public const SHORT_TIMEOUT = 2;

    /** @var callable|null Guzzle handler used instead of the network in tests. */
    private static $mockhandler = null;

    /** @var config Configuration. */
    private config $config;

    /** @var \core\http_client HTTP client. */
    private \core\http_client $http;

    /** @var int Index of the host tried first. */
    private int $current = 0;

    /**
     * Constructor.
     *
     * @param config $config Configuration.
     */
    public function __construct(config $config) {
        $this->config = $config;
        $options = [
            'ignoresecurity' => true,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::ALLOW_REDIRECTS => false,
            RequestOptions::CONNECT_TIMEOUT => $config->connecttimeout,
            RequestOptions::TIMEOUT => $config->timeout,
            RequestOptions::VERIFY => $config->verifytls ? ($config->cafile !== '' ? $config->cafile : true) : false,
            'headers' => ['Accept' => 'application/json'],
        ];
        if ($config->auth === 'basic' && $config->username !== '') {
            $options[RequestOptions::AUTH] = [$config->username, $config->password];
        }
        if ($config->auth === 'mtls') {
            $options[RequestOptions::CERT] = $config->clientcert;
            $options[RequestOptions::SSL_KEY] = $config->clientkey;
        }
        if (self::$mockhandler !== null) {
            $options['mock'] = self::$mockhandler;
        }
        $this->http = new \core\http_client($options);
        $this->current = $config->hosts ? random_int(0, count($config->hosts) - 1) : 0;
    }

    /**
     * Replaces the network with a Guzzle handler (MockHandler, possibly wrapped by Middleware::history()).
     *
     * @param callable|null $handler Handler; null restores the network.
     * @return void
     */
    public static function phpunit_set_handler(?callable $handler): void {
        if (!defined('PHPUNIT_TEST') || !PHPUNIT_TEST) {
            throw new \coding_exception('Only for PHPUnit tests');
        }
        self::$mockhandler = $handler;
    }

    /**
     * Sends a request, trying the next host when one cannot be reached.
     *
     * @param string $method HTTP method.
     * @param string $path Path and query, starting with "/".
     * @param array|string|null $body Body: arrays are sent as JSON, strings as NDJSON.
     * @param bool $short Whether to use the short timeout (web requests).
     * @return array [HTTP status, decoded JSON body (empty array if none)].
     * @throws client_exception When no host answered.
     */
    public function request(string $method, string $path, array|string|null $body = null, bool $short = false): array {
        if (!$this->config->hosts) {
            throw new client_exception(get_string('error_nohosts', 'tool_opensearch'), false);
        }
        $options = [];
        if ($short) {
            $options[RequestOptions::TIMEOUT] = self::SHORT_TIMEOUT;
            $options[RequestOptions::CONNECT_TIMEOUT] = min($this->config->connecttimeout, self::SHORT_TIMEOUT);
        }
        if (is_array($body)) {
            $options[RequestOptions::BODY] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $options['headers'] = ['Content-Type' => 'application/json'];
        } else if (is_string($body)) {
            $options[RequestOptions::BODY] = $body;
            $options['headers'] = ['Content-Type' => 'application/x-ndjson'];
        }
        if (isset($options[RequestOptions::BODY])) {
            // Set here, as a string: guzzlehttp/psr7 2.8 rejects the integer that PrepareBodyMiddleware would set.
            $options['headers']['Content-Length'] = (string) strlen($options[RequestOptions::BODY]);
        }

        $count = count($this->config->hosts);
        $last = null;
        for ($i = 0; $i < $count; $i++) {
            $host = $this->config->hosts[($this->current + $i) % $count];
            try {
                $response = $this->http->request($method, $host . $path, $options);
                $this->current = ($this->current + $i) % $count;
                $decoded = json_decode((string) $response->getBody(), true);
                return [$response->getStatusCode(), is_array($decoded) ? $decoded : []];
            } catch (ConnectException $e) {
                // Unreachable host: try the next one.
                $last = self::describe($e);
            } catch (RequestException $e) {
                $last = self::describe($e);
                break;
            } catch (GuzzleException $e) {
                $last = new client_exception($e->getMessage(), true);
                break;
            }
        }
        throw $last;
    }

    /**
     * Sends a request and fails when the status is not 2xx.
     *
     * @param string $method HTTP method.
     * @param string $path Path and query, starting with "/".
     * @param array|string|null $body Body.
     * @param bool $short Whether to use the short timeout.
     * @return array Decoded JSON body.
     * @throws client_exception On transport errors and non 2xx status.
     */
    public function call(string $method, string $path, array|string|null $body = null, bool $short = false): array {
        [$status, $decoded] = $this->request($method, $path, $body, $short);
        if ($status < 200 || $status > 299) {
            throw self::status_error($status, $decoded);
        }
        return $decoded;
    }

    /**
     * Error for a non 2xx answer, with the reason given by OpenSearch.
     *
     * @param int $status HTTP status.
     * @param array $body Decoded body.
     * @return client_exception
     */
    public static function status_error(int $status, array $body): client_exception {
        $reason = self::reason($body);
        $message = match (true) {
            $status === 401 => get_string('error_401', 'tool_opensearch'),
            $status === 403 => get_string('error_403', 'tool_opensearch', $reason),
            default => get_string('error_status', 'tool_opensearch', (object) ['status' => $status, 'reason' => $reason]),
        };
        return new client_exception($message, $status === 429 || $status >= 500, $status);
    }

    /**
     * Reason of an OpenSearch error body ("type: reason"), shortened.
     *
     * @param array $body Decoded body.
     * @return string
     */
    public static function reason(array $body): string {
        $error = $body['error'] ?? null;
        if (is_array($error)) {
            $reason = trim(($error['type'] ?? '') . ': ' . ($error['reason'] ?? ''), ': ');
        } else {
            $reason = is_string($error) ? $error : '';
        }
        return \core_text::substr($reason, 0, 500);
    }

    /**
     * Clear message for transport errors (DNS, TLS, timeout, connection refused).
     *
     * @param RequestException|ConnectException $e Exception.
     * @return client_exception
     */
    private static function describe(RequestException|ConnectException $e): client_exception {
        $errno = (int) ($e->getHandlerContext()['errno'] ?? 0);
        $host = $e->getRequest()->getUri()->getHost();
        $key = match ($errno) {
            6 => 'error_dns',
            7 => 'error_refused',
            28 => 'error_timeout',
            35, 51, 53, 54, 58, 59, 60, 64, 66, 77, 80, 82, 83, 90, 91 => 'error_tls',
            default => '',
        };
        if ($key === '') {
            return new client_exception(get_string(
                'error_transport',
                'tool_opensearch',
                (object) ['host' => $host, 'message' => $e->getMessage()]
            ), true);
        }
        return new client_exception(get_string($key, 'tool_opensearch', $host), $key !== 'error_tls' && $key !== 'error_dns');
    }
}
