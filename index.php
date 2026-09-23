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
 * Main view page for displaying custom pages in the local_page plugin.
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Include config.php.
// phpcs:disable moodle.Files.RequireLogin.Missing
// Let codechecker ignore the next line because otherwise it would complain about a missing login check
// after requiring config.php which is really not needed.
require(__DIR__ . '/../../config.php');

// Globals.
global $CFG, $PAGE, $USER, $DB, $SITE;
require_once($CFG->dirroot . '/local/page/lib.php'); // Include the library file for local_page plugin functions.

// Retrieve the ID or menuname of the page to be displayed from the URL parameters.
$pageid = optional_param('id', 0, PARAM_INT);
$menuname = optional_param('menuname', '', PARAM_ALPHANUMEXT);

// Set up the page context and URL for the current page.
$context = context_system::instance(); // Get the system context.
$PAGE->set_context($context); // Set the context for the page.

// Set the URL based on whether we are using menuname or ID.
if (!empty($menuname)) {
    $PAGE->set_url(new moodle_url('/' . $menuname)); // Define the URL for the page using menuname.
} else {
    $PAGE->set_url(new moodle_url('/local/page/index.php', ['id' => $pageid])); // Define the URL for the page using ID.
}

// Load the custom page object using the page ID or menuname.
if (!empty($menuname)) {
    // Load by menuname.
    $custompage = \local_page\custompage::load_by_menuname($menuname);
} else {
    // Load by ID.
    $custompage = \local_page\custompage::load($pageid);
}

// Check if the custom page has specific access level requirements.
if (!empty($custompage->accesslevel)) {
    require_login(); // Ensure the user is logged in if access level is required.

    // Note: Additional capability checks can be added here based on $custompage->accesslevel.
}

$canview = local_page_user_can_view_page($custompage);

// Set the page layout to use.
$PAGE->set_pagelayout('base'); // Set the page layout.

// Only expose SEO meta, headings, canonical URL and per-page Additional HTML once access is confirmed.
$safetitle = get_string('noaccess', 'local_page');

$headseo = '';
$existinghead = !empty($CFG->additionalhtmlhead) ? $CFG->additionalhtmlhead . "\n" : '';
if (!$canview) {
    // Generic document title — do not leak draft/archived/deleted-page metadata via $PAGE / head.
    $PAGE->set_title($safetitle);
    $PAGE->set_heading('');
    $CFG->additionalhtmlhead = $existinghead;
} else {
    $PAGE->set_title($custompage->pagename);
    $statusbadge = $custompage->status;

    $metatags = [
        'description' => $custompage->metadescription,
        'keywords' => $custompage->metakeywords,
        'author' => $custompage->metaauthor,
        'robots' => $custompage->metarobots,
    ];

    foreach ($metatags as $name => $content) {
        if (!empty($content)) {
            $headseo .= html_writer::empty_tag('meta', ['name' => $name, 'content' => $content]) . "\n";
        }
    }

    // Advertise the image only when pluginfile.php will serve it; see local_page_ogimage_is_servable().
    $fs = get_file_storage();
    $files = local_page_ogimage_is_servable($custompage)
        ? $fs->get_area_files($context->id, 'local_page', 'ogimage', $custompage->id, 'sortorder', false)
        : [];

    if ($files) {
        $file = reset($files);
        if (local_page_ogimage_is_image($file)) {
            $imageurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
                false
            );
            $headseo .= html_writer::empty_tag('meta', ['property' => 'og:image', 'content' => $imageurl->out(false)]) . "\n";
        }
    }

    if (!empty($menuname) && !empty($custompage->menuname)) {
        $canonicalurl = new moodle_url('/' . $custompage->menuname);
    } else {
        $canonicalurl = new moodle_url('/local/page/index.php', ['id' => $custompage->id]);
    }

    $headseo .= html_writer::empty_tag('meta', ['property' => 'og:site_name', 'content' => $SITE->fullname]) . "\n";
    $headseo .= html_writer::empty_tag('meta', ['property' => 'og:type', 'content' => 'website']) . "\n";
    $headseo .= html_writer::empty_tag('meta', ['property' => 'og:title', 'content' => local_page_og_title($custompage)]) . "\n";
    $headseo .= html_writer::empty_tag('meta', ['property' => 'og:url', 'content' => $canonicalurl->out(false)]) . "\n";

    $additionalhead = get_config('local_page', 'additionalhead') ? (string) $custompage->meta : '';
    $CFG->additionalhtmlhead = $existinghead . $headseo . $additionalhead;

    if ($custompage->hidetitle == 'no') {
        $PAGE->set_heading($custompage->pagename);
    }

    if (has_capability('local/page:addpages', $context)) {
        $PAGE->add_body_class('local-page-status-' . $statusbadge);
    }

    $bodyid = (int) $custompage->id;
    if ($bodyid > 0) {
        if ($pagedata = $DB->get_record('local_page', ['id' => $bodyid, 'deleted' => 0])) {
            $PAGE->add_body_class('local-page-id-' . $bodyid);
        }
    }
}

$PAGE->set_pagetype('local-page-id-' . max(0, (int) $custompage->id));

// Obtain the renderer for the local_page plugin to output the page content.
$renderer = $PAGE->get_renderer('local_page');

// Output the page header, content, and footer.
echo $OUTPUT->header(); // Display the page header.
echo $OUTPUT->blocks('side-pre');
echo $renderer->showpage($custompage); // Render and display the custom page content.



// Check if the user has the capability to add pages or is a site admin.
$editpageid = (int) $custompage->id;
if ($editpageid > 0 && has_capability('local/page:addpages', $context)) {
    $footerbtn = html_writer::div(
        html_writer::link(
            new moodle_url('/local/page/edit.php', ['id' => $editpageid]),
            '<i class="fa fa-pencil me-2"></i>' . get_string('edit', 'moodle'),
            ['class' => 'btn btn-primary']
        ),
        'local-page-admin-controls mt-3'
    );
    echo $footerbtn;
}

echo $OUTPUT->footer(); // Display the page footer.
