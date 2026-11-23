Operação
========

OpenSearch fora do ar
---------------------

* As páginas não são afetadas: os documentos só vão para a fila.
* A fila cresce; ``moodle_tool_opensearch_up`` vai a 0 e ``queue_oldest_age_seconds`` sobe.
* Quando o cluster volta, o ``ship_queue`` esvazia a fila, até ``batchdocs`` documentos por requisição, por 50 segundos a cada minuto. Documentos que falharam esperam com backoff exponencial (30 s, 1 min, 2 min... até 30 min, com jitter).
* Acima de ``queuemax`` documentos, os novos são descartados e contados em ``documents_dropped_total``. O limite usa o tamanho contado na última execução da tarefa, então é aproximado.

Capacidade
----------

* Vazão de uma execução: cerca de ``batchdocs`` documentos por requisição, tantas requisições quantas couberem em 50 segundos. Com os padrões e um cluster respondendo um ``_bulk`` de 5000 documentos em 2 segundos, cerca de 100000 documentos por minuto.
* Fila no banco: cada documento é uma linha com o seu JSON; as linhas são apagadas depois do envio.
* Armazenamento no OpenSearch: meça o tamanho médio do documento de cada stream; o ``best_compression`` costuma reduzir à metade ou mais.

Retenção
--------

* Cada stream tem uma política ISM ``<prefixo>-<stream>-ism``: o índice de apoio faz rollover depois de 7 dias ou 30 GB por shard primário, e é apagado quando fica mais velho que a retenção.
* Mudar a retenção atualiza a política e move os índices de apoio existentes para a nova versão.

Dividindo um cluster
--------------------

Sites que dividem um cluster precisam de prefixos diferentes. O prefixo padrão já muda com o ``wwwroot``. Dê a cada site um usuário limitado ao próprio prefixo (:doc:`configuration`).

Problemas comuns
----------------

* **401 ou 403** no teste de conexão: credenciais ou papel.
* **Erro de TLS**: a CA, ou o certificado e a chave do cliente.
* **Documentos recusados** (``failed``): o documento tem um campo fora do mapeamento do stream; o último erro na página de teste de conexão diz qual. Corrija o produtor.
* **Templates não enviados**: rode ``php admin/tool/opensearch/cli/sync.php --force`` e leia o erro de cada stream.
