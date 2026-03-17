@core @core_course @mod_subsection
Feature: Section and activity navigation with subsections follows display order
  In order to navigate in courses with subsections
  As a user
  I need the navigation to reflect the course display order, not raw section numbers

  # Section navigation, the section selector and the activity navigation are only rendered
  # by themes without a course index (e.g. Classic). This feature is blacklisted for Boost
  # (theme/boost/tests/behat/blacklist.json) and runs in the classic suite.

  @javascript
  Scenario: Section prev/next navigation places subsections in display order
    # Subsections have higher section numbers than regular sections (created after them).
    # Without the fix, sectionnavigation orders by section number, placing subsections
    # AFTER Section 2. With the fix, display order is used:
    # General -> Section 1 -> Subsection A -> Subsection B -> Section 2.
    Given the following "courses" exist:
      | fullname | shortname | category | format | coursedisplay | numsections | startdate | initsections | newsitems |
      | Course 1 | C1        | 0        | topics | 1             | 2           | 0         | 1            | 0         |
    And the following "activities" exist:
      | activity   | name         | course | idnumber | section |
      | subsection | Subsection A | C1     | subseca  | 1       |
      | subsection | Subsection B | C1     | subsecb  | 1       |
    And I log in as "admin"
    And I am on "Course 1" course homepage
    # Without fix: Section 1 next = Section 2 (subsections placed after by section number).
    # With fix:    Section 1 next = Subsection A (subsections in display order).
    When I follow "Section 1"
    Then I should see "Subsection A" in the ".single-section div.nextsection" "css_element"
    And I am on "Course 1" course homepage
    # Without fix: Section 2 prev = Section 1.
    # With fix:    Section 2 prev = Subsection B (last item before Section 2).
    And I follow "Section 2"
    Then I should see "Subsection B" in the ".single-section div.prevsection" "css_element"

  @javascript
  Scenario: Activity navigation follows the display order of activities inside subsections
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections | initsections | newsitems |
      | Course 1 | C1        | topics | 1           | 1            | 0         |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    # Section 2 is the delegated section of "Subsection 1". Delegated sections are numbered
    # after the last regular section, so they must be referenced by their section number.
    And the following "activities" exist:
      | activity   | name         | course | idnumber | section |
      | page       | Page A       | C1     | pagea    | 1       |
      | subsection | Subsection 1 | C1     | subsec1  | 1       |
      | page       | Page D       | C1     | paged    | 1       |
      | page       | Page B       | C1     | pageb    | 2       |
      | page       | Page C       | C1     | pagec    | 2       |
    # Display order: A -> [Subsection 1: B -> C] -> D.
    # Without fix: A -> D -> B -> C (subsection activities are appended at the end).
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
