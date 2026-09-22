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
 * Output class for pages list template
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;
use moodle_url;

/**
 * Class representing data for pages list template
 *
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pages_list implements renderable, templatable {
    /** @var array Array of page records */
    protected $pages;

    /** @var \core\context|null Context the listed pages belong to; null means the system context. */
    protected $context;

    /**
     * Constructor
     *
     * @param array $pages Array of page records from database
     * @param \core\context|null $context Context the pages belong to; the system context by default
     */
    public function __construct($pages, ?\core\context $context = null) {
        $this->pages = $pages;
        $this->context = $context;
    }

    /**
     * Export data for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/lib.php');

        $context = $this->context ?? \core\context\system::instance();

        $data = new stdClass();

        // Group pages by status.
        $livepages = [];
        $draftpages = [];
        $archivedpages = [];

        foreach ($this->pages as $page) {
            // Create page card data for each page.
            $pagecard = new page_card(
                $page->id,
                $page->pagename,
                $page->status,
                $page->pagedate,
                $page->enddate,
                $page->menuname,
                $context
            );
            $pagecarddata = $pagecard->export_for_template($output);

            // Group by status.
            if ($page->status == 'live') {
                $livepages[] = $pagecarddata;
            } else if ($page->status == 'draft') {
                $draftpages[] = $pagecarddata;
            } else if ($page->status == 'archived') {
                $archivedpages[] = $pagecarddata;
            }
        }

        // Assign grouped pages to data object.
        $data->livepages = $livepages;
        $data->draftpages = $draftpages;
        $data->archivedpages = $archivedpages;

        /*
         * The address of the editor for a NEW page in this context. A category's button has to
         * carry the category, or it opens the site-wide editor and the author is refused there by
         * a capability they were never meant to hold.
         */
        $data->addpageurl = local_page_edit_url($context);

        return $data;
    }
}
