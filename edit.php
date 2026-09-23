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

// Set up the page context.
$context = context_system::instance(); // Create a context instance for the system.

// Set PAGE variables.
$PAGE->set_context($context); // Set the context for the page.
$PAGE->set_url(new moodle_url('/local/page/edit.php', ['id' => $pageid])); // Set the URL for the page.
$PAGE->set_pagelayout('standard'); // Set the page layout to standard.
$PAGE->set_title(get_string('pagesetup_title', 'local_page')); // Set the page title.
$PAGE->set_heading(get_string('pluginname', 'local_page')); // Set the page heading.

// Force the user to login and check capabilities.
require_login(); // Ensure the user is logged in.
require_capability('local/page:addpages', $context); // Check if the user has the capability to add pages.

// Refuse an id naming no page or a deleted one: custompage::load($pageid, true) below would load either.
local_page_require_editable_page($pageid);

// Get the renderer for this page.
$renderer = $PAGE->get_renderer('local_page'); // Get the renderer for the local_page plugin.

// Load the page to edit and save if form submitted.
$pagetoedit = \local_page\custompage::load($pageid, true); // Load the page to edit.
$renderer->save_page($pagetoedit); // Save the page using the renderer.

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
$backlink = new moodle_url('/local/page/pages.php'); // Create a URL for the back link.
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
echo $renderer->edit_page($pagetoedit); // Output the edit form for the page.

echo $OUTPUT->footer(); // Output the page footer.
