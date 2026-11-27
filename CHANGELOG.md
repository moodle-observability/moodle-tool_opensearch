# Changelog

## 0.2.0 - 2026-11-26

- Fix: `_bulk` failed with guzzlehttp/psr7 2.8 (Moodle 5.3.1+): the client now sets `Content-Length` as a string.
- `alerts` stream: with tool_taskmonitor installed, every alert raised or resolved is sent to OpenSearch (365 days
  of retention by default), through the `alert_raised` hook.

## 0.1.2 - 2026-11-23

- Every document gets `moodle.shortname`, the short name of the site, to filter sites sharing a cluster.
- New fields of a stream reach its existing data stream at once (`PUT _mapping`), instead of being rejected
  until the next rollover.
- Example stack and dashboards for OpenSearch Dashboards and Grafana in `examples/`, with the filters of the
  Moodle logs report (documentation: Dashboards).

## 0.1.1 - 2026-11-23

- Fix: the retention (ISM) policy was not attached to the backing indices of the data streams, so documents
  were never deleted. The `ism_template` now matches the data stream name, and the next sync attaches the policy
  to the backing indices created by 0.1.0.

## 0.1.0 - 2026-11-23

First release (alpha).

- Connection to one or more OpenSearch nodes with basic authentication or a client certificate (mTLS), custom CA
  and TLS verification; settings by `MOODLE_OPENSEARCH_*` environment variables or the settings page, password
  encrypted with the site key.
- `register_streams` hook for the plugins that send documents; `api::index()` buffers documents and writes them
  to a queue in the database at the end of the request (no HTTP call during a page); `api::index_now()`,
  `api::search()` and `api::delete_by_query()`.
- `ship_queue` task: `_bulk` every minute, per item handling, exponential backoff with jitter, queue limit.
- Data streams with strict mappings and `best_compression`, ISM retention policies per stream, kept in sync by the
  `ship_queue` and `sync_schema` tasks and `cli/sync.php`.
- Connection test page and `cli/test.php`: version, cluster health, ISM plugin, write permission.
- Metrics for tool_prometheus: queue size and age, sent, rejected and discarded documents, last success.
