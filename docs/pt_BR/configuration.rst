Configuração
============

*Administração do site > Plugins > Ferramentas de administração > OpenSearch > Configurações.*

Variáveis de ambiente vencem a página de configurações, chave por chave: ``MOODLE_OPENSEARCH_`` seguido do nome da configuração em maiúsculas. A página lista as chaves que vêm do ambiente. Definir ``MOODLE_OPENSEARCH_HOSTS`` também liga o envio, a menos que ``MOODLE_OPENSEARCH_ENABLED=false``. ``$CFG->forced_plugin_settings['tool_opensearch']`` funciona como em qualquer plugin.

.. list-table::
   :header-rows: 1
   :widths: 20 15 65

   * - Configuração
     - Padrão
     - Descrição
   * - ``enabled``
     - 0
     - Coloca os documentos na fila e os envia. Desligado, ``api::index()`` os descarta.
   * - ``hosts``
     -
     - Uma ou mais URLs separadas por vírgula (``https://os1:9200,https://os2:9200``). Quando um nó não responde, o próximo é tentado.
   * - ``auth``
     - ``basic``
     - ``none``, ``basic`` (usuário e senha) ou ``mtls`` (certificado de cliente).
   * - ``username``, ``password``
     -
     - Autenticação básica. A senha é cifrada com a chave do site (``moodledata/secret/key``), que precisa ser a mesma em todos os nós e entrar no backup. Se a chave mudar, digite a senha de novo.
   * - ``cafile``
     -
     - Caminho da CA que assinou o certificado do cluster. Vazio usa as CAs do sistema.
   * - ``clientcert``, ``clientkey``
     -
     - Caminhos do certificado e da chave do cliente (PEM), para mTLS. A chave nunca vai para o banco.
   * - ``verifytls``
     - 1
     - Verifica o certificado do cluster. Desligue só em ambientes de teste.
   * - ``connecttimeout``, ``timeout``
     - 2, 10
     - Segundos. O timeout longo só vale nas tarefas; requisições durante uma página (``api::index_now()``) esperam no máximo 2 segundos.
   * - ``prefix``
     - ``moodle-<hash>``
     - Início dos nomes de data streams, templates e políticas. O padrão usa um hash do ``wwwroot``, para que sites que dividem um cluster (produção, homologação) não se misturem.
   * - ``shards``, ``replicas``
     - 1, 1
     - De cada índice de apoio. Use 0 réplicas num cluster de um nó.
   * - ``batchdocs``, ``batchbytes``
     - 5000, 8 MB
     - Limites de cada requisição ``_bulk``.
   * - ``queuemax``
     - 2000000
     - Documentos na fila acima dos quais os novos são descartados (e contados).
   * - ``retention_<stream>``
     - do produtor
     - Dias mantidos em cada stream. Uma configuração por stream declarado.

Exemplo com variáveis de ambiente:

.. code-block:: bash

   MOODLE_OPENSEARCH_HOSTS=https://opensearch.example.org:9200
   MOODLE_OPENSEARCH_USERNAME=moodle
   MOODLE_OPENSEARCH_PASSWORD=...
   MOODLE_OPENSEARCH_CAFILE=/etc/ssl/opensearch-ca.pem

Redes internas
--------------

O core bloqueia requisições para endereços privados por padrão (``$CFG->curlsecurityblockedhosts``), e clusters costumam ficar em rede interna. O ``tool_opensearch`` desliga essa verificação só nas próprias requisições. Ele só fala com os hosts acima, que só um administrador (ou o ambiente do servidor) pode definir, e não segue redirecionamentos.

Papel no OpenSearch
-------------------

Com o plugin de segurança, dê ao Moodle um usuário com um papel limitado ao prefixo do site. Troque ``moodle-1a2b3c4d`` pelo prefixo mostrado na página de teste de conexão.

.. code-block:: text

   PUT _plugins/_security/api/roles/moodle_writer
   {
     "cluster_permissions": [
       "cluster_monitor",
       "cluster:admin/opendistro/ism/*",
       "indices:admin/index_template/*"
     ],
     "index_permissions": [{
       "index_patterns": ["moodle-1a2b3c4d-*", ".ds-moodle-1a2b3c4d-*"],
       "allowed_actions": [
         "indices:data/write/*",
         "indices:data/read/search",
         "indices:admin/create",
         "indices:admin/data_stream/*",
         "indices:admin/refresh",
         "delete"
       ]
     }]
   }

Busca e exclusão (``indices:data/read/search``, ``delete``) são usadas pelos pedidos de privacidade dos produtores. O teste de conexão grava e apaga um documento em ``<prefixo>-selftest``.
