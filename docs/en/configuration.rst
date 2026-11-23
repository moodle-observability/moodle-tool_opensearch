Configuration
=============

*Site administration > Plugins > Admin tools > OpenSearch > Settings.*

Environment variables win over the settings page, key by key: ``MOODLE_OPENSEARCH_`` followed by the setting name in upper case. The page lists the keys that come from the environment. Setting ``MOODLE_OPENSEARCH_HOSTS`` also enables sending, unless ``MOODLE_OPENSEARCH_ENABLED=false``. ``$CFG->forced_plugin_settings['tool_opensearch']`` works as for any plugin.

.. list-table::
   :header-rows: 1
   :widths: 20 15 65

   * - Setting
     - Default
     - Description
   * - ``enabled``
     - 0
     - Queue and send the documents. When disabled, ``api::index()`` discards them.
   * - ``hosts``
     -
     - One or more URLs separated by commas (``https://os1:9200,https://os2:9200``). When a node cannot be reached, the next one is tried.
   * - ``auth``
     - ``basic``
     - ``none``, ``basic`` (user name and password) or ``mtls`` (client certificate).
   * - ``username``, ``password``
     -
     - Basic authentication. The password is encrypted with the site key (``moodledata/secret/key``), which must be the same on every node and be part of the backup. If the key changes, type the password again.
   * - ``cafile``
     -
     - Path of the CA bundle that signed the cluster certificate. Empty uses the system bundle.
   * - ``clientcert``, ``clientkey``
     -
     - Paths of the client certificate and key (PEM), for mTLS. The key never goes to the database.
   * - ``verifytls``
     - 1
     - Verify the cluster certificate. Disable only in test environments.
   * - ``connecttimeout``, ``timeout``
     - 2, 10
     - Seconds. The long timeout only applies to the tasks; requests during a page (``api::index_now()``) wait 2 seconds at most.
   * - ``prefix``
     - ``moodle-<hash>``
     - Start of the names of data streams, templates and policies. The default uses a hash of ``wwwroot``, so sites sharing a cluster (production, staging) do not mix.
   * - ``shards``, ``replicas``
     - 1, 1
     - Of each backing index. Use 0 replicas on a single node cluster.
   * - ``batchdocs``, ``batchbytes``
     - 5000, 8 MB
     - Limits of each ``_bulk`` request.
   * - ``queuemax``
     - 2000000
     - Documents in the queue above which new documents are discarded (and counted).
   * - ``retention_<stream>``
     - from the producer
     - Days kept in each stream. One setting per declared stream.

Example with environment variables:

.. code-block:: bash

   MOODLE_OPENSEARCH_HOSTS=https://opensearch.example.org:9200
   MOODLE_OPENSEARCH_USERNAME=moodle
   MOODLE_OPENSEARCH_PASSWORD=...
   MOODLE_OPENSEARCH_CAFILE=/etc/ssl/opensearch-ca.pem

Internal networks
-----------------

The core blocks requests to private addresses by default (``$CFG->curlsecurityblockedhosts``), and clusters usually live in an internal network. ``tool_opensearch`` turns that check off for its own requests only. It only ever talks to the hosts above, which only an administrator (or the server environment) can set, and it does not follow redirects.

Role in OpenSearch
------------------

With the security plugin, give Moodle a user with a role limited to the prefix of the site. Replace ``moodle-1a2b3c4d`` with the prefix shown on the connection test page.

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

Search and deletion (``indices:data/read/search``, ``delete``) are used by the privacy requests of the producers. The connection test writes and deletes one document in ``<prefix>-selftest``.
