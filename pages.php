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
 * Page for managing custom pages
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include the main configuration file for Moodle.
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
// Include the library file for local pages functionality.
require_once($CFG->dirroot . '/local/page/lib.php');

// Get the page ID to delete from URL parameters.
$deletepage = optional_param('pagedel', 0, PARAM_INT);

// Set up the page context.
$context = context_system::instance();

// Set PAGE variables for the current page.
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/page/pages.php'));
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('pagesetup_title', 'local_page'));
$PAGE->set_heading(get_string('pagesetup_heading', 'local_page'));

// Force the user to login and check capabilities for managing pages.
require_login();
require_capability('local/page:addpages', $context);

// Handle page deletion if requested.
if ($deletepage !== 0) {
    require_sesskey();
    // Mark the page as deleted in the database, and release the friendly URL it was holding in the
    // same write: a slug belongs to a page a visitor can reach, and a deleted page is not one.
    // A page restored by hand therefore needs a new slug.
    if ($pagetodelete = $DB->get_record('local_page', ['id' => $deletepage], 'id, menuname')) {
        $DB->update_record('local_page', (object) [
            'id' => (int) $pagetodelete->id,
            'deleted' => 1,
            'menuname' => \local_page\local\slug::deleted_name(
                (string) $pagetodelete->menuname,
                (int) $pagetodelete->id
            ),
        ]);
    }
    // Redirect to the same page to prevent resubmission.
    redirect(new moodle_url('/local/page/pages.php'));
}

// Get the renderer for this page.
$renderer = $PAGE->get_renderer('local_page');

// Output the page header and content.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('custompage_title', 'local_page'));
echo $renderer->list_pages();

echo $OUTPUT->footer();
