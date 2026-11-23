<img src="docs/logo/logo.svg" alt="moodle-tool_opensearch logo" width="96" align="right" />

# moodle-tool_opensearch

[![Moodle Plugin CI](https://github.com/moodle-observability/moodle-tool_opensearch/actions/workflows/ci.yml/badge.svg)](https://github.com/moodle-observability/moodle-tool_opensearch/actions/workflows/ci.yml)
[![Documentation](https://github.com/moodle-observability/moodle-tool_opensearch/actions/workflows/docs.yml/badge.svg)](https://github.com/moodle-observability/moodle-tool_opensearch/actions/workflows/docs.yml)

OpenSearch connection, queue and bulk shipping for Moodle (`tool_opensearch`). Base plugin that sends documents from Moodle plugins to OpenSearch: connection, authentication, a queue in the database, bulk shipping with retries, data streams with retention (ISM) and metrics. On its own it stores nothing: other plugins, such as `logstore_opensearch`, produce the documents.

**Status:** 0.1.0 (alpha). Download it from the [releases](https://github.com/moodle-observability/moodle-tool_opensearch/releases).

Example stack and dashboards (OpenSearch Dashboards and Grafana) with the filters of the Moodle logs report: [`examples/`](examples).

Documentation (English and Brazilian Portuguese): https://moodle-observability.github.io/moodle-tool_opensearch/

Requirements: Moodle 5.3 or later; OpenSearch 2.19 or later with the ISM plugin.

Part of [Moodle Observability](https://github.com/moodle-observability). Contributions: see the [contributing guide](https://github.com/moodle-observability/.github/blob/main/CONTRIBUTING.md).

License: [GPL-3.0](LICENSE).
