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
// Which set of pages this screen lists: the site's, or one course category's.
$contextid = optional_param('contextid', 0, PARAM_INT);

// Set up the page context.
if ($contextid > 0) {
    $context = \core\context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
        // Custom pages exist at those two levels only; anything else is a hand-edited URL.
        throw new moodle_exception('invalidcontext');
    }
} else {
    $context = context_system::instance();
}
$listurl = local_page_list_url($context);

// Set PAGE variables for the current page.
if ($context->contextlevel == CONTEXT_COURSECAT) {
    // Sets the course, the category and the context in one call, and throws if anything else
    // already set one of them — so it comes before every other set_*() call (pagelib.php:1478).
    $PAGE->set_category_by_id((int) $context->instanceid);
} else {
    $PAGE->set_context($context);
}
$PAGE->set_url($listurl);
$PAGE->set_pagelayout('base');
$PAGE->set_title(get_string('pagesetup_title', 'local_page'));
if ($context->contextlevel == CONTEXT_COURSECAT) {
    /*
     * Say whose pages these are. The heading is read with $alwaysreturnhidden, because
     * core_course_category::get() otherwise throws 'cannotviewcategory' for a category the viewer
     * cannot browse — and who may manage this screen was already decided by the capability check
     * below. A heading is not the place to apply a second, different rule.
     */
    $PAGE->set_heading(core_course_category::get((int) $context->instanceid, MUST_EXIST, true)->get_formatted_name());
} else {
    $PAGE->set_heading(get_string('pagesetup_heading', 'local_page'));
}

// Force the user to login and check capabilities for managing pages in THIS context.
require_login();
require_capability(\local_page\local\scope::capability($context), $context);

// Handle page deletion if requested.
if ($deletepage !== 0) {
    require_sesskey();
    // Mark the page as deleted in the database, and release the friendly URL it was holding in the
    // same write: a slug belongs to a page a visitor can reach, and a deleted page is not one.
    // A page restored by hand therefore needs a new slug.
    //
    // The row has to belong to the context this screen was authorised for: the capability was
    // checked there, and the id travels in a link, so without the comparison a category manager
    // could delete a site-wide page by typing its id.
    $pagetodelete = $DB->get_record('local_page', ['id' => $deletepage], 'id, menuname, contextid');
    if ($pagetodelete && local_page_page_in_context($pagetodelete, $context)) {
        $DB->update_record('local_page', (object) [
            'id' => (int) $pagetodelete->id,
            'deleted' => 1,
            'menuname' => \local_page\local\slug::deleted_name(
                (string) $pagetodelete->menuname,
                (int) $pagetodelete->id
            ),
        ]);
        // Its public short address dies with it: the /p/ code of a deleted page answers not found.
        \local_page\local\links::forget((int) $pagetodelete->id);
    }
    // Redirect to the same page to prevent resubmission.
    redirect($listurl);
}

// Get the renderer for this page.
$renderer = $PAGE->get_renderer('local_page');

// Output the page header and content.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('custompage_title', 'local_page'));
echo $renderer->list_pages($context);

echo $OUTPUT->footer();
