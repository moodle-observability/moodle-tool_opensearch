Operations
==========

OpenSearch down
---------------

* Pages are not affected: documents only go to the queue.
* The queue grows; ``moodle_tool_opensearch_up`` goes to 0 and ``queue_oldest_age_seconds`` rises.
* When the cluster is back, ``ship_queue`` empties the queue, up to ``batchdocs`` documents per request, for 50 seconds per minute. Documents that failed wait with exponential backoff (30 s, 1 min, 2 min... up to 30 min, with jitter).
* Above ``queuemax`` documents, new ones are discarded and counted in ``documents_dropped_total``. The limit uses the size counted by the last run of the task, so it is approximate.

Capacity
--------

* Throughput of one run: about ``batchdocs`` documents per request, as many requests as fit in 50 seconds. With the defaults and a cluster answering a 5000 document ``_bulk`` in 2 seconds, about 100000 documents per minute.
* Queue in the database: each document is one row with its JSON; rows are deleted once sent.
* Storage in OpenSearch: measure the average document size of each stream; ``best_compression`` usually cuts it by half or more.

Retention
---------

* Each stream has an ISM policy ``<prefix>-<stream>-ism``: the backing index rolls over after 7 days or 30 GB per primary shard, and is deleted when it is older than the retention.
* Changing the retention updates the policy and moves the existing backing indices to the new version.

Sharing a cluster
-----------------

Sites sharing a cluster need different prefixes. The default prefix already differs by ``wwwroot``. Give each site a user limited to its own prefix (:doc:`configuration`).

Troubleshooting
---------------

* **401 or 403** in the connection test: credentials or role.
* **TLS error**: the CA bundle, or the client certificate and key.
* **Documents rejected** (``failed``): the document has a field not in the mapping of the stream; the last error on the connection test page names it. Fix the producer.
* **Templates not sent**: run ``php admin/tool/opensearch/cli/sync.php --force`` and read the error of each stream.
