Streams (para desenvolvedores)
==============================

Um plugin que envia documentos declara seus streams com o hook ``\tool_opensearch\hook\register_streams`` e chama ``\tool_opensearch\api``. Cada stream vira o data stream ``<prefixo>-<nome>``.

Declarando um stream
--------------------

``db/hooks.php``:

.. code-block:: php

   $callbacks = [
       [
           'hook' => \tool_opensearch\hook\register_streams::class,
           'callback' => [\local_example\hook_callbacks::class, 'register_streams'],
       ],
   ];

O callback:

.. code-block:: php

   public static function register_streams(\tool_opensearch\hook\register_streams $hook): void {
       $hook->add_stream('local_example', 'example', [
           'action' => ['type' => 'keyword'],
           'userid' => ['type' => 'long'],
           'details' => ['type' => 'text'],
       ], 90, 'Example events.');
   }

* Nome: letras minúsculas, dígitos e ``_``, começando com letra, até 40 caracteres. ``selftest`` é reservado. Um nome já declarado por outro plugin é recusado.
* Mapeamento: as ``properties`` de um mapeamento do OpenSearch. É estrito: um documento com campo não declarado é recusado e contado como falha. ``@timestamp`` (``date``) e ``moodle.site`` (``keyword``) são acrescentados pelo ``tool_opensearch``.
* Retenção: número padrão de dias; o administrador pode mudar.
* Os callbacks rodam uma vez por requisição e não devem chamar serviços externos. Um callback com erro é avisado com ``debugging()`` e não esconde os streams dos outros plugins.

Enviando documentos
-------------------

.. code-block:: php

   \tool_opensearch\api::index('example', ['action' => 'created', 'userid' => $USER->id]);

* ``index()`` nunca lança exceção e nunca espera o cluster: guarda o documento num buffer, que vai para a fila no fim da requisição (ou a cada 500 documentos ou 1 MB).
* Nada acontece com o envio desligado. Streams desconhecidos são avisados com ``debugging()``.
* ``@timestamp`` é acrescentado quando falta (ISO 8601). ``moodle.site`` sempre recebe o prefixo.
* ``api::index_now()`` envia na hora, com timeout de 2 segundos, e coloca o documento na fila quando falha. Use para volumes baixos, como alertas.
* ``api::is_enabled()`` diz se o envio está ligado.

Privacidade
-----------

Cada produtor declara no seu provider de privacidade os dados pessoais que envia (``add_external_location_link()``) e atende os pedidos dos usuários:

.. code-block:: php

   $hits = \tool_opensearch\api::search('example', ['query' => ['term' => ['userid' => $userid]], 'size' => 1000]);
   \tool_opensearch\api::delete_by_query('example', ['term' => ['userid' => $userid]]);

Os dois lançam ``\tool_opensearch\local\client_exception`` quando o cluster não responde.

Alertas do tool_taskmonitor
---------------------------

Com o ``tool_taskmonitor`` instalado, o ``tool_opensearch`` declara o próprio stream ``alerts`` (365 dias por padrão) e envia a ele cada alerta levantado ou resolvido pelo ``tool_taskmonitor``, na hora (ou pela fila quando o cluster não responde). Os alertas ficam guardados muito mais tempo que no Moodle. Campos: ``alert_id``, ``type``, ``severity``, ``state`` (``raised`` ou ``resolved``), ``title``, ``details`` e ``since`` (quando o alerta começou).
