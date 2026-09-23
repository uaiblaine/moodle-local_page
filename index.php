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
 * \local_page\local\request). While the site's router is configured, the category slug form is
 * answered with a redirect to its routed address, and so is a category page's ?id= address once
 * the page's rules have let the viewer read it. This script only translates the answer.
 */
if ($category > 0) {
    $request = \local_page\local\request::category($category, $pageid, $slug, legacy: true);
} else {
    $request = \local_page\local\request::legacy($pageid, $menuname);
}
if ($request->redirect !== null) {
    redirect($request->redirect);
}

/*
 * The page itself is rendered by the function the routed address renders with, so the two cannot
 * drift apart: it sets up the layout, the head tags and the body classes, and returns the body.
 */
$body = local_page_render_view($request);

// Output the page header, content, and footer.
echo $OUTPUT->header(); // Display the page header.
echo $body;
echo $OUTPUT->footer(); // Display the page footer.
