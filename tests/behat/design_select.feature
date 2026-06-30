@mod @mod_lesson @mod_lesson_design
Feature: Choose a lesson design template
  In order to change how a lesson looks
  As a teacher
  I need to select a design template in the lesson settings

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "activities" exist:
      | activity | name      | course | idnumber |
      | lesson   | Test less | C1     | lesson1  |

  Scenario: The design selector appears and saves
    Given I am on the "Test less" "lesson activity editing" page logged in as admin
    When I set the field "Design template" to "Monsterwelt"
    And I press "Save and display"
    And I am on the "Test less" "lesson activity editing" page
    Then the field "Design template" matches value "Monsterwelt"
