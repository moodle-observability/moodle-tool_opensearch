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
 * Connection and sending configuration: environment variables win over the plugin settings, key by key.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class config {
    /** Prefix of the environment variables. */
    public const ENV_PREFIX = 'MOODLE_OPENSEARCH_';

    /** @var array Defaults of every key (setting name => value). */
    public const DEFAULTS = [
        'enabled' => 0,
        'hosts' => '',
        'auth' => 'basic',
        'username' => '',
        'password' => '',
        'cafile' => '',
        'clientcert' => '',
        'clientkey' => '',
        'verifytls' => 1,
        'connecttimeout' => 2,
        'timeout' => 10,
        'prefix' => '',
        'shards' => 1,
        'replicas' => 1,
        'batchdocs' => 5000,
        'batchbytes' => 8388608,
        'queuemax' => 2000000,
    ];

    /** @var bool Whether sending is enabled (and at least one host is configured). */
    public bool $enabled = false;

    /** @var string[] Base URLs of the cluster nodes, without the trailing slash. */
    public array $hosts = [];

    /** @var string none, basic or mtls. */
    public string $auth = 'basic';

    /** @var string User name (basic authentication). */
    public string $username = '';

    /** @var string Password (basic authentication), decrypted. */
    public string $password = '';

    /** @var string Path of the CA bundle (empty: system bundle). */
    public string $cafile = '';

    /** @var string Path of the client certificate (mTLS). */
    public string $clientcert = '';

    /** @var string Path of the client private key (mTLS). */
    public string $clientkey = '';

    /** @var bool Whether the server certificate is verified. */
    public bool $verifytls = true;

    /** @var int Connection timeout, in seconds. */
    public int $connecttimeout = 2;

    /** @var int Request timeout of the tasks, in seconds. */
    public int $timeout = 10;

    /** @var string Prefix of the data streams, templates and policies. */
    public string $prefix = '';

    /** @var int Primary shards of each data stream. */
    public int $shards = 1;

    /** @var int Replicas of each data stream. */
    public int $replicas = 1;

    /** @var int Maximum documents per _bulk request. */
    public int $batchdocs = 5000;

    /** @var int Maximum bytes per _bulk request. */
    public int $batchbytes = 8388608;

    /** @var int Maximum documents in the queue; new documents are dropped above it. */
    public int $queuemax = 2000000;

    /** @var array<string, string> Origin of each key: environment, settings or default. */
    public array $sources = [];

    /** @var string Problem found while reading the configuration (empty if none). */
    public string $error = '';

    /**
     * Configuration of the site.
     *
     * @param array|null $env Variables (name => value); null reads getenv().
     * @param \stdClass|null $settings Plugin settings; null reads get_config().
     * @return self
     */
    public static function load(?array $env = null, ?\stdClass $settings = null): self {
        $settings = $settings ?? (get_config('tool_opensearch') ?: new \stdClass());
        $config = new self();
        $raw = [];
        foreach (self::DEFAULTS as $key => $default) {
            $name = self::ENV_PREFIX . strtoupper($key);
            $value = $env === null ? getenv($name) : ($env[$name] ?? false);
            if ($value !== false && trim((string) $value) !== '') {
                $raw[$key] = trim((string) $value);
                $config->sources[$key] = 'environment';
            } else if (isset($settings->$key) && trim((string) $settings->$key) !== '') {
                $raw[$key] = trim((string) $settings->$key);
                $config->sources[$key] = 'settings';
                if ($key === 'password') {
                    $raw[$key] = self::decrypt($raw[$key], $config);
                }
            } else {
                $raw[$key] = (string) $default;
                $config->sources[$key] = 'default';
            }
        }

        $config->hosts = self::parse_hosts($raw['hosts']);
        // Hosts in the environment enable sending, unless MOODLE_OPENSEARCH_ENABLED says otherwise.
        $enabled = $config->sources['enabled'] !== 'environment' && $config->sources['hosts'] === 'environment'
            ? true : self::bool($raw['enabled']);
        $config->enabled = $enabled && $config->hosts !== [];
        $config->auth = in_array($raw['auth'], ['none', 'basic', 'mtls'], true) ? $raw['auth'] : 'basic';
        $config->username = $raw['username'];
        $config->password = $raw['password'];
        $config->cafile = $raw['cafile'];
        $config->clientcert = $raw['clientcert'];
        $config->clientkey = $raw['clientkey'];
        $config->verifytls = self::bool($raw['verifytls']);
        $config->connecttimeout = self::positive($raw['connecttimeout'], 2);
        $config->timeout = self::positive($raw['timeout'], 10);
        $prefix = strtolower($raw['prefix']);
        $config->prefix = self::valid_prefix($prefix) ? $prefix : self::default_prefix();
        $config->shards = self::positive($raw['shards'], 1);
        $config->replicas = max(0, (int) $raw['replicas']);
        $config->batchdocs = self::positive($raw['batchdocs'], 5000);
        $config->batchbytes = self::positive($raw['batchbytes'], 8388608);
        $config->queuemax = self::positive($raw['queuemax'], 2000000);
        return $config;
    }

    /**
     * Splits a comma separated list of URLs, keeping only http and https ones.
     *
     * @param string $hosts Host list.
     * @return string[]
     */
    public static function parse_hosts(string $hosts): array {
        $parsed = [];
        foreach (preg_split('/[\s,]+/', $hosts, -1, PREG_SPLIT_NO_EMPTY) as $host) {
            if (preg_match('#^https?://[^/\s]+#i', $host)) {
                $parsed[] = rtrim($host, '/');
            }
        }
        return array_values(array_unique($parsed));
    }

    /**
     * Whether a prefix is accepted by OpenSearch as the start of an index name.
     *
     * @param string $prefix Prefix.
     * @return bool
     */
    public static function valid_prefix(string $prefix): bool {
        return (bool) preg_match('/^[a-z0-9][a-z0-9_-]{0,39}$/', $prefix);
    }

    /**
     * Default prefix: "moodle-" and a short hash of wwwroot, so that sites sharing a cluster do not mix.
     *
     * @return string
     */
    public static function default_prefix(): string {
        global $CFG;
        return 'moodle-' . substr(sha1((string) $CFG->wwwroot), 0, 8);
    }

    /**
     * Name of a data stream (also used by its index template).
     *
     * @param string $stream Stream name.
     * @return string
     */
    public function data_stream(string $stream): string {
        return $this->prefix . '-' . $stream;
    }

    /**
     * Decrypts the stored password; an undecryptable value (another encryption key) becomes empty.
     *
     * @param string $value Stored value.
     * @param self $config Configuration that receives the error.
     * @return string
     */
    private static function decrypt(string $value, self $config): string {
        try {
            return \core\encryption::decrypt($value);
        } catch (\Throwable $e) {
            $config->error = get_string('error_password', 'tool_opensearch');
            return '';
        }
    }

    /**
     * Boolean from a setting or variable (1, true, yes, on).
     *
     * @param string $value Value.
     * @return bool
     */
    private static function bool(string $value): bool {
        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Positive integer, or the default.
     *
     * @param string $value Value.
     * @param int $default Default.
     * @return int
     */
    private static function positive(string $value, int $default): int {
        return ctype_digit($value) && (int) $value > 0 ? (int) $value : $default;
    }
}
