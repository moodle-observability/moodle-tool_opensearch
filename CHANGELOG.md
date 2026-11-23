# Changelog

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
