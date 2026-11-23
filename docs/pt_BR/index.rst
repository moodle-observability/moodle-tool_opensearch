moodle-tool_opensearch
======================

**Conexão, fila e envio em lote ao OpenSearch para o Moodle.**

Plugin base que envia documentos de plugins do Moodle ao OpenSearch: conexão, autenticação, fila no banco de dados, envio em lote com novas tentativas, data streams com retenção (ISM) e métricas. Sozinho não grava nada: outros plugins, como o ``logstore_opensearch``, produzem os documentos.

.. note::

   Fase de planejamento. Ainda não há versão publicada. Acompanhe o andamento nas `issues <https://github.com/moodle-observability/moodle-tool_opensearch/issues>`_.

Funcionalidades previstas
-------------------------

* Conexão por lista de URLs com autenticação básica ou certificado de cliente (mTLS), CA própria e verificação de TLS; configuração por variáveis de ambiente ou pela página de configurações, senha cifrada.
* API para outros plugins: ``api::index($stream, $doc)`` só enfileira (nenhuma chamada HTTP durante a requisição da página).
* Tarefa agendada que envia a fila com ``_bulk``, tratamento do resultado item a item e backoff exponencial; o OpenSearch fora do ar nunca deixa o Moodle lento.
* Data streams, index templates e políticas de retenção (ISM) por stream, sincronizados automaticamente.
* Página de teste de conexão e ferramentas de linha de comando.
* Métricas para o ``tool_prometheus`` (tamanho da fila, documento mais antigo, documentos enviados, com falha e descartados).

Requisitos
----------

* Moodle 5.3 ou posterior.
* Um cluster OpenSearch (2.x) com os plugins de segurança e ISM.

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
