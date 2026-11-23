<img src="docs/logo/logo.svg" alt="moodle-tool_opensearch logo" width="96" align="right" />

# moodle-tool_opensearch

[![Documentation](https://github.com/moodle-observability/moodle-tool_opensearch/actions/workflows/docs.yml/badge.svg)](https://github.com/moodle-observability/moodle-tool_opensearch/actions/workflows/docs.yml)

OpenSearch connection, queue and bulk shipping for Moodle (`tool_opensearch`). Base plugin that sends documents from Moodle plugins to OpenSearch: connection, authentication, a queue in the database, bulk shipping with retries, data streams with retention (ISM) and metrics. On its own it stores nothing: other plugins, such as `logstore_opensearch`, produce the documents.

**Status:** planning phase. There is no release yet.

Documentation (English and Brazilian Portuguese): https://moodle-observability.github.io/moodle-tool_opensearch/

Requirements: Moodle 5.3 or later.

Part of [Moodle Observability](https://github.com/moodle-observability). Contributions: see the [contributing guide](https://github.com/moodle-observability/.github/blob/main/CONTRIBUTING.md).

License: [GPL-3.0](LICENSE).
