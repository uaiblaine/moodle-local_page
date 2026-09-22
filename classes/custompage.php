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
 * Custom pages management for Moodle.
 *
 * Handles creation, updating, and loading of custom pages within Moodle.
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

/**
 * Class custompage.
 *
 * Provides functionality for managing custom pages: create, update, load.
 */
class custompage {
    /**
     * @var \stdClass The data object containing page properties.
     */
    private $pagedata;

    /**
     * Constructor.
     *
     * @param \stdClass $data The page data object.
     */
    public function __construct(\stdClass $data) {
        $this->pagedata = $data;
    }

    /**
     * Insert a new page into the database.
     *
     * @param \stdClass $data The page data to insert.
     * @return int The ID of the newly created page.
     */
    public function createpage(\stdClass $data): int {
        global $DB;
        return $DB->insert_record('local_page', $data);
    }

    /**
     * Update an existing page in the database.
     *
     * @param \stdClass $data The page data to update.
     * @return bool True if the update was successful.
     */
    public function updatepage(\stdClass $data): bool {
        global $DB;
        return $DB->update_record('local_page', $data);
    }

    /**
     * Update an existing page or create a new one if it doesn't exist.
     *
     * @param \stdClass $data The page data to update or create.
     * @return int|bool The page ID on success, or false on failure.
     */
    public function update(\stdClass $data) {
        if (!empty($data->id) && $data->id > 0) {
            $result = $this->updatepage($data);
            return $result ? $data->id : false;
        } else {
            return $this->createpage($data);
        }
    }

    /**
     * Magic getter to retrieve properties from the page data object.
     *
     * @param string $item The property name to retrieve.
     * @return mixed The property value or null if not found.
     */
    public function __get($item) {
        return $this->pagedata->$item ?? null;
    }

    /**
     * Loads a page from the database by ID.
     *
     * @param int $id The page ID to load.
     * @param bool $editor Whether the page is being loaded for editing.
     * @return custompage The loaded page object.
     */
    public static function load($id, $editor = false): custompage {
        global $DB, $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once(dirname(__FILE__) . '/../lib.php');

        $data = null;

        if (intval($id) > 0) {
            $params = ['id' => intval($id)];
            if (!$editor) {
                $params['deleted'] = 0;
            }
            $data = $DB->get_record('local_page', $params);
        }

        // Handle cases where the page does not exist or has no main content.
        if (!$data) {
            // No record found in DB – treat as "no access" for viewers, empty for editor.
            $data = new \stdClass();
            $data->pagecontent = $editor ? '' : \get_string('noaccess', 'local_page');
        } else if (empty($data->pagecontent)) {
            // Page exists but has no editor content – allow this and keep it empty.
            // This lets pages rely solely on the raw HTML field (contenthtml).
            $data->pagecontent = '';
        }

        // Initialize contenthtml field if not set.
        if (!isset($data->contenthtml)) {
            $data->contenthtml = '';
        }

        if (!$editor) {
            self::rewrite_file_urls($data);
        }

        return new custompage($data);
    }

    /**
     * Loads a page from the database by menuname.
     *
     * Friendly URLs are unique per context, not site-wide, so a lookup has to say which context it
     * means: the site-wide scope is 0, a category's is its context id (see
     * {@see \local_page\local\scope}). The default of 0 is what the site-root viewer wants, and
     * it is also what every caller written before contexts existed meant.
     *
     * @param string $menuname The menuname to load.
     * @param bool $editor Whether the page is being loaded for editing.
     * @param int $contextid Stored contextid to look in; 0 is the system scope.
     * @return custompage The loaded page object.
     */
    public static function load_by_menuname($menuname, $editor = false, int $contextid = 0): custompage {
        global $DB, $CFG;
        require_once($CFG->libdir . '/formslib.php');
        require_once(dirname(__FILE__) . '/../lib.php');

        $data = null;

        if (!empty($menuname)) {
            $data = $DB->get_record_sql(
                "SELECT * FROM {local_page}
                 WHERE menuname = :menuname AND contextid = :contextid AND deleted = 0
                 ORDER BY id DESC",
                ['menuname' => $menuname, 'contextid' => $contextid],
                IGNORE_MULTIPLE
            );
        }

        // Handle cases where the page does not exist or has no main content.
        if (!$data) {
            // No record found in DB – treat as "no access" for viewers, empty for editor.
            $data = new \stdClass();
            $data->pagecontent = $editor ? '' : \get_string('noaccess', 'local_page');
        } else if (empty($data->pagecontent)) {
            // Page exists but has no editor content – allow this and keep it empty.
            // This lets pages rely solely on the raw HTML field (contenthtml).
            $data->pagecontent = '';
        }

        // Initialize contenthtml field if not set.
        if (!isset($data->contenthtml)) {
            $data->contenthtml = '';
        }

        if (!$editor) {
            self::rewrite_file_urls($data);
        }

        return new custompage($data);
    }

    /**
     * Rewrites the @@PLUGINFILE@@ placeholders of a loaded row into real pluginfile URLs.
     *
     * The area a page's embedded files live in is decided by the row itself: a site-wide page
     * shares one area under itemid 0, which is what every URL stored before contexts existed
     * names, while a category page has an area of its own under its id. Both halves were written
     * out twice, once per loader, with the system context and itemid 0 hard-coded; they are one
     * method now so the two loaders cannot come to disagree about where a file is.
     *
     * @param \stdClass $data Row being prepared for the viewer; modified in place.
     * @return void
     */
    private static function rewrite_file_urls(\stdClass $data): void {
        $context = \local_page\local\scope::context($data);
        $itemid = \local_page\local\scope::is_category($data) ? (int) $data->id : 0;

        if (!empty($data->pagecontent)) {
            $data->pagecontent = \file_rewrite_pluginfile_urls(
                $data->pagecontent,
                'pluginfile.php',
                $context->id,
                'local_page',
                'pagecontent',
                $itemid
            );
        }

        if (isset($data->contenthtml) && (string) $data->contenthtml !== '') {
            $data->contenthtml = \file_rewrite_pluginfile_urls(
                $data->contenthtml,
                'pluginfile.php',
                $context->id,
                'local_page',
                'pagecontent',
                $itemid
            );
        }
    }
}
