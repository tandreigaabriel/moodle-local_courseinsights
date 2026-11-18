@local @local_courseinsights
Feature: Course Insights dashboard access and content

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email          |
      | student1 | Student   | One      | s1@example.com |
    And the following "courses" exist:
      | fullname        | shortname   | category |
      | Test course: XS | Web1to1     | 0        |
      | Web Development | Development | 0        |
    And the following "course enrolments" exist:
      | user     | course      | role    |
      | student1 | Web1to1     | student |
      | student1 | Development | student |

  @javascript
  Scenario: Site administrator can access the Course Insights dashboard
    Given I log in as "admin"
    When I visit "/local/courseinsights/index.php"
    Then I should see "Course Insights"

  @javascript
  Scenario: Dashboard shows courses from the site
    Given I log in as "admin"
    When I visit "/local/courseinsights/index.php"
    Then I should see "Web Development"

  @javascript
  Scenario: Site admin can switch to the Site Overview tab
    Given I log in as "admin"
    And I visit "/local/courseinsights/index.php"
    When I follow "Site Overview"
    Then I should see "Course Insights"

  @javascript
  Scenario: Site admin can open a course detail page
    Given I log in as "admin"
    And I visit "/local/courseinsights/index.php"
    When I follow "Detailed Report"
    Then I should not see "Access denied"
