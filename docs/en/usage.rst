Usage
=====

Connection test
---------------

*Site administration > Plugins > Admin tools > OpenSearch > Connection test* shows the hosts, the authentication and the prefix, the state of each stream (queued, sent, rejected and discarded documents, last error) and runs the test:

* **Connection**: distribution, version and cluster name (``GET /``).
* **Cluster health**: fails when the status is ``red``.
* **ISM plugin**: needed for the retention policies.
* **Write permission**: creates and deletes one document in ``<prefix>-selftest``.

Errors are reported in plain words: DNS, connection refused, timeout, TLS, 401 (credentials) and 403 (role).

From the command line (exit code 1 when a check fails):

.. code-block:: bash

   php admin/tool/opensearch/cli/test.php

Templates and policies
----------------------

The ``ship_queue`` task sends the index template and the ISM policy of a stream before its first document, and again when they change (new stream, retention, shards, replicas, prefix). The daily ``sync_schema`` task sends them again, restoring ones removed in the cluster. By hand:

.. code-block:: bash

   php admin/tool/opensearch/cli/sync.php --force
   php admin/tool/opensearch/cli/sync.php --show   # print the JSON, without sending

Tasks
-----

.. list-table::
   :header-rows: 1

   * - Task
     - Schedule
     - What it does
   * - ``\tool_opensearch\task\ship_queue``
     - every minute
     - Sends the queue with ``_bulk`` for at most 50 seconds.
   * - ``\tool_opensearch\task\sync_schema``
     - daily
     - Sends the templates and policies again.

Querying
--------

Query and visualise the documents in OpenSearch Dashboards: create an index pattern ``<prefix>-<stream>*`` with ``@timestamp`` as the time field. Every document has ``moodle.site`` with the prefix of the site.
