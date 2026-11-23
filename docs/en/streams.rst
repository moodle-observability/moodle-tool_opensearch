Streams (for developers)
========================

A plugin that sends documents declares its streams with the ``\tool_opensearch\hook\register_streams`` hook and calls ``\tool_opensearch\api``. Each stream becomes the data stream ``<prefix>-<name>``.

Declaring a stream
------------------

``db/hooks.php``:

.. code-block:: php

   $callbacks = [
       [
           'hook' => \tool_opensearch\hook\register_streams::class,
           'callback' => [\local_example\hook_callbacks::class, 'register_streams'],
       ],
   ];

The callback:

.. code-block:: php

   public static function register_streams(\tool_opensearch\hook\register_streams $hook): void {
       $hook->add_stream('local_example', 'example', [
           'action' => ['type' => 'keyword'],
           'userid' => ['type' => 'long'],
           'details' => ['type' => 'text'],
       ], 90, 'Example events.');
   }

* Name: lower case letters, digits and ``_``, starting with a letter, at most 40 characters. ``selftest`` is reserved. A name already declared by another plugin is rejected.
* Mapping: the ``properties`` of an OpenSearch mapping. It is strict: a document with an undeclared field is rejected and counted as failed. ``@timestamp`` (``date``) and ``moodle.site`` (``keyword``) are added by ``tool_opensearch``.
* Retention: default number of days; the administrator may change it.
* Callbacks run once per request and must not call external services. A failing callback is reported with ``debugging()`` and does not hide the streams of the other plugins.

Sending documents
-----------------

.. code-block:: php

   \tool_opensearch\api::index('example', ['action' => 'created', 'userid' => $USER->id]);

* ``index()`` never throws and never waits for the cluster: it buffers the document, and the buffer goes to the queue at the end of the request (or every 500 documents or 1 MB).
* Nothing happens when sending is disabled. Unknown streams are reported with ``debugging()``.
* ``@timestamp`` is added when missing (ISO 8601). ``moodle.site`` always receives the prefix.
* ``api::index_now()`` sends right away with a 2 second timeout and queues the document when that fails. Use it for low volumes, such as alerts.
* ``api::is_enabled()`` tells whether sending is enabled.

Privacy
-------

Each producer declares in its own privacy provider the personal data it sends (``add_external_location_link()``) and answers the requests of the users:

.. code-block:: php

   $hits = \tool_opensearch\api::search('example', ['query' => ['term' => ['userid' => $userid]], 'size' => 1000]);
   \tool_opensearch\api::delete_by_query('example', ['term' => ['userid' => $userid]]);

Both throw ``\tool_opensearch\local\client_exception`` when the cluster cannot answer.
