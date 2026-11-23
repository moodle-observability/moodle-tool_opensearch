Installation
============

1. Download the release from GitHub, or clone the repository, into ``public/admin/tool/opensearch``:

   .. code-block:: bash

      git clone https://github.com/moodle-observability/moodle-tool_opensearch.git public/admin/tool/opensearch

2. Run the upgrade:

   .. code-block:: bash

      php admin/cli/upgrade.php

3. Configure the connection (:doc:`configuration`) and run the connection test (:doc:`usage`).

4. Install the plugins that produce documents, such as ``logstore_opensearch``.

The plugin creates two tables: ``tool_opensearch_queue`` (documents waiting to be sent) and ``tool_opensearch_stream`` (state and counters of each stream).
