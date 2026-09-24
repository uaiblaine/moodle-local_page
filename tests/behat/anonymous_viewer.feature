@local @local_page
Feature: A category's pages reach visitors only when the category is public
  In order to put a category's pages on the internet only when the site means to
  As a visitor who is not logged in
  I need a category page to send me to the login page unless its category is public

  # forcelogin is on in every scenario: the plugin serves pages to visitors on sites that otherwise
  # demand a login, and forcelogin is not an ambient gate but a set of explicit checks in core that
  # the viewer never makes. There is deliberately no scenario in which a public category's page
  # renders for a visitor: whether a category is public is decided by local_unlistedcourses, which is
  # not a dependency and may be absent from the test site, so that control lives in PHPUnit
  # (tests/local/request_test.php) with a stand-in for the predicate.
  Background:
    Given the following config values are set as admin:
      | forcelogin | 1 |
    And the following "categories" exist:
      | name         | category | idnumber |
      | Alpha campus | 0        | CAT1     |
    And the following "local_page > pages" exist:
      | pagename       | menuname | category | pagecontent                                    |
      | Welcome        | welcome  |          | <p>Everybody may read this welcome page.</p>   |
      | Alpha handbook | handbook | CAT1     | <p>The Alpha campus handbook starts here.</p>  |

  Scenario: A visitor still reads a site-wide page under forcelogin
    When I visit "/local/page/index.php?menuname=welcome"
    Then I should see "Everybody may read this welcome page."
    And "Username" "field" should not exist

  Scenario: A visitor asking for a category page meets the login page, and comes back to the page after logging in
    # Without local_unlistedcourses the adapter fails closed; with it, this category was never made
    # public. Both must refuse, and with the one answer a category that does not exist would get.
    When I visit the custom page "handbook" of the category "CAT1"
    Then "Username" "field" should exist
    And I should not see "The Alpha campus handbook starts here."
    When I set the field "Username" to "admin"
    And I set the field "Password" to "admin"
    And I press "Log in"
    Then I should see "The Alpha campus handbook starts here."

  Scenario: A logged-in administrator reads the same category page
    Given I log in as "admin"
    When I visit the custom page "handbook" of the category "CAT1"
    Then I should see "The Alpha campus handbook starts here."
    And "Username" "field" should not exist

  # The routed address, /local_page/category/N/slug, where the site's router is configured; the step
  # asks the plugin's address builder, which answers the script's own address where it is not (under
  # PHP's built-in server, for one, which rewrites nothing). Either way it is the address the site links to.
  Scenario: A visitor at a private category's routed address meets the login page
    When I visit the routed page "handbook" of the category "CAT1"
    Then "Username" "field" should exist
    And I should not see "The Alpha campus handbook starts here."

  Scenario: A logged-in administrator reads a category page at its routed address
    Given I log in as "admin"
    When I visit the routed page "handbook" of the category "CAT1"
    Then I should see "The Alpha campus handbook starts here."
    And "Username" "field" should not exist
