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

/**
 * Admin settings.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category('tool_opensearch_category', get_string('pluginname', 'tool_opensearch')));
    $settings = new admin_settingpage('tool_opensearch', get_string('settings', 'tool_opensearch'));
    $ADMIN->add('tool_opensearch_category', $settings);
    $ADMIN->add('tool_opensearch_category', new admin_externalpage(
        'tool_opensearch_test',
        get_string('test', 'tool_opensearch'),
        new moodle_url('/admin/tool/opensearch/test.php')
    ));

    if ($ADMIN->fulltree) {
        $config = \tool_opensearch\local\config::load();
        $fromenv = array_keys(array_filter($config->sources, fn(string $source): bool => $source === 'environment'));
        $settings->add(new admin_setting_heading(
            'tool_opensearch/source',
            get_string('source', 'tool_opensearch'),
            get_string('source_desc', 'tool_opensearch', (object) [
                'variables' => $fromenv ? implode(', ', array_map(
                    fn(string $key): string => \tool_opensearch\local\config::ENV_PREFIX . strtoupper($key),
                    $fromenv
                )) : get_string('none'),
                'test' => (new moodle_url('/admin/tool/opensearch/test.php'))->out(),
            ])
        ));
        $invalidate = [\tool_opensearch\local\schema_manager::class, 'invalidate'];
        $add = function (admin_setting $setting, bool $schema = false) use ($settings, $invalidate): void {
            if ($schema) {
                $setting->set_updatedcallback($invalidate);
            }
            $settings->add($setting);
        };

        $add(new admin_setting_configcheckbox(
            'tool_opensearch/enabled',
            get_string('enabled', 'tool_opensearch'),
            get_string('enabled_desc', 'tool_opensearch'),
            0
        ), true);
        $add(new admin_setting_configtext(
            'tool_opensearch/hosts',
            get_string('hosts', 'tool_opensearch'),
            get_string('hosts_desc', 'tool_opensearch'),
            '',
            PARAM_TEXT
        ));

        $add(new admin_setting_heading('tool_opensearch/authheading', get_string('authheading', 'tool_opensearch'), ''));
        $add(new admin_setting_configselect(
            'tool_opensearch/auth',
            get_string('auth', 'tool_opensearch'),
            get_string('auth_desc', 'tool_opensearch'),
            'basic',
            [
                'none' => get_string('auth_none', 'tool_opensearch'),
                'basic' => get_string('auth_basic', 'tool_opensearch'),
                'mtls' => get_string('auth_mtls', 'tool_opensearch'),
            ]
        ));
        $add(new admin_setting_configtext(
            'tool_opensearch/username',
            get_string('username', 'tool_opensearch'),
            '',
            '',
            PARAM_TEXT
        ));
        $add(new admin_setting_encryptedpassword(
            'tool_opensearch/password',
            get_string('password', 'tool_opensearch'),
            get_string('password_desc', 'tool_opensearch')
        ));
        foreach (['cafile', 'clientcert', 'clientkey'] as $name) {
            $add(new admin_setting_configtext(
                'tool_opensearch/' . $name,
                get_string($name, 'tool_opensearch'),
                get_string($name . '_desc', 'tool_opensearch'),
                '',
                PARAM_PATH
            ));
        }
        $add(new admin_setting_configcheckbox(
            'tool_opensearch/verifytls',
            get_string('verifytls', 'tool_opensearch'),
            get_string('verifytls_desc', 'tool_opensearch'),
            1
        ));
        $add(new admin_setting_configtext(
            'tool_opensearch/connecttimeout',
            get_string('connecttimeout', 'tool_opensearch'),
            get_string('connecttimeout_desc', 'tool_opensearch'),
            '2',
            PARAM_INT
        ));
        $add(new admin_setting_configtext(
            'tool_opensearch/timeout',
            get_string('timeout', 'tool_opensearch'),
            get_string('timeout_desc', 'tool_opensearch'),
            '10',
            PARAM_INT
        ));

        $add(new admin_setting_heading('tool_opensearch/indexheading', get_string('indexheading', 'tool_opensearch'), ''));
        $add(new admin_setting_configtext(
            'tool_opensearch/prefix',
            get_string('prefix', 'tool_opensearch'),
            get_string('prefix_desc', 'tool_opensearch', \tool_opensearch\local\config::default_prefix()),
            '',
            PARAM_ALPHANUMEXT
        ), true);
        foreach (['shards' => '1', 'replicas' => '1'] as $name => $default) {
            $add(new admin_setting_configtext(
                'tool_opensearch/' . $name,
                get_string($name, 'tool_opensearch'),
                get_string($name . '_desc', 'tool_opensearch'),
                $default,
                PARAM_INT
            ), true);
        }

        $add(new admin_setting_heading('tool_opensearch/queueheading', get_string('queueheading', 'tool_opensearch'), ''));
        foreach (['batchdocs' => '5000', 'batchbytes' => '8388608', 'queuemax' => '2000000'] as $name => $default) {
            $add(new admin_setting_configtext(
                'tool_opensearch/' . $name,
                get_string($name, 'tool_opensearch'),
                get_string($name . '_desc', 'tool_opensearch'),
                $default,
                PARAM_INT
            ));
        }

        $streams = \tool_opensearch\local\registry::streams();
        $add(new admin_setting_heading(
            'tool_opensearch/streamsheading',
            get_string('streamsheading', 'tool_opensearch'),
            get_string($streams ? 'streamsheading_desc' : 'streamsheading_none', 'tool_opensearch')
        ));
        foreach ($streams as $name => $stream) {
            $add(new admin_setting_configtext(
                'tool_opensearch/retention_' . $name,
                get_string('retention', 'tool_opensearch', $name),
                get_string('retention_desc', 'tool_opensearch', (object) [
                    'component' => $stream->component,
                    'datastream' => $config->data_stream($name),
                    'days' => $stream->retentiondays,
                    'description' => $stream->description,
                ]),
                '',
                PARAM_INT
            ), true);
        }
    }
}
