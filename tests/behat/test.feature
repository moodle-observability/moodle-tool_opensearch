@tool @tool_opensearch
Feature: OpenSearch connection test page
  In order to check the OpenSearch configuration
  As an administrator
  I need a page that shows the configuration and runs the connection test

  Scenario: The test reports a missing host
    Given I log in as "admin"
    When I navigate to "Plugins > Admin tools > OpenSearch > Connection test" in site administration
    Then I should see "Sending is disabled"
    And I should see "No installed plugin declares a stream yet."
    And I click on "Run the test" "link"
    And I should see "No OpenSearch host is configured."
    And I should see "Some checks failed."
