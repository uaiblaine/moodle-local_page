<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat step definitions for local_page.
 *
 * @package    local_page
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.
require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Step definitions for local_page Behat features.
 *
 * @package    local_page
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_page extends behat_base {
    /**
     * Visits a category's page at its category address, logged in or not.
     *
     * A feature can name a category only by its idnumber, and the address carries the category id,
     * which Behat cannot compute; this step resolves one into the other and visits
     * /local/page/index.php?category=N&page=slug. Where the site's router is configured the script
     * answers that address with a 303 to the routed one, so the step walks the redirect as well.
     *
     * @Given /^I visit the custom page "(?P<slug_string>(?:[^"]|\\")*)" of the category "(?P<idnumber_string>(?:[^"]|\\")*)"$/
     * @param string $slug The page's friendly URL within the category
     * @param string $idnumber The category's idnumber
     * @return void
     */
    public function i_visit_the_custom_page_of_the_category(string $slug, string $idnumber): void {
        global $DB;

        $categoryid = (int) $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        $url = new moodle_url('/local/page/index.php', ['category' => $categoryid, 'page' => $slug]);

        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Visits a category's page at its canonical address, as the address builder spells it.
     *
     * That is the route /local_page/category/N/slug where the site's router is configured, and the
     * script's own /local/page/index.php?category=N&page=slug where it is not: a Behat site served by
     * PHP's built-in server (as moodle-plugin-ci serves it) rewrites nothing and sets no
     * routerconfigured, so a spelled-out route would 404 there. Asking the builder in this process
     * makes the step visit the address the site itself would link to, on either kind of site.
     *
     * @Given /^I visit the routed page "(?P<slug_string>(?:[^"]|\\")*)" of the category "(?P<idnumber_string>(?:[^"]|\\")*)"$/
     * @param string $slug The page's friendly URL within the category
     * @param string $idnumber The category's idnumber
     * @return void
     */
    public function i_visit_the_routed_page_of_the_category(string $slug, string $idnumber): void {
        global $DB;

        $categoryid = (int) $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        $url = \local_page\local\links::category_page($categoryid, $slug);

        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Visits the editor of a new page in a category, logged in or not.
     *
     * The editor's address carries the category id, which Behat cannot compute, so this step resolves
     * the idnumber and asks the plugin's own helper for the address: /local/page/edit.php?category=N.
     *
     * @Given /^I visit the editor of a new page of the category "(?P<idnumber_string>(?:[^"]|\\")*)"$/
     * @param string $idnumber The category's idnumber
     * @return void
     */
    public function i_visit_the_editor_of_a_new_page_of_the_category(string $idnumber): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/page/lib.php');

        $categoryid = (int) $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        $url = local_page_edit_url(\core\context\coursecat::instance($categoryid));

        $this->execute('behat_general::i_visit', [$url]);
    }

    /**
     * Visits the list of a category's pages, logged in or not.
     *
     * The list's address carries the category's context id, which Behat cannot compute, so this step
     * resolves the idnumber and asks the plugin's own helper for the address:
     * /local/page/pages.php?contextid=N.
     *
     * @Given /^I visit the pages list of the category "(?P<idnumber_string>(?:[^"]|\\")*)"$/
     * @param string $idnumber The category's idnumber
     * @return void
     */
    public function i_visit_the_pages_list_of_the_category(string $idnumber): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/page/lib.php');

        $categoryid = (int) $DB->get_field('course_categories', 'id', ['idnumber' => $idnumber], MUST_EXIST);
        $url = local_page_list_url(\core\context\coursecat::instance($categoryid));

        $this->execute('behat_general::i_visit', [$url]);
    }
}
