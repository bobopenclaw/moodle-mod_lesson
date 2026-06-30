@mod @mod_lesson @mod_lesson_design
Feature: Manage lesson design templates
  In order to offer different lesson appearances
  As an administrator
  I can create design templates and see the built-in ones

  Scenario: List built-in templates and create a new one
    Given I log in as "admin"
    And I visit "/mod/lesson/templates.php"
    And I should see "Default"
    And I should see "Monsterwelt"
    When I press "Add template"
    And I set the following fields to these values:
      | Name                 | My Card |
      | ID number            | mycard  |
      | Base skin            | Card    |
      | Configuration (JSON) | {}      |
    And I press "Save changes"
    Then I should see "My Card"
    And I should see "mycard"
