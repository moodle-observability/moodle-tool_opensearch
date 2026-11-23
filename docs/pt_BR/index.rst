moodle-tool_opensearch
======================

**Conexão, fila e envio em lote para o OpenSearch no Moodle.**

Plugin base que envia ao OpenSearch documentos produzidos por plugins do Moodle. Sozinho não grava nada: outros plugins, como o ``logstore_opensearch``, declaram streams e produzem os documentos.

.. note::

   Versão 0.1.0 (alfa). Acompanhe o andamento nas `issues <https://github.com/moodle-observability/moodle-tool_opensearch/issues>`_.

Funcionalidades
---------------

* **Conexão**: um ou mais nós, autenticação básica ou certificado de cliente (mTLS), CA própria e verificação TLS. Configuração por variáveis de ambiente ou pela página de configurações; a senha fica cifrada.
* **Nenhum HTTP durante a página**: ``api::index()`` guarda os documentos em memória e os grava numa fila no banco no fim da requisição. OpenSearch lento ou fora do ar nunca deixa o Moodle lento.
* **Envio em lote**: uma tarefa agendada envia a fila com ``_bulk`` a cada minuto e trata cada item: documentos enviados saem da fila, falhas temporárias esperam com backoff exponencial, documentos recusados pelo mapeamento são descartados e contados.
* **Data streams e retenção**: cada stream é um data stream com mapeamento estrito, ``best_compression`` e política de retenção ISM própria, sincronizados automaticamente.
* **Teste de conexão**: uma página e um script de linha de comando conferem versão, saúde do cluster, plugin ISM e permissão de escrita.
* **Métricas** para o ``tool_prometheus``: tamanho e idade da fila, documentos enviados, recusados e descartados, último envio.

Requisitos
----------

* Moodle 5.3 ou posterior.
* OpenSearch 2.19 ou posterior (testado com 2.19 e 3.x), com o plugin ISM. O plugin de segurança é recomendado.

Links
-----

* `Código-fonte <https://github.com/moodle-observability/moodle-tool_opensearch>`_
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
   dashboards
