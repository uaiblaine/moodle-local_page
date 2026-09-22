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
 * Edit page for custom pages
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include the Moodle configuration file.
require_once(dirname(dirname(dirname(__FILE__))) . '/config.php'); // Include the Moodle configuration file.
// Include the local page library.
require_once($CFG->dirroot . '/local/page/lib.php'); // Include the local page library.

// Get parameters from URL.
$download = optional_param('download', '', PARAM_ALPHA); // Get download parameter.
$pageid = optional_param('id', 0, PARAM_INT); // Get page ID parameter.
$categoryid = optional_param('category', 0, PARAM_INT); // Course category, for a new category page.

/*
 * Which context this edit is in. For an existing page the stored row decides and the URL cannot
 * argue; for a new page the ?category= parameter does. The row is read here only to answer that
 * question — the authoritative re-check is local_page_require_editable_page() below, which reads
 * it again with the deleted filter and is the call that refuses.
 */
$editrow = $pageid > 0 ? $DB->get_record('local_page', ['id' => $pageid, 'deleted' => 0]) : false;
if ($editrow) {
    $context = \local_page\local\scope::context($editrow);
    $categoryid = (int) ($editrow->categoryid ?? 0);
} else if ($categoryid > 0) {
    $context = \local_page\local\scope::for_category($categoryid);
} else {
    $context = context_system::instance(); // Create a context instance for the system.
}

// Set PAGE variables.
if ($context->contextlevel == CONTEXT_COURSECAT) {
    /*
     * set_category_by_id() sets the course to the site and the context to the category itself, and
     * throws once either has been set (pagelib.php:1478-1490) — so it has to come before every
     * other set_*() call, and it replaces set_context() rather than following it.
     */
    $PAGE->set_category_by_id((int) $context->instanceid);
} else {
    $PAGE->set_context($context); // Set the context for the page.
}
$editurlparams = ['id' => $pageid];
if ($pageid <= 0 && $categoryid > 0) {
    $editurlparams['category'] = $categoryid;
}
$PAGE->set_url(new moodle_url('/local/page/edit.php', $editurlparams)); // Set the URL for the page.
$PAGE->set_pagelayout('standard'); // Set the page layout to standard.
$PAGE->set_title(get_string('pagesetup_title', 'local_page')); // Set the page title.
$PAGE->set_heading(get_string('pluginname', 'local_page')); // Set the page heading.

// Force the user to login and check capabilities.
require_login(); // Ensure the user is logged in.

// Get the renderer for this page.
$renderer = $PAGE->get_renderer('local_page'); // Get the renderer for the local_page plugin.

// Load the page to edit and save if form submitted.
$pagetoedit = \local_page\custompage::load($pageid, true); // Load the page to edit.

/*
 * load(..., true) deliberately skips the deleted filter, so without this guard a soft-deleted page
 * could still be opened in the editor and saved back into existence. A new page (id 0) is
 * unaffected: there is no row to read and only the capability is checked — at the context the new
 * page would be created in, which is the one this screen was opened for.
 */
local_page_require_editable_page($pageid, \local_page\local\scope::stored_contextid($context));

$renderer->save_page($pagetoedit, $context); // Save the page using the renderer.

// Theme XY Simple Content Builder: only when theme_xy is installed and builder templates exist (hero overlay, snippets).
$contentbuildersupplement = '';
if (local_page_xy_simple_content_builder_is_available()) {
    require_once($CFG->dirroot . '/theme/xy/lib.php');
    if (function_exists('theme_xy_require_simple_content_builder')) {
        theme_xy_require_simple_content_builder();
    }
    if (function_exists('theme_xy_simple_content_builder_template_context')) {
        $contentbuildersupplement = $OUTPUT->render_from_template(
            'theme_xy/contentbuilder/builder',
            theme_xy_simple_content_builder_template_context()
        );
    }
}

echo $OUTPUT->header(); // Output the page header.

// Display page title with back link.
$backparams = $context->contextlevel == CONTEXT_COURSECAT ? ['contextid' => $context->id] : [];
$backlink = new moodle_url('/local/page/pages.php', $backparams); // Create a URL for the back link.
$backtext = get_string('backtolist', 'local_page'); // Get the back link text.
$title = get_string('custompage_title', 'local_page'); // Get the page title.
$previewlink = new moodle_url('/local/page/index.php', ['id' => $pageid]); // Create a URL for the preview link.

// Output the page title and back link.
echo html_writer::tag(
    'h3',
    html_writer::link($backlink, html_writer::tag(
        'i',
        '',
        ['class' => 'fas fa-arrow-left me-1']
    ) . ' ' .
        $backtext, ['class' => 'btn btn-sm btn-secondary me-3']),
    ['class' => 'd-inline-flex align-items-center']
);

// Preview button.
echo html_writer::link(
    $previewlink,
    html_writer::tag('i', '', ['class' => 'fas fa-eye me-1']) . ' ' . get_string('preview', 'editor'),
    ['class' => 'btn btn-sm btn-success', 'target' => '_blank']
);

if ($contentbuildersupplement !== '') {
    echo $contentbuildersupplement;
}

// Display the edit form.
echo $renderer->edit_page($pagetoedit, $context); // Output the edit form for the page.

echo $OUTPUT->footer(); // Output the page footer.
