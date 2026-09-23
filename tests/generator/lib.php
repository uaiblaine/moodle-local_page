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
 * Every column has a default, so a test states only the fields it is about.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_page_generator extends component_generator_base {
    /** @var int Pages created so far, so each one gets a distinct name and friendly URL. */
    protected $pagecount = 0;

    /**
     * Create one custom page.
     *
     * The row is written with insert_record() rather than through the renderer's save path, which
     * also handles the editor and its files; the rules under test read the stored row.
     *
     * @param array $record Field values overriding the defaults
     * @return stdClass The stored row, with its id cast to int
     */
    public function create_page(array $record = []): stdClass {
        global $DB;

        $this->pagecount++;

        $record += [
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
}
