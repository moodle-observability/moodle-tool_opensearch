moodle-tool_opensearch
======================

**OpenSearch connection, queue and bulk shipping for Moodle.**

Base plugin that sends documents from Moodle plugins to OpenSearch: connection, authentication, a queue in the database, bulk shipping with retries, data streams with retention (ISM) and metrics. On its own it stores nothing: other plugins, such as ``logstore_opensearch``, produce the documents.

.. note::

   Planning phase. There is no release yet. Follow the progress in the `issues <https://github.com/moodle-observability/moodle-tool_opensearch/issues>`_.

Planned features
----------------

* Connection by URL list with basic authentication or client certificate (mTLS), custom CA and TLS verification; settings by environment variables or the settings page, password encrypted.
* API for other plugins: ``api::index($stream, $doc)`` only queues (no HTTP call during a page request).
* Scheduled task that ships the queue with ``_bulk``, per-item result handling and exponential backoff; OpenSearch being down never slows Moodle down.
* Data streams, index templates and retention policies (ISM) per stream, kept in sync automatically.
* Connection test page and command line tools.
* Metrics for ``tool_prometheus`` (queue size, oldest document, sent, failed and dropped documents).

Requirements
------------

* Moodle 5.3 or later.
* An OpenSearch cluster (2.x) with the security and ISM plugins.

Links
-----

* `Source code <https://github.com/moodle-observability/moodle-tool_opensearch>`_
* `Issues <https://github.com/moodle-observability/moodle-tool_opensearch/issues>`_
* `Moodle Observability <https://moodle-observability.github.io/>`_

.. toctree::
   :hidden:
   :maxdepth: 1

   installation
   configuration
   usage
