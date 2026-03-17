@core @core_course @mod_subsection
Feature: Section and activity navigation with subsections follows display order
  In order to navigate in courses with subsections
  As a user
  I need the navigation to reflect the course display order, not raw section numbers

  @javascript
  Scenario: Section prev/next navigation places subsections in display order (MDL-85755)
    # Subsections have higher section numbers than regular sections (created after them).
    # Without the fix, sectionnavigation orders by section number, placing subsections
    # AFTER Section 2. With the fix, display order is used:
    # General -> Section 1 -> Subsection A -> Subsection B -> Section 2.
    # We verify via regular section pages in paged mode (coursedisplay=1),
    # confirming subsections appear between Section 1 and Section 2.
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
  Scenario: Activity navigation with subsections (MDL-85755)
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | 1        | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format | numsections | initsections | newsitems |
      | Course 2 | C2        | topics | 1           | 1            | 0         |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C2     | student |
    And the following "activities" exist:
      | activity   | name         | course | idnumber | section |
      | page       | Page A       | C2     | pagea    | 1       |
      | subsection | Subsection 1 | C2     | subsec1  | 1       |
      | page       | Page D       | C2     | paged    | 1       |
      | page       | Page B       | C2     | pageb    | subsec1 |
      | page       | Page C       | C2     | pagec    | subsec1 |
    And I log in as "student1"
    # With the fix, subsection activities (B, C) appear before Page D:
    # A -> [Subsection 1] -> B -> C -> D.
    # prev(D) = C proves that D comes after C (not after A as in the unfixed version).
    When I am on the "pagea" "Activity" page
    Then the "Jump to activity" select box should contain "Page B"
    And the "Jump to activity" select box should contain "Page C"
    And the "Jump to activity" select box should contain "Page D"
    And I select "Page D" from the "Jump to activity" singleselect
    Then I should see "Page C" in the "#prev-activity-link" "css_element"
