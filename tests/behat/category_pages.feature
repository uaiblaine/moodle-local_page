@local @local_page
Feature: Authoring the custom pages that belong to a course category
  In order to publish information about my own part of the site
  As a manager of a course category
  I need its administration menu to lead me to that category's pages and to keep me there

  Background:
    Given the following "categories" exist:
      | name         | category | idnumber |
      | Alpha campus | 0        | CAT1     |
      | Beta campus  | 0        | CAT2     |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Molly     | Manager  | manager1@example.com |
      | manager2 | Mark      | Manager  | manager2@example.com |
    And the following "roles" exist:
      | shortname    | name         | archetype |
      | pagesmanager | Pages manager |           |
    And the following "role capabilities" exist:
      | role         | local/page:managecategorypages |
      | pagesmanager | allow                          |
    And the following "role assigns" exist:
      | user     | role         | contextlevel | reference |
      | manager1 | pagesmanager | Category     | CAT1      |
      | manager2 | pagesmanager | Category     | CAT2      |

  Scenario: A category manager reaches the category's pages and creates one there
    Given I am on the "CAT1" "Category" page logged in as "manager1"
    When I navigate to "Custom pages" in current page administration
    Then I should see "Page Management"
    # The heading says whose pages these are; without it the screen is the same for every category.
    And I should see "Alpha campus" in the ".page-context-header" "css_element"
    When I click on "Add New Page" "link"
    And I set the field "Title of the Page" to "Alpha campus handbook"
    And I press "Save changes"
    And I click on "Return to Pages List" "link"
    Then I should see "Alpha campus handbook"
    And I should see "Alpha campus" in the ".page-context-header" "css_element"

  Scenario: A manager of another category is offered neither the menu nor the pages
    Given I am on the "CAT1" "Category" page logged in as "admin"
    And I navigate to "Custom pages" in current page administration
    And I click on "Add New Page" "link"
    And I set the field "Title of the Page" to "Alpha campus handbook"
    And I press "Save changes"
    When I am on the "CAT2" "Category" page logged in as "manager2"
    And I navigate to "Custom pages" in current page administration
    Then I should see "Page Management"
    And I should not see "Alpha campus handbook"
    When I am on the "CAT1" "Category" page logged in as "manager2"
    # The control. "should not exist in current page administration" returns quietly when it finds
    # no menu at all, so without this the scenario could pass on a page that renders nothing. Core
    # puts a "Category" link in a category page's secondary navigation whatever the viewer holds.
    Then "Category" "link" should exist in the ".secondary-navigation" "css_element"
    And "Custom pages" "link" should not exist in current page administration

  Scenario: A visitor opening the editor meets the login page whether or not the category exists
    # The two answers must be the same: an error for a category id that does not exist beside the
    # login page for one that does would tell a visitor which ids exist.
    When I visit "/local/page/edit.php?category=999999"
    Then "Username" "field" should exist
    When I visit the editor of a new page of the category "CAT1"
    Then "Username" "field" should exist
    And I should not see "Title of the Page"
