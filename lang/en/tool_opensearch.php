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
 * Strings.
 *
 * @package    tool_opensearch
 * @copyright  2026 Moodle Observability contributors
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['auth'] = 'Authentication';
$string['auth_basic'] = 'Basic (user name and password)';
$string['auth_desc'] = 'How Moodle authenticates to the cluster. Give the user a role limited to the data streams of this site (see the documentation).';
$string['auth_mtls'] = 'Client certificate (mTLS)';
$string['auth_none'] = 'None';
$string['authheading'] = 'Authentication and TLS';
$string['batchbytes'] = 'Bytes per batch';
$string['batchbytes_desc'] = 'Maximum size of each _bulk request, in bytes.';
$string['batchdocs'] = 'Documents per batch';
$string['batchdocs_desc'] = 'Maximum documents in each _bulk request.';
$string['cafile'] = 'CA bundle';
$string['cafile_desc'] = 'Path, on the Moodle server, of the certificate authority that signed the cluster certificate. Empty uses the system bundle.';
$string['clientcert'] = 'Client certificate';
$string['clientcert_desc'] = 'Path of the client certificate (PEM), for mTLS.';
$string['clientkey'] = 'Client key';
$string['clientkey_desc'] = 'Path of the client private key (PEM), for mTLS. The key itself is never stored in the database.';
$string['connecttimeout'] = 'Connection timeout';
$string['connecttimeout_desc'] = 'Seconds to wait for a connection.';
$string['enabled'] = 'Enabled';
$string['enabled_desc'] = 'Queue the documents of the streams and send them to OpenSearch. When disabled, documents are discarded by the producers.';
$string['error_401'] = 'Authentication failed (401): check the user name and the password.';
$string['error_403'] = 'Permission denied (403): the user needs the role described in the documentation. {$a}';
$string['error_client'] = 'OpenSearch: {$a}';
$string['error_disabled'] = 'Sending to OpenSearch is disabled or has no hosts.';
$string['error_dns'] = 'The host name {$a} could not be resolved (DNS).';
$string['error_nohosts'] = 'No OpenSearch host is configured.';
$string['error_password'] = 'The stored password cannot be decrypted: the encryption key of this site changed. Type the password again.';
$string['error_queuefull'] = 'The queue is full: new documents are being discarded.';
$string['error_refused'] = 'Connection refused by {$a}.';
$string['error_status'] = 'OpenSearch answered with HTTP {$a->status}. {$a->reason}';
$string['error_timeout'] = 'Timeout while talking to {$a}.';
$string['error_tls'] = 'TLS error with {$a}: check the CA bundle, the client certificate and "Verify the certificate".';
$string['error_transport'] = 'Error while talking to {$a->host}: {$a->message}';
$string['error_unknownstream'] = 'Unknown stream "{$a}": no installed plugin declares it.';
$string['hosts'] = 'Hosts';
$string['hosts_desc'] = 'URL of one or more nodes, separated by commas (for example https://os1:9200,https://os2:9200). The next one is tried when a node does not answer.';
$string['indexheading'] = 'Data streams';
$string['password'] = 'Password';
$string['password_desc'] = 'Stored encrypted with the site key (moodledata/secret/key), which must be the same on every node.';
$string['pluginname'] = 'OpenSearch';
$string['prefix'] = 'Prefix';
$string['prefix_desc'] = 'Start of the names of the data streams, templates and policies of this site, so that sites sharing a cluster do not mix. Lower case letters, digits, "-" and "_". Empty uses {$a}.';
$string['privacy:metadata:opensearch'] = 'Documents produced by other plugins are sent to the OpenSearch cluster configured by the administrator. Each producer describes the personal data it sends.';
$string['privacy:metadata:opensearch:payload'] = 'The document, as produced by the plugin that owns the stream.';
$string['privacy:metadata:tool_opensearch_queue'] = 'Documents waiting to be sent to OpenSearch, kept only until they are sent.';
$string['privacy:metadata:tool_opensearch_queue:payload'] = 'The document, as produced by the plugin that owns the stream.';
$string['privacy:metadata:tool_opensearch_queue:stream'] = 'The stream of the document.';
$string['privacy:metadata:tool_opensearch_queue:timecreated'] = 'When the document was queued.';
$string['queueheading'] = 'Queue';
$string['queuemax'] = 'Queue limit';
$string['queuemax_desc'] = 'Maximum documents waiting in the queue. Above it, new documents are discarded (and counted), so that Moodle keeps working while OpenSearch is down for a long time.';
$string['replicas'] = 'Replicas';
$string['replicas_desc'] = 'Replicas of each backing index (0 for a single node cluster).';
$string['retention'] = 'Retention of {$a}';
$string['retention_desc'] = '{$a->description} Days kept in <code>{$a->datastream}</code> ({$a->component}). Empty uses {$a->days} days.';
$string['settings'] = 'Settings';
$string['shards'] = 'Primary shards';
$string['shards_desc'] = 'Primary shards of each backing index.';
$string['source'] = 'Configuration';
$string['source_desc'] = 'Environment variables win over these settings. Set from the environment: {$a->variables}. <a href="{$a->test}">Test the connection</a>.';
$string['stream'] = 'Stream';
$string['stream_alerts'] = 'Alerts raised and resolved by tool_taskmonitor, kept for a long time.';
$string['streamcomponent'] = 'Plugin';
$string['streamdropped'] = 'Discarded (queue full)';
$string['streamfailed'] = 'Rejected';
$string['streamlasterror'] = 'Last error';
$string['streamlastsuccess'] = 'Last sent';
$string['streamqueued'] = 'Queued';
$string['streamretention'] = 'Retention (days)';
$string['streamsent'] = 'Sent';
$string['streamsheading'] = 'Streams';
$string['streamsheading_desc'] = 'Streams declared by the installed plugins. Each one is a data stream with its own retention.';
$string['streamsheading_none'] = 'No installed plugin declares a stream yet.';
$string['streamunknown'] = 'Not declared';
$string['task_ship_queue'] = 'Send queued documents to OpenSearch';
$string['task_sync_schema'] = 'Update OpenSearch index templates and retention policies';
$string['test'] = 'Connection test';
$string['test_check'] = 'Check';
$string['test_connection'] = 'Connection';
$string['test_connection_ok'] = '{$a->distribution} {$a->version}, cluster {$a->cluster}';
$string['test_disabled'] = 'Sending is disabled: the test still runs with the configured hosts.';
$string['test_distribution'] = 'Distribution';
$string['test_distribution_bad'] = 'The cluster is not OpenSearch: data streams and ISM policies may not work.';
$string['test_failed'] = 'Some checks failed.';
$string['test_health'] = 'Cluster health';
$string['test_health_ok'] = 'Status {$a}';
$string['test_ism'] = 'ISM plugin';
$string['test_ism_ok'] = 'Available: retention policies can be created.';
$string['test_passed'] = 'Every check passed.';
$string['test_result'] = 'Result';
$string['test_results'] = 'Results';
$string['test_run'] = 'Run the test';
$string['test_time'] = 'Time';
$string['test_write'] = 'Write permission';
$string['test_write_ok'] = 'A document was created and deleted in {$a}.';
$string['timeout'] = 'Timeout';
$string['timeout_desc'] = 'Seconds to wait for an answer in the tasks. Requests made during a page (index_now) wait 2 seconds at most.';
$string['username'] = 'User name';
$string['verifytls'] = 'Verify the certificate';
$string['verifytls_desc'] = 'Check the certificate of the cluster. Disable only in test environments.';
