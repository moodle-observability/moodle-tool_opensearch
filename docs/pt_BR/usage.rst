Uso
===

Teste de conexão
----------------

*Administração do site > Plugins > Ferramentas de administração > OpenSearch > Connection test* mostra os hosts, a autenticação e o prefixo, o estado de cada stream (documentos na fila, enviados, recusados e descartados, último erro) e roda o teste:

* **Conexão**: distribuição, versão e nome do cluster (``GET /``).
* **Saúde do cluster**: falha quando o status é ``red``.
* **Plugin ISM**: necessário para as políticas de retenção.
* **Permissão de escrita**: cria e apaga um documento em ``<prefixo>-selftest``.

Os erros vêm em palavras claras: DNS, conexão recusada, timeout, TLS, 401 (credenciais) e 403 (papel).

Pela linha de comando (código de saída 1 quando uma verificação falha):

.. code-block:: bash

   php admin/tool/opensearch/cli/test.php

Templates e políticas
---------------------

A tarefa ``ship_queue`` envia o index template e a política ISM de um stream antes do primeiro documento, e de novo quando mudam (stream novo, retenção, shards, réplicas, prefixo). A tarefa diária ``sync_schema`` os envia de novo, restaurando os que forem removidos no cluster. Manualmente:

.. code-block:: bash

   php admin/tool/opensearch/cli/sync.php --force
   php admin/tool/opensearch/cli/sync.php --show   # mostra o JSON, sem enviar

Tarefas
-------

.. list-table::
   :header-rows: 1

   * - Tarefa
     - Agenda
     - O que faz
   * - ``\tool_opensearch\task\ship_queue``
     - a cada minuto
     - Envia a fila com ``_bulk`` por no máximo 50 segundos.
   * - ``\tool_opensearch\task\sync_schema``
     - diária
     - Envia os templates e as políticas de novo.

Consultas
---------

Consulte e visualize os documentos no OpenSearch Dashboards: crie um index pattern ``<prefixo>-<stream>*`` com ``@timestamp`` como campo de tempo. Todo documento tem ``moodle.site`` com o prefixo do site.
