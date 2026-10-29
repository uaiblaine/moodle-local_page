@local @local_page
Feature: Recovering a deleted custom page from the recycle bin
  In order to undo a deletion made by mistake
  As a manager of a course category
  I need a deleted page to wait in a recycle bin until I restore it or delete it permanently

  Background:
    Given the following "categories" exist:
      | name         | category | idnumber |
      | Alpha campus | 0        | CAT1     |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | manager1 | Molly     | Manager  | manager1@example.com |
    And the following "roles" exist:
      | shortname    | name          | archetype |
      | pagesmanager | Pages manager |           |
    And the following "role capabilities" exist:
      | role         | local/page:managecategorypages |
      | pagesmanager | allow                          |
    And the following "role assigns" exist:
      | user     | role         | contextlevel | reference |
      | manager1 | pagesmanager | Category     | CAT1      |
    And the following "local_page > pages" exist:
      | pagename        | menuname | category |
      | Alpha handbook  | handbook | CAT1     |

  Scenario: A deleted page is restored, deleted again, then deleted permanently
    Given I am on the "CAT1" "Category" page logged in as "manager1"
    And I navigate to "Custom pages" in current page administration
    And I should not see "Recycle bin"
    # The confirmation sits in a modal; without JavaScript its link is followed directly.
    When I click on "Remove" "link" in the ".modal-footer" "css_element"
    Then I should not see "Alpha handbook"
    When I click on "Recycle bin (1)" "link"
    Then I should see "Alpha handbook"
    And I should see "handbook"
    When I press "Restore"
    Then I should see "The page 'Alpha handbook' was restored."
    And I should see "The recycle bin is empty."
    When I click on "Return to Pages List" "link"
    Then I should see "Alpha handbook"
    When I click on "Remove" "link" in the ".modal-footer" "css_element"
    And I click on "Recycle bin (1)" "link"
    And I click on "Delete permanently" "link"
    Then I should see "Permanently delete the page 'Alpha handbook' and its files?"
    When I press "Delete permanently"
    Then I should see "The page was deleted permanently."
    And I should see "The recycle bin is empty."

  Scenario: A visitor opening a recycle bin meets the login page whether or not the context exists
    # The bin names its context by id, like the pages list: the two answers must be the same.
    When I visit "/local/page/bin.php?contextid=999999"
    Then "Username" "field" should exist
    When I visit the recycle bin of the category "CAT1"
    Then "Username" "field" should exist
    And I should not see "Recycle bin"

  Scenario: A guest opening a recycle bin meets the login page whether or not the context exists
    Given the following config values are set as admin:
      | guestloginbutton | 1 |
    And I log in as "guest"
    When I visit "/local/page/bin.php?contextid=999999"
    Then "Username" "field" should exist
    When I visit the recycle bin of the category "CAT1"
    Then "Username" "field" should exist
    And I should not see "Recycle bin"
