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
$category = optional_param('category', 0, PARAM_INT);
$slug = optional_param('page', '', PARAM_ALPHANUMEXT);

/*
 * Which page this is, whether the viewer may read it, and $PAGE's context and URL are decided by
 * one request class, in one order, for every address: a visitor asking for a category page is
 * refused before anything is looked up unless the category is public (see
 * \local_page\local\request). This script only translates the answer.
 */
if ($category > 0) {
    $request = \local_page\local\request::category($category, $pageid, $slug);
} else {
    $request = \local_page\local\request::legacy($pageid, $menuname);
}
if ($request->redirect !== null) {
    redirect($request->redirect);
}
$custompage = $request->page;
$canview = $request->canview;
$context = \local_page\local\scope::context($custompage);

// Check if the custom page has specific access level requirements.
if (!empty($custompage->accesslevel)) {
    require_login(); // Ensure the user is logged in if access level is required.

    // Note: Additional capability checks can be added here based on $custompage->accesslevel.
}

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
        'og:title' => $custompage->metatitle,
        'robots' => $custompage->metarobots,
    ];

    foreach ($metatags as $name => $content) {
        if (!empty($content)) {
            $headseo .= html_writer::empty_tag('meta', ['name' => $name, 'content' => $content]) . "\n";
        }
    }

    /*
     * The og:image tag is only worth emitting when pluginfile.php will actually serve the file:
     * local_page_ogimage_is_servable() is the gate the file route applies (publication state only,
     * never the viewer), so an editor previewing a draft gets no tag rather than a tag whose URL
     * answers 404.
     */
    $fs = get_file_storage();
    $files = local_page_ogimage_is_servable($custompage)
        ? $fs->get_area_files($context->id, 'local_page', 'ogimage', $custompage->id, 'sortorder', false)
        : [];

    if ($files) {
        $file = reset($files);
        if (!$file->is_directory()) {
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

    if ($category > 0 && !empty($custompage->menuname)) {
        $canonicalurl = new moodle_url('/local/page/index.php', ['category' => $category, 'page' => $custompage->menuname]);
    } else if (!empty($menuname) && !empty($custompage->menuname)) {
        $canonicalurl = new moodle_url('/' . $custompage->menuname);
    } else {
        $canonicalurl = new moodle_url('/local/page/index.php', ['id' => $custompage->id]);
    }

    $headseo .= html_writer::empty_tag('meta', ['property' => 'og:site_name', 'content' => $SITE->fullname]) . "\n";
    $headseo .= html_writer::empty_tag('meta', ['property' => 'og:type', 'content' => 'website']) . "\n";
    $headseo .= html_writer::empty_tag('meta', ['property' => 'og:title', 'content' => $custompage->pagename]) . "\n";
    $headseo .= html_writer::empty_tag('meta', ['property' => 'og:url', 'content' => $canonicalurl->out(false)]) . "\n";

    /*
     * The per-page <head> HTML, which local_page_head_html() withholds unless the site setting is
     * on AND the page is a site-wide one: no sanitiser exists for head markup, so the field is
     * never offered to a category author and a stored value is ignored rather than emitted. The
     * decision lives in lib.php because this file is a script, and a guard only a script reaches
     * is a guard no test holds.
     */
    $additionalhead = local_page_head_html($custompage);
    $CFG->additionalhtmlhead = $existinghead . $headseo . $additionalhead;

    if ($custompage->hidetitle == 'no') {
        $PAGE->set_heading($custompage->pagename);
    }

    if (has_capability(\local_page\local\scope::capability($context), $context)) {
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
if ($editpageid > 0 && has_capability(\local_page\local\scope::capability($context), $context)) {
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
