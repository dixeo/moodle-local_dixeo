@local @local_dixeo @javascript
Feature: Web service calls survive a lost connection
  In order to keep working through short network losses
  As a user
  I need web service failures to be classified and retried safely

  Scenario: The network module classifies failures and never resends a call
    Given I log in as "admin"
    When I am on fixture page "/local/dixeo/tests/behat/fixtures/network_testpage.php"
    And I wait until "DONE" "text" exists
    Then I should see "PASS h"
    And I should see "PASS i"
    And I should see "PASS l"
    And I should not see "FAIL"
