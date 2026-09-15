@local @local_bbcotodobien
Feature: Teachers can view the ToDo good course report
  In order to improve course quality
  As a teacher
  I need to open the current audit report from course administration

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | teacher1 | Teacher | 1 | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1 |
    And the following "course enrolments" exist:
      | user | course | role |
      | teacher1 | C1 | editingteacher |
    And the course "C1" has a ToDo good audit that is not fully compliant

  Scenario: Teacher sees the current report
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I navigate to "ToDo good" in current page administration
    Then I should see "Current report"
    And I should see "Quality"
    And I should see "Fail"
    And I should see "Re-evaluate all"
    And I should see "History"
