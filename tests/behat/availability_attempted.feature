@availability @availability_attempted @javascript
Feature: Restrict access by quiz attempted
  In order to let students move on once they have tried a quiz
  As a teacher
  I need to restrict an activity until a quiz attempt has been submitted, whatever the grade

  Background:
    Given the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
      | student1 | Student   | One      |
    And the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "question categories" exist:
      | contextlevel | reference | name           |
      | Course       | C1        | Test questions |
    And the following "questions" exist:
      | questioncategory | qtype     | name | questiontext   |
      | Test questions   | truefalse | TF1  | First question |
    And the following "activities" exist:
      | activity | name   | course |
      | quiz     | Quiz 1 | C1     |
      | page     | Notes  | C1     |
    And quiz "Quiz 1" contains the following questions:
      | question | page |
      | TF1      | 1    |

  Scenario: The page appears once the quiz is submitted, even when failed
    Given I am on the "Notes" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Quiz attempted" "button" in the "Add restriction..." "dialogue"
    And I click on ".availability-item .availability-eye img" "css_element"
    And I set the field "Quiz" to "Quiz 1"
    And I press "Save and return to course"
    And I should see "An attempt at Quiz 1 has been submitted"

    # Not yet attempted: hidden.
    When I am on the "Course 1" "course" page logged in as "student1"
    Then I should not see "Notes" in the "region-main" "region"

    # Started but not submitted: still hidden.
    And user "student1" has started an attempt at quiz "Quiz 1"
    And I reload the page
    And I should not see "Notes" in the "region-main" "region"

    # Submitted with a wrong answer: shown.
    And user "student1" has input answers in their attempt at quiz "Quiz 1":
      | slot | response |
      | 1    | False    |
    And user "student1" has finished an attempt at quiz "Quiz 1"
    And I reload the page
    And I should see "Notes" in the "region-main" "region"

  Scenario: Choose the previous quiz
    Given I am on the "Notes" "page activity editing" page logged in as "teacher1"
    And I expand all fieldsets
    And I click on "Add restriction..." "button"
    And I click on "Quiz attempted" "button" in the "Add restriction..." "dialogue"
    And I set the field "Quiz" to "Previous quiz"
    And I press "Save and display"
    And I am on "Course 1" course homepage
    Then I should see "An attempt at Quiz 1 has been submitted"
