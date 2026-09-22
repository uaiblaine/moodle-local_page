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
 * Local Pages Renderer
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/local/page/forms/edit.php');

// Temporary fix: manually include output classes until cache is cleared.
require_once($CFG->dirroot . '/local/page/classes/output/page_card.php');
require_once($CFG->dirroot . '/local/page/classes/output/pages_list.php');
require_once($CFG->dirroot . '/local/page/classes/output/page_content.php');

use local_page\output\page_card;
use local_page\output\pages_list;
use local_page\output\page_content;

/**
 *
 * Class local_page_renderer
 *
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_page_renderer extends plugin_renderer_base {
    /**
     * @var array
     */
    public $errorfields = [];

    /**
     * Render a page card using the Output API
     *
     * @param page_card $pagecard Page card output object
     * @return string The rendered HTML
     */
    public function render_page_card(page_card $pagecard): string {
        return $this->render_from_template('local_page/page_card', $pagecard->export_for_template($this));
    }

    /**
     * Render pages list using the Output API
     *
     * @param pages_list $pageslist Pages list output object
     * @return string The rendered HTML
     */
    public function render_pages_list(pages_list $pageslist): string {
        return $this->render_from_template('local_page/pages_list', $pageslist->export_for_template($this));
    }

    /**
     * Render page content using the Output API
     *
     * @param page_content $pagecontent Page content output object
     * @return string The rendered HTML
     */
    public function render_page_content(page_content $pagecontent): string {
        return $this->render_from_template('local_page/page_content', $pagecontent->export_for_template($this));
    }

    /**
     * Get the submenu item
     *
     * This function generates the HTML for displaying a single page card in the pages list.
     * Each card shows the page status (live/draft/archived), title, edit button, URLs,
     * and action buttons for viewing and deleting the page.
     *
     * @param int $parent The ID of the page
     * @param string $name The name/title of the page
     * @param string $status The current status of the page (live, draft, archived)
     * @param int $pagedate The timestamp when the page becomes active
     * @param int $enddate The timestamp when the page expires
     * @param string|null $menuname Optional menu name for clean URLs
     * @return string The generated HTML for the page card
     */
    public function get_allpages($parent, $name, $status, $pagedate, $enddate, $menuname = null): string {
        $pagecard = new page_card($parent, $name, $status, $pagedate, $enddate, $menuname);
        return $this->render_page_card($pagecard);
    }

    /**
     *
     * List the pages for the user to view
     *
     * @return string
     */
    public function list_pages() {
        global $DB;

        // Get all non-deleted pages ordered by name.
        $records = $DB->get_records_sql(
            "SELECT id, pagename, pagedata, status, menuname, pagedate, enddate
             FROM {local_page}
             WHERE deleted = 0
             ORDER BY pagename"
        );

        $pageslist = new pages_list($records);
        return $this->render_pages_list($pageslist);
    }

    /**
     *
     * Show the page based on users rights
     *
     * @param mixed $page
     * @return mixed
     */
    public function showpage($page) {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/lib.php');

        if (!local_page_user_can_view_page($page)) {
            // Return a no access message if the user does not have permission.
            $noaccessmsg = get_string('noaccess', 'local_page');
            $pagecontentobj = new page_content(false, '', $noaccessmsg);
            return $this->render_page_content($pagecontentobj);
        }

        $form = '';

        // Format the page content to prevent XSS attacks.
        $pagecontent = format_text(
            $this->adduserdata($page->pagecontent),
            FORMAT_HTML,
            ['trusted' => true, 'noclean' => true]
        );
        // Add content HTML if available (raw HTML content). Avoid PHP empty() — it treats "0" as empty.
        $contenthtml = '';
        if ($page->contenthtml !== null && $page->contenthtml !== '') {
            $contenthtml = $this->adduserdata($page->contenthtml);
        }
        // Replace placeholders in the page content with the actual form content.
        $content = str_replace(["#form#", "{form}"], [$form, $form], $pagecontent);
        // Combine regular content with raw HTML content.
        $finalcontent = $content . $contenthtml;
        $pagecontentobj = new page_content(true, $finalcontent);
        return $this->render_page_content($pagecontentobj);
    }

    /**
     * Replaces user data placeholders in content with actual user information
     *
     * Only an explicit allow-list of placeholders is supported (e.g. {firstname}, {email});
     * Logged-out and guest sessions use {@see guest_user()} (configured via $CFG->siteguest), never a hard-coded user id.
     *
     * @param string $data The content containing user data placeholders
     * @return string The content with placeholders replaced with actual user data
     */
    public function adduserdata($data) {
        global $USER;

        $allowedfields = ['firstname', 'lastname', 'email', 'username', 'idnumber', 'city', 'country'];

        if (isloggedin() && !isguestuser()) {
            $usr = $USER;
        } else {
            $usr = guest_user();
            if (!$usr) {
                return $data;
            }
        }

        foreach ($allowedfields as $key) {
            if (!isset($usr->$key) || !is_scalar($usr->$key)) {
                continue;
            }
            $placeholder = '{' . $key . '}';
            if (strpos($data, $placeholder) !== false) {
                $data = str_replace($placeholder, s($usr->$key), $data);
            }
        }

        if (strpos($data, '{fullname}') !== false) {
            $data = str_replace('{fullname}', s(fullname($usr)), $data);
        }

        return $data;
    }

    /**
     *
     * Save the page to the database and redirect the user
     *
     * @param bool $page
     */
    public function save_page($page = false) {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/page/lib.php');
        $mform = new pages_edit_product_form($page);
        if ($mform->is_cancelled()) {
            redirect(new moodle_url($CFG->wwwroot . '/local/page/pages.php'));
        } else if ($data = $mform->get_data()) {
            require_once($CFG->libdir . '/formslib.php');
            $context = context_system::instance();

            /*
             * Re-check the posted id before anything is written. The form was rendered under a
             * capability check, but the id travels in a hidden field and the row may have been
             * deleted in between, so the write path has to establish for itself that the target
             * exists and that this caller may edit it. The row it returns is what the id below is
             * taken from — never the posted value.
             */
            $editable = local_page_require_editable_page((int) $data->id);
            $draftitemid = file_get_submitted_draft_itemid('pagecontent');
            $pagecontenttext = '';
            if (isset($data->pagecontent) && is_array($data->pagecontent) && array_key_exists('text', $data->pagecontent)) {
                $pagecontenttext = $data->pagecontent['text'];
            }

            $savedpagecontent = file_save_draft_area_files(
                $draftitemid,
                $context->id,
                'local_page',
                'pagecontent',
                0,
                ['subdirs' => true],
                $pagecontenttext
            );

            $data->pagedata = '';

            $recordpage = new stdClass();
            $recordpage->id = $editable === null ? 0 : (int) $editable->id;
            $recordpage->pagename = $data->pagename;
            if (get_config('local_page', 'additionalhead')) {
                $recordpage->meta = $data->meta;
            }
            $recordpage->menuname = strtolower(trim((string) $data->menuname));
            $recordpage->accesslevel = $data->accesslevel;
            $recordpage->pagedate = $data->pagedate;
            $recordpage->enddate = $data->enddate;
            $recordpage->status = $data->status;
            $recordpage->metadescription = $data->metadescription;
            $recordpage->metakeywords = $data->metakeywords;
            $recordpage->metaauthor = $data->metaauthor;
            $recordpage->metatitle = $data->metatitle;
            $recordpage->metarobots = $data->metarobots;
            $recordpage->onlyloggedin = $data->onlyloggedin;
            $recordpage->hidetitle = $data->hidetitle;
            $recordpage->contenthtml = $data->contenthtml;

            $recordpage->pagecontent = $savedpagecontent;

            /*
             * The uniqueness of a friendly URL is decided by a read followed by a write, so two
             * editors saving the same slug at the same moment would both pass the form's check and
             * both store it. Serialise the whole write on one named lock and re-read inside it.
             * A lock we cannot take within the timeout means another save is in flight, which is
             * the same situation as losing the race, so it is reported the same way.
             */
            $lockfactory = \core\lock\lock_config::get_lock_factory('local_page');
            $lock = $lockfactory->get_lock('menuname', 10);
            if (!$lock) {
                throw new \moodle_exception('menuname_taken', 'local_page');
            }

            try {
                if (
                    $recordpage->menuname !== ''
                    && \local_page\local\slug::is_taken($recordpage->menuname, (int) $recordpage->id)
                ) {
                    throw new \moodle_exception('menuname_taken', 'local_page');
                }

                $result = $page->update($recordpage);

                /*
                 * A page saved with no slug would be reachable only by id, and normalise_all()
                 * would name it at the next upgrade rather than now. Name it here instead, so that
                 * every page is addressable from the moment it exists.
                 */
                if ($result && $result > 0 && $recordpage->menuname === '') {
                    $DB->set_field('local_page', 'menuname', 'page-' . (int) $result, ['id' => (int) $result]);
                }
            } finally {
                $lock->release();
            }

            if ($result && $result > 0) {
                $options = local_page_ogimage_filemanager_options();
                if (isset($data->ogimage_filemanager)) {
                    file_postupdate_standard_filemanager($data, 'ogimage', $options, $context, 'local_page', 'ogimage', $result);
                }
                redirect(new moodle_url($CFG->wwwroot . '/local/page/edit.php', ['id' => $result]));
            }
        }
    }

    /**
     *
     * Show the page information to edit
     *
     * @param bool $page
     */
    public function edit_page($page = false) {
        $mform = new pages_edit_product_form($page);
        $forform = new stdClass();
        $forform->pagecontent['text'] = $page->pagecontent;
        $forform->pagename = $page->pagename;
        $forform->meta = $page->meta;
        $forform->accesslevel = $page->accesslevel;
        $forform->menuname = $page->menuname;
        $forform->status = $page->status;
        $forform->metadescription = $page->metadescription;
        $forform->metakeywords = $page->metakeywords;
        $forform->metaauthor = $page->metaauthor;
        $forform->metatitle = $page->metatitle;
        $forform->metarobots = $page->metarobots;
        $forform->id = $page->id;
        $forform->pagedate = $page->pagedate;
        $forform->enddate = $page->enddate;
        $forform->onlyloggedin = $page->onlyloggedin;
        $forform->hidetitle = $page->hidetitle;
        $forform->contenthtml = $page->contenthtml;
        $mform->set_data($forform);
        $mform->display();
    }
}
