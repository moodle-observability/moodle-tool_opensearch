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
use tool_opensearch\local\config;

/**
 * Tests of the configuration.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(config::class)]
final class config_test extends \advanced_testcase {
    /**
     * Defaults: disabled, prefix from wwwroot.
     */
    public function test_defaults(): void {
        $config = config::load([], new \stdClass());
        $this->assertFalse($config->enabled);
        $this->assertSame([], $config->hosts);
        $this->assertSame(config::default_prefix(), $config->prefix);
        $this->assertMatchesRegularExpression('/^moodle-[0-9a-f]{8}$/', $config->prefix);
        $this->assertSame(5000, $config->batchdocs);
        $this->assertSame('default', $config->sources['hosts']);
    }

    /**
     * Settings are read and the password is decrypted.
     */
    public function test_settings(): void {
        $settings = (object) [
            'enabled' => '1',
            'hosts' => 'https://os1:9200/, ftp://bad, https://os2:9200',
            'auth' => 'basic',
            'username' => 'moodle',
            'password' => \core\encryption::encrypt('s3cret'),
            'prefix' => 'prod',
            'replicas' => '0',
            'batchdocs' => 'x',
        ];
        $config = config::load([], $settings);
        $this->assertTrue($config->enabled);
        $this->assertSame(['https://os1:9200', 'https://os2:9200'], $config->hosts);
        $this->assertSame('s3cret', $config->password);
        $this->assertSame('prod', $config->prefix);
        $this->assertSame('prod-events', $config->data_stream('events'));
        $this->assertSame(0, $config->replicas);
        $this->assertSame(5000, $config->batchdocs);
        $this->assertSame('', $config->error);
    }

    /**
     * Environment variables win key by key; hosts in the environment enable sending.
     */
    public function test_environment_wins(): void {
        $settings = (object) ['enabled' => '0', 'hosts' => 'https://settings:9200', 'username' => 'settings'];
        $config = config::load([
            'MOODLE_OPENSEARCH_HOSTS' => 'https://env:9200',
            'MOODLE_OPENSEARCH_PASSWORD' => 'plain',
        ], $settings);
        $this->assertTrue($config->enabled);
        $this->assertSame(['https://env:9200'], $config->hosts);
        $this->assertSame('settings', $config->username);
        $this->assertSame('plain', $config->password);
        $this->assertSame('environment', $config->sources['hosts']);
        $this->assertSame('settings', $config->sources['username']);

        $config = config::load(
            ['MOODLE_OPENSEARCH_HOSTS' => 'https://env:9200', 'MOODLE_OPENSEARCH_ENABLED' => 'false'],
            new \stdClass()
        );
        $this->assertFalse($config->enabled);
    }

    /**
     * A password encrypted with another key becomes empty, with a clear error.
     */
    public function test_undecryptable_password(): void {
        $config = config::load([], (object) ['password' => 'not-encrypted']);
        $this->assertSame('', $config->password);
        $this->assertNotSame('', $config->error);
    }

    /**
     * Invalid prefixes fall back to the default.
     */
    public function test_prefix_validation(): void {
        $this->assertTrue(config::valid_prefix('moodle-v3_dev'));
        $this->assertFalse(config::valid_prefix('Moodle'));
        $this->assertFalse(config::valid_prefix('-x'));
        $this->assertFalse(config::valid_prefix(''));
        $this->assertSame(config::default_prefix(), config::load([], (object) ['prefix' => '_bad'])->prefix);
    }
}
