Instalação
==========

1. Baixe a release do GitHub, ou clone o repositório, em ``public/admin/tool/opensearch``:

   .. code-block:: bash

      git clone https://github.com/moodle-observability/moodle-tool_opensearch.git public/admin/tool/opensearch

2. Rode a atualização:

   .. code-block:: bash

      php admin/cli/upgrade.php

3. Configure a conexão (:doc:`configuration`) e rode o teste de conexão (:doc:`usage`).

4. Instale os plugins que produzem documentos, como o ``logstore_opensearch``.

O plugin cria duas tabelas: ``tool_opensearch_queue`` (documentos aguardando envio) e ``tool_opensearch_stream`` (estado e contadores de cada stream).
