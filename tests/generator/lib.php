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
 * Data generator for local_page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Builds rows in the {local_page} table for tests.
 *
 * Every column has a default here, so a test states only what it is actually
 * about: one asserting on the publish window should not have to name a meta
 * description to get a row. It also means a column added to install.xml is a
 * one-line edit in this file rather than an edit in every test that inserts.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class local_page_generator extends component_generator_base {
    /** @var int Pages created so far, so each gets a distinct name and menu name. */
    protected $pagecount = 0;

    /**
     * Create one custom page.
     *
     * The row is written with insert_record() rather than through the plugin's
     * own save path: the save path applies editor/file handling that most tests
     * of the access rules do not want, and the access rules read the stored row.
     *
     * @param array|stdClass $record Field values overriding the defaults.
     * @return stdClass The stored row, with its id cast to int.
     */
    public function create_page(array|stdClass $record = []): stdClass {
        global $DB;

        $this->pagecount++;
        $record = (array) $record;

        $record += [
            'contextid' => 0,
            'categoryid' => null,
            'pagename' => 'Page ' . $this->pagecount,
            'menuname' => 'page-' . $this->pagecount,
            'status' => 'live',
            'pagedate' => 0,
            'enddate' => 0,
            'accesslevel' => '',
            'onlyloggedin' => 0,
            'hidetitle' => 'no',
            'deleted' => 0,
            'pagecontent' => '<p>Body</p>',
            'contenthtml' => '',
            'contenttrust' => 0,
            'pagedata' => '',
            'meta' => '',
            'metadescription' => '',
            'metakeywords' => '',
            'metaauthor' => '',
            'metatitle' => '',
            'metarobots' => '',
        ];

        $page = (object) $record;
        $page->id = (int) $DB->insert_record('local_page', $page);

        return $page;
    }

    /**
     * Create one custom page belonging to a course category.
     *
     * Writes both halves of the context dimension the way the save path does: contextid is the
     * category's CONTEXT id, which is what every lookup compares against, and categoryid is the
     * category itself, which is what the page layout needs before it can set a context.
     *
     * @param int $categoryid Course category the page belongs to.
     * @param array $record Field values overriding the defaults.
     * @return stdClass The stored row, with its id cast to int.
     */
    public function create_category_page(int $categoryid, array $record = []): stdClass {
        $record['contextid'] = (int) \core\context\coursecat::instance($categoryid)->id;
        $record['categoryid'] = $categoryid;

        return $this->create_page($record);
    }
}
