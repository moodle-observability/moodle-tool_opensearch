Painéis
=======

A pasta ``examples/`` do repositório tem uma stack de testes e painéis prontos para o log do Moodle enviado pelo ``logstore_opensearch`` (data stream ``<prefixo>-logstore``):

.. list-table::
   :header-rows: 1

   * - Arquivo
     - Conteúdo
   * - ``examples/docker-compose.yml``
     - OpenSearch, OpenSearch Dashboards e Grafana na rede ``opensearch``, para testes (segurança desligada).
   * - ``examples/opensearch-dashboards/moodle-log.ndjson``
     - Index pattern ``*-logstore*`` e os dashboards **Moodle - log query** e **Moodle - log overview**.
   * - ``examples/grafana/dashboards/moodle-log.json``
     - Dashboard do Grafana **Moodle - log (tool_opensearch)**, com os mesmos filtros como variáveis.
   * - ``examples/grafana/provisioning/``
     - Datasource do Grafana (plugin OpenSearch) e provisionamento do dashboard.

Filtros
-------

Os painéis filtram pelos mesmos critérios do relatório de logs do Moodle (*Administração do site > Relatórios > Logs*), mais o site:

.. list-table::
   :header-rows: 1

   * - Relatório do Moodle
     - Campo
   * - (site)
     - ``moodle.shortname``: nome curto do site, acrescentado pelo ``tool_opensearch`` a todo documento. Vários sites podem dividir um cluster e os mesmos painéis.
   * - Curso
     - ``courseid``
   * - Usuário
     - ``userid``
   * - Data
     - filtro de tempo sobre ``@timestamp``
   * - Atividade
     - ``contextinstanceid`` (o id do módulo do curso quando ``contextlevel`` é 70)
   * - Ações
     - ``crud`` (``c`` criar, ``r`` visualizar, ``u`` atualizar, ``d`` apagar)
   * - Origem
     - ``origin`` (``web``, ``cli``, ``ws``, ``restore``...)
   * - Eventos
     - ``edulevel`` (0 outros, 1 ensino, 2 participação) e ``eventname``

Os documentos guardam os ids do log padrão (sem nomes): use os ids mostrados no Moodle, por exemplo na URL de um curso (``course/view.php?id=``) ou de uma atividade (``mod/.../view.php?id=``).

Como usar
---------

1. Suba a stack: ``cd examples && docker compose up -d``. Ligue o container do Moodle à rede ``opensearch`` e defina ``MOODLE_OPENSEARCH_HOSTS=http://opensearch:9200`` (:doc:`configuration`).
2. **OpenSearch Dashboards** (http://localhost:5601): *Dashboards Management > Saved objects > Import*, escolha ``moodle-log.ndjson`` e mantenha os ids (*Check for existing objects*). Abra *Dashboards > Moodle - log query*.
3. **Grafana** (http://localhost:3001): o datasource e o dashboard são provisionados ao subir, na pasta *Moodle*. Em outro Grafana, instale o plugin ``grafana-opensearch-datasource``, crie um datasource para ``*-logstore*`` com ``@timestamp`` e importe ``moodle-log.json``.

Com prefixo próprio, o padrão ``*-logstore*`` continua valendo. Para ver um só site de um cluster compartilhado, use o filtro *Moodle*.
