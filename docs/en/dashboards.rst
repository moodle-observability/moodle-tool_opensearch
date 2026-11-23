Dashboards
==========

The ``examples/`` folder of the repository has a test stack and ready-made dashboards for the Moodle log sent by ``logstore_opensearch`` (data stream ``<prefix>-logstore``):

.. list-table::
   :header-rows: 1

   * - File
     - Contents
   * - ``examples/docker-compose.yml``
     - OpenSearch, OpenSearch Dashboards and Grafana on the ``opensearch`` network, for tests (security disabled).
   * - ``examples/opensearch-dashboards/moodle-log.ndjson``
     - Index pattern ``*-logstore*`` and the dashboards **Moodle - log query** and **Moodle - log overview**.
   * - ``examples/grafana/dashboards/moodle-log.json``
     - Grafana dashboard **Moodle - log (tool_opensearch)**, with the same filters as variables.
   * - ``examples/grafana/provisioning/``
     - Grafana datasource (OpenSearch plugin) and dashboard provisioning.

Filters
-------

The dashboards filter by the same criteria as the Moodle logs report (*Site administration > Reports > Logs*), plus the site:

.. list-table::
   :header-rows: 1

   * - Moodle report
     - Field
   * - (site)
     - ``moodle.shortname``: short name of the site, added by ``tool_opensearch`` to every document. Several sites can share a cluster and the same dashboards.
   * - Course
     - ``courseid``
   * - User
     - ``userid``
   * - Date
     - time filter on ``@timestamp``
   * - Activity
     - ``contextinstanceid`` (the course module id when ``contextlevel`` is 70)
   * - Actions
     - ``crud`` (``c`` create, ``r`` view, ``u`` update, ``d`` delete)
   * - Origin
     - ``origin`` (``web``, ``cli``, ``ws``, ``restore``...)
   * - Events
     - ``edulevel`` (0 other, 1 teaching, 2 participating) and ``eventname``

The documents keep the ids of the standard log (no names): use the ids shown in Moodle, for example in the URL of a course (``course/view.php?id=``) or of an activity (``mod/.../view.php?id=``).

Using them
----------

1. Start the stack: ``cd examples && docker compose up -d``. Connect the Moodle container to the ``opensearch`` network and set ``MOODLE_OPENSEARCH_HOSTS=http://opensearch:9200`` (:doc:`configuration`).
2. **OpenSearch Dashboards** (http://localhost:5601): *Dashboards Management > Saved objects > Import*, choose ``moodle-log.ndjson`` and keep the ids (*Check for existing objects*). Open *Dashboards > Moodle - log query*.
3. **Grafana** (http://localhost:3001): the datasource and the dashboard are provisioned on start, in the *Moodle* folder. In another Grafana, install the ``grafana-opensearch-datasource`` plugin, create a datasource for ``*-logstore*`` with ``@timestamp`` and import ``moodle-log.json``.

With a custom prefix, the pattern ``*-logstore*`` still matches. To show a single site of a shared cluster, use the *Moodle* filter.
