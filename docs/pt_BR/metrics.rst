Métricas
========

Com o ``tool_prometheus`` instalado, o plugin acrescenta estas métricas ao endpoint dele. Elas vêm da tabela ``tool_opensearch_stream``, atualizada pela tarefa ``ship_queue``: um scrape nunca conta a fila.

.. list-table::
   :header-rows: 1

   * - Métrica
     - Tipo
     - Descrição
   * - ``moodle_tool_opensearch_up``
     - gauge
     - 1 quando a última execução do ``ship_queue`` alcançou o OpenSearch.
   * - ``moodle_tool_opensearch_queue_documents{stream}``
     - gauge
     - Documentos aguardando, na última execução do ``ship_queue``.
   * - ``moodle_tool_opensearch_queue_oldest_age_seconds{stream}``
     - gauge
     - Idade do documento mais antigo da fila (0 se vazia).
   * - ``moodle_tool_opensearch_documents_sent_total{stream}``
     - counter
     - Documentos aceitos pelo OpenSearch.
   * - ``moodle_tool_opensearch_documents_failed_total{stream}``
     - counter
     - Documentos recusados pelo OpenSearch (em geral um campo fora do mapeamento) e descartados.
   * - ``moodle_tool_opensearch_documents_dropped_total{stream}``
     - counter
     - Documentos descartados porque a fila estava cheia.
   * - ``moodle_tool_opensearch_last_success_timestamp_seconds{stream}``
     - gauge
     - Unix time do último documento aceito.

Alertas sugeridos
-----------------

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

Uma tarefa ``ship_queue`` com falha também é avisada pelo ``tool_taskmonitor``.
