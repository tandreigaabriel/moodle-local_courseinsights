@local @local_courseinsights
Feature: Course Insights access control

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email              |
      | student1 | Student   | One      | student1@test.com  |
      | teacher1 | Teacher   | One      | teacher1@test.com  |
    And the following "courses" exist:
      | fullname  | shortname | category |
      | My Course | MC1       | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | student1 | MC1    | student        |
      | teacher1 | MC1    | editingteacher |

  Scenario: A student without the view capability cannot access the dashboard
    Given I log in as "student1"
    When I visit "/local/courseinsights/index.php"
    Then I should see "Access denied"

  @javascript
  Scenario: An editing teacher with the view capability can access the dashboard
    Given the following "role assigns" exist:
      | user     | role           | contextlevel | reference |
      | teacher1 | editingteacher | System       |           |
    And I log in as "teacher1"
    When I visit "/local/courseinsights/index.php"
    Then I should see "Course Insights"
    And I should not see "Access denied"

  Scenario: An editing teacher cannot access plugin administration settings
    Given I log in as "teacher1"
    When I visit "/admin/settings.php?section=local_courseinsights_settings"
    Then I should see "Access denied"

  @javascript
  Scenario: A site admin can access plugin administration settings
    Given I log in as "admin"
    When I navigate to "Plugins > Local plugins > Course Insights" in site administration
    Then I should see "Course Insights"
