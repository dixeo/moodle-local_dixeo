@local @local_dixeo @javascript
Feature: Dixeo preferences link on the user Preferences page
  In order to change my Dixeo preferences outside a course
  As a user
  I need a Dixeo preferences link on my own Preferences page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |

  Scenario: A student opens My preferences from their Preferences page
    Given I log in as "student1"
    And I follow "Preferences" in the user menu
    Then I should see "Dixeo preferences"
    When I click on "Dixeo preferences" "link"
    Then I should see "My preferences" in the ".modal-dialog" "css_element"
    And I should see "Terms of Use and Privacy Notice" in the ".modal-dialog" "css_element"
    And I should see "You have not accepted the current Terms of Use" in the ".modal-dialog" "css_element"
    And "[data-action='local-dixeo-settings-status']" "css_element" should not be visible
    And "[data-region='local-dixeo-settings-list']" "css_element" should not be visible
    When I press the escape key
    Then "[data-region='local-dixeo-settings-preferences']" "css_element" should not exist

  Scenario: An administrator sees no Dixeo preferences link on another user's Preferences page
    Given I am on the "student1" "user > profile" page logged in as "admin"
    When I click on "Preferences" "link" in the "region-main" "region"
    Then I should see "Preferred language"
    And I should not see "Dixeo preferences"
