Metrics
=======

With ``tool_prometheus`` installed, the plugin adds these metrics to its endpoint. They are read from ``tool_opensearch_stream``, updated by the ``ship_queue`` task: a scrape never counts the queue.

.. list-table::
   :header-rows: 1

   * - Metric
     - Type
     - Description
   * - ``moodle_tool_opensearch_up``
     - gauge
     - 1 when the last run of ``ship_queue`` reached OpenSearch.
   * - ``moodle_tool_opensearch_queue_documents{stream}``
     - gauge
     - Documents waiting, at the last run of ``ship_queue``.
   * - ``moodle_tool_opensearch_queue_oldest_age_seconds{stream}``
     - gauge
     - Age of the oldest queued document (0 if none).
   * - ``moodle_tool_opensearch_documents_sent_total{stream}``
     - counter
     - Documents accepted by OpenSearch.
   * - ``moodle_tool_opensearch_documents_failed_total{stream}``
     - counter
     - Documents rejected by OpenSearch (usually a field not in the mapping) and discarded.
   * - ``moodle_tool_opensearch_documents_dropped_total{stream}``
     - counter
     - Documents discarded because the queue was full.
   * - ``moodle_tool_opensearch_last_success_timestamp_seconds{stream}``
     - gauge
     - Unix time of the last accepted document.

Suggested alerts
----------------

.. code-block:: yaml

   - alert: MoodleOpenSearchDown
     expr: moodle_tool_opensearch_up == 0
     for: 15m
   - alert: MoodleOpenSearchQueueStuck
     expr: moodle_tool_opensearch_queue_oldest_age_seconds > 1800
     for: 10m
   - alert: MoodleOpenSearchDropping
     expr: increase(moodle_tool_opensearch_documents_dropped_total[15m]) > 0
   - alert: MoodleOpenSearchRejecting
     expr: increase(moodle_tool_opensearch_documents_failed_total[1h]) > 0

A failing ``ship_queue`` task is also reported by ``tool_taskmonitor``.
