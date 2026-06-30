@mod @mod_lesson @mod_lesson_design
Feature: Card design renders lesson question pages
  In order to present questions with a different look
  As a learner
  I see the card layout when a lesson uses a card-based template

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | student1 | Sam       | Student  |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activity" exists:
      | activity | lesson      |
      | course   | C1          |
      | idnumber | 0001        |
      | name     | Card lesson |
      | design   | monsterwelt |
    And the following "mod_lesson > pages" exist:
      | lesson      | qtype       | title | content        |
      | Card lesson | multichoice | Q1    | Pick the right |
    And the following "mod_lesson > answers" exist:
      | page | answer | response | jumpto    | score |
      | Q1   | Right  | Yes      | Next page | 1     |
      | Q1   | Wrong  | No       | This page | 0     |

  Scenario: Multichoice question renders as a card
    When I am on the "Card lesson" "lesson activity" page logged in as student1
    Then ".lesson-design-card" "css_element" should exist
    And I should see "Right"
    And I should see "Wrong"

  Scenario: Default design keeps the standard look
    Given the following "activity" exists:
      | activity | lesson         |
      | course   | C1             |
      | idnumber | 0002           |
      | name     | Plain lesson   |
      | design   | default        |
    And the following "mod_lesson > pages" exist:
      | lesson       | qtype       | title | content        |
      | Plain lesson | multichoice | PQ1   | Pick the right |
    And the following "mod_lesson > answers" exist:
      | page | answer | response | jumpto    | score |
      | PQ1  | Right  | Yes      | Next page | 1     |
      | PQ1  | Wrong  | No       | This page | 0     |
    When I am on the "Plain lesson" "lesson activity" page logged in as student1
    Then ".lesson-design-card" "css_element" should not exist
    And I should see "Right"
