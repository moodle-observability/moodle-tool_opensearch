moodle-tool_opensearch
======================

**OpenSearch connection, queue and bulk shipping for Moodle.**

Base plugin that sends documents from Moodle plugins to OpenSearch. On its own it stores nothing: other plugins, such as ``logstore_opensearch``, declare streams and produce the documents.

.. note::

   Version 0.1.0 (alpha). Follow the progress in the `issues <https://github.com/moodle-observability/moodle-tool_opensearch/issues>`_.

Features
--------

* **Connection**: one or more nodes, basic authentication or client certificate (mTLS), custom CA and TLS verification. Settings by environment variables or the settings page; the password is stored encrypted.
* **No HTTP during a page**: ``api::index()`` keeps documents in memory and writes them to a queue in the database at the end of the request. OpenSearch being slow or down never slows Moodle down.
* **Bulk shipping**: a scheduled task sends the queue with ``_bulk`` every minute and handles each item: sent documents leave the queue, temporary failures wait with exponential backoff, documents rejected by the mapping are discarded and counted.
* **Data streams and retention**: each stream is a data stream with a strict mapping, ``best_compression`` and its own ISM retention policy, kept in sync automatically.
* **Connection test**: a page and a command line script check the version, cluster health, ISM plugin and write permission.
* **Metrics** for ``tool_prometheus``: queue size and age, sent, rejected and discarded documents, last success.

Requirements
------------

* Moodle 5.3 or later.
* OpenSearch 2.19 or later (tested with 2.19 and 3.x), with the ISM plugin. The security plugin is recommended.

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
   streams
   metrics
   operations
