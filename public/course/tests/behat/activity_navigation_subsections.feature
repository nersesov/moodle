@core @core_course @mod_subsection
Feature: Activity navigation with subsections
  In order to navigate between activities across subsections
  As a student
  I need the activity navigation to follow the course display order

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections | initsections |
      | Course 1 | C1        | topics | 1           | 1            |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |

  @javascript
  Scenario: Activity navigation with subsections (MDL-85755)
    Given the following "activities" exist:
      | activity   | name         | course | idnumber | section |
      | page       | Page A       | C1     | pagea    | 1       |
      | subsection | Subsection 1 | C1     | subsec1  | 1       |
      | page       | Page D       | C1     | paged    | 1       |
      | page       | Page B       | C1     | pageb    | subsec1 |
      | page       | Page C       | C1     | pagec    | subsec1 |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Page A"
    Then "#prev-activity-link" "css_element" should not exist
    And I should see "Page B" in the "#next-activity-link" "css_element"
    And I follow "Page B"
    And I should see "Page A" in the "#prev-activity-link" "css_element"
    And I should see "Page C" in the "#next-activity-link" "css_element"
    And I follow "Page C"
    And I should see "Page B" in the "#prev-activity-link" "css_element"
    And I should see "Page D" in the "#next-activity-link" "css_element"
    And I follow "Page D"
    And I should see "Page C" in the "#prev-activity-link" "css_element"
    And "#next-activity-link" "css_element" should not exist
    And I am on the "pagea" "Activity" page
    And the "Jump to activity" select box should not contain "Page A"
    And the "Jump to activity" select box should contain "Page B"
    And the "Jump to activity" select box should contain "Page C"
    And the "Jump to activity" select box should contain "Page D"
    And I select "Page B" from the "Jump to activity" singleselect
    And I should see "Page A" in the "#prev-activity-link" "css_element"
    And I should see "Page C" in the "#next-activity-link" "css_element"
    And I select "Page D" from the "Jump to activity" singleselect
    And I should see "Page C" in the "#prev-activity-link" "css_element"
    And "#next-activity-link" "css_element" should not exist
