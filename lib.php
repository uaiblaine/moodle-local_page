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
 * Local pages plugin - Core library functions
 *
 * This file contains the core functions used by the local_page plugin
 * for handling file serving, navigation menu building, and metadata generation.
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Whether theme_xy Simple Content Builder (overlay, snippets incl. hero) can be wired on local page edit.
 *
 * Requires theme_xy installed and all builder assets present, and the {@see moodle_page} theme to actually be xy
 * (default / user theme chain resolved for this page). If xy is installed but another theme such as a child theme
 * is active, returns false — the overlay is omitted.
 *
 * @return bool
 */
function local_page_xy_simple_content_builder_is_available(): bool {
    global $PAGE;

    if (($PAGE->theme->name ?? '') !== 'xy') {
        return false;
    }

    $dir = core_component::get_component_directory('theme_xy');
    if (!$dir) {
        return false;
    }
    if (!is_readable($dir . '/lib.php')) {
        return false;
    }
    return is_readable($dir . '/templates/contentbuilder/builder.mustache');
}

/**
 * Shared options for the Open Graph image file manager (form definition, set_data, save).
 * SVG is excluded because ogimage URLs are anonymous-readable and image/svg+xml can execute script.
 *
 * @return array
 */
function local_page_ogimage_filemanager_options(): array {
    return [
        'subdirs' => 0,
        'maxbytes' => 204800,
        'maxfiles' => 1,
        'accepted_types' => ['jpg', 'jpeg', 'png', 'webp'],
    ];
}

/**
 * Whether $now falls inside a page's publish window.
 *
 * A bound of 0 (or missing) means "no bound", so a row with neither date set is always inside its
 * window. This is the four-branch arithmetic local_page_user_can_view_page() used to carry inline,
 * lifted out unchanged: both that predicate and local_page_ogimage_is_servable() call it, so the
 * public page and its Open Graph image cannot come to disagree about when a page is published.
 *
 * Note it answers about the WINDOW only. Status, access level and onlyloggedin are each caller's
 * business, and they differ between the two callers on purpose.
 *
 * @param object $page Row from {local_page} (stdClass) or an object exposing pagedate and enddate
 * @param int $now Unix timestamp to test the window against
 * @return bool
 */
function local_page_publish_window_is_open(object $page, int $now): bool {
    $start = (int) ($page->pagedate ?? 0);
    $end = (int) ($page->enddate ?? 0);

    if ($start > 0 && $start > $now) {
        return false;
    }
    if ($end > 0 && $end < $now) {
        return false;
    }

    return true;
}

/**
 * Whether a page's Open Graph image may be served, to anybody, through pluginfile.php.
 *
 * True only for a persisted, not soft-deleted, 'live' row that is inside its publish window.
 *
 * It deliberately consults NEITHER accesslevel NOR onlyloggedin NOR any capability, which is the
 * whole difference between this predicate and local_page_user_can_view_page(). An og:image URL is
 * fetched by a scraper — a link preview in a chat client, a crawler, a social network — and that
 * fetch is anonymous and carries no Moodle session, so gating the image on a viewer's capabilities
 * would break every preview rather than protect anything. Nothing is leaked by the difference: the
 * og:image meta tag is emitted by index.php only after the viewer has passed
 * local_page_user_can_view_page(), so a visitor who may not read an onlyloggedin or capability
 * restricted page is never handed the image URL in the first place. What this gate DOES enforce is
 * the part a scraper must not be able to walk around: a draft, archived, expired, not-yet-started
 * or deleted page has no published image, whoever asks.
 *
 * @param object $page Row from {local_page} (stdClass)
 * @return bool
 */
function local_page_ogimage_is_servable(object $page): bool {
    if ((int) ($page->id ?? 0) <= 0) {
        return false;
    }

    if ((int) ($page->deleted ?? 0) !== 0) {
        return false;
    }

    if (($page->status ?? '') !== 'live') {
        return false;
    }

    return local_page_publish_window_is_open($page, time());
}

/**
 * Loads the page a write is about and checks the caller may edit it.
 *
 * For a positive id the row is read with deleted = 0 and a missing or soft-deleted row raises
 * pagenotfound, so a delete that landed between rendering the edit form and posting it cannot be
 * resurrected by replaying the form. For an id of 0 or less — a new page — there is no row to read
 * and only the capability is checked.
 *
 * The capability is the one that governs the page's own context: local/page:addpages for a
 * site-wide page, local/page:managecategorypages for a page belonging to a course category. For an
 * existing page that context comes from the STORED row and the $contextid argument is ignored
 * entirely — a posted context may not move a page, and the row is returned rather than a bare bool
 * so that every caller writes to the context the row actually has.
 *
 * @param int $pageid Page id as posted, or 0 for a new page
 * @param int $contextid Stored contextid the new page is to be created in; 0 is the system scope
 * @return \stdClass|null The stored row, or null when creating a new page
 * @throws \moodle_exception When the id names no live page
 * @throws \coding_exception When the context is neither the system nor a course category
 * @throws \required_capability_exception When the caller may not edit pages there
 */
function local_page_require_editable_page(int $pageid, int $contextid = 0): ?\stdClass {
    global $DB;

    if ($pageid <= 0) {
        $context = \local_page\local\scope::context((object) ['contextid' => $contextid]);
        require_capability(\local_page\local\scope::capability($context), $context);
        return null;
    }

    $row = $DB->get_record('local_page', ['id' => $pageid, 'deleted' => 0]);
    if (!$row) {
        throw new \moodle_exception('pagenotfound', 'local_page');
    }

    $context = \local_page\local\scope::context($row);
    require_capability(\local_page\local\scope::capability($context), $context);

    return $row;
}

/**
 * Whether a stored row belongs to a context.
 *
 * The comparison is on the STORED convention, where the system context is 0 (see
 * {@see \local_page\local\scope}), so a row and a context object can be compared without loading
 * the row's context at all.
 *
 * It exists as a function because the listing screen's delete action needs it and pages.php is a
 * script: a guard that only ever runs inside a script cannot be held by a test, and an unheld
 * guard is the one that quietly stops working.
 *
 * @param \stdClass $row Row from {local_page}
 * @param \core\context $context Context the caller is acting in
 * @return bool
 * @throws \coding_exception When the context is neither the system nor a course category
 */
function local_page_page_in_context(\stdClass $row, \core\context $context): bool {
    return (int) ($row->contextid ?? 0) === \local_page\local\scope::stored_contextid($context);
}

/**
 * The context a save writes to.
 *
 * For an existing page it is the stored row's context, whatever the form posted: the hidden field
 * travels through the browser, and honouring it would let a page be moved between contexts — and
 * with it, out of the reach of the capability that was checked when the form was rendered. For a
 * new page there is no row yet, so the posted value is all there is; it has already been through
 * local_page_require_editable_page(), which is what establishes that the caller may write there.
 *
 * @param \stdClass|null $editable The stored row, or null when creating a new page
 * @param int $contextid Stored contextid as posted; 0 is the system scope
 * @return \core\context
 */
function local_page_save_target_context(?\stdClass $editable, int $contextid): \core\context {
    if ($editable !== null) {
        return \local_page\local\scope::context($editable);
    }

    return \local_page\local\scope::context((object) ['contextid' => $contextid]);
}

/**
 * Whether the current user may view a local page under the same rules as the public renderer.
 *
 * Mirrors local_page_renderer::showpage() access checks (status, dates, onlyloggedin, accesslevel,
 * site configuration capability override). Pages without a positive database id or with soft-delete
 * set are never viewable.
 *
 * Every capability it reads — the site configuration override, the entries of accesslevel, and the
 * editor-preview branch — is evaluated at the page's OWN context. A page belonging to a course
 * category is previewed by whoever may author that category's pages, not by whoever may author the
 * site's, and an accesslevel entry means what it means where the page lives.
 *
 * @param object $page Row from {local_page} (stdClass) or {@see \local_page\custompage} with the same fields
 * @return bool
 */
function local_page_user_can_view_page(object $page): bool {
    global $CFG;

    require_once($CFG->libdir . '/accesslib.php');

    // Not a persisted row (e.g. missing id lookup) — never treat as publicly viewable.
    if (empty((int) ($page->id ?? 0))) {
        return false;
    }

    // Soft-deleted rows must not be viewable on the front (including via pluginfile checks).
    if ((int) ($page->deleted ?? 0) !== 0) {
        return false;
    }

    // Resolved after the two cheap refusals above, because an unsaved row has no context to read.
    $context = \local_page\local\scope::context($page);

    if (has_capability('moodle/site:config', $context)) {
        return true;
    }

    $canaccess = true;
    if (!empty($page->accesslevel) && trim($page->accesslevel) !== '') {
        $canaccess = false;
        $levels = explode(',', $page->accesslevel);
        foreach ($levels as $level) {
            if ($canaccess != true) {
                if (stripos($level, '!') !== false) {
                    $level = str_replace('!', '', $level);
                    $canaccess = has_capability(trim($level), $context) ? false : true;
                } else {
                    $canaccess = has_capability(trim($level), $context) ? true : false;
                }
            }
        }
    }

    $permissions = true;
    if ((int) $page->onlyloggedin === 1) {
        $permissions = isloggedin() && !isguestuser();
    }

    // Same people who can edit custom pages may preview draft/archived/scheduled content
    // (status and publish window still apply to everyone else).
    if (has_capability(\local_page\local\scope::capability($context), $context)) {
        return $canaccess && $permissions;
    }

    /*
     * The four-branch window arithmetic that stood here moved into
     * local_page_publish_window_is_open() unchanged, so that the og:image gate reads the publish
     * window through the same code this predicate does and the two cannot drift apart.
     */
    $istimevalid = local_page_publish_window_is_open($page, time()) && $page->status === 'live' && $permissions;

    return $canaccess && $istimevalid;
}

/**
 * Whether $hay contains $needle as a whole reference (not as a strict prefix of a longer filename/path).
 *
 * The match must end at end-of-string or before a URL/HTML boundary character (?, #, quotes, whitespace,
 * `<`, `)`, `]`, `&`, or the NUL used to join page fields in the search buffer).
 *
 * @param string $hay Content to search
 * @param string $needle Path fragment to find (non-empty)
 * @return bool
 */
function local_page_haystack_contains_pluginfile_needle(string $hay, string $needle): bool {
    if ($needle === '') {
        return false;
    }
    $len = strlen($needle);
    $offset = 0;
    while (($pos = strpos($hay, $needle, $offset)) !== false) {
        $next = substr($hay, $pos + $len, 1);
        if ($next === '' || strpos("\0?#\"' \t\r\n<)&]&", $next) !== false) {
            return true;
        }
        $offset = $pos + 1;
    }
    return false;
}

/**
 * Returns local_page rows whose HTML references a stored file in the pagecontent filearea (itemid 0).
 *
 * Candidate rows are still found with SQL LIKE on the filename; references are then confirmed with
 * anchored matching so one filename cannot satisfy a request for a strict prefix of another.
 *
 * Only rows of the context the file was requested through are searched, and that clause is what
 * keeps the shared site-wide area shared between the right pages. Everything there is stored under
 * itemid 0, so ownership of a file can only be read out of the page content that names it — and a
 * page of another context is written by other people: a category's pages are authored by whoever
 * holds local/page:managecategorypages there. Without the clause such an author could paste a
 * reference to a site-wide file into their own page and, because their own page is viewable, make
 * that file servable whatever the state of the site-wide page it really belongs to.
 *
 * @param int $contextid Context id the file was requested through
 * @param string $filepath File path with leading/trailing slashes (e.g. /sub/)
 * @param string $filename File name
 * @return stdClass[] List of page records (values only)
 * @throws \dml_missing_record_exception When the context id names no context
 * @throws \coding_exception When the context is neither the system nor a course category
 */
function local_page_pages_referencing_pagecontent_file(int $contextid, string $filepath, string $filename): array {
    global $DB;

    $rel = trim($filepath, '/');
    $suffix = $rel === '' ? $filename : $rel . '/' . $filename;
    $parts = array_filter(explode('/', $suffix), static function (string $part): bool {
        return $part !== '';
    });
    $encodedparts = array_map('rawurlencode', array_values($parts));
    $encodedsuffix = implode('/', $encodedparts);

    $needles = [
        '@@PLUGINFILE@@/' . $suffix,
        '@@PLUGINFILE@@/' . $encodedsuffix,
        '/pluginfile.php/' . $contextid . '/local_page/pagecontent/0/' . $suffix,
        '/pluginfile.php/' . $contextid . '/local_page/pagecontent/0/' . $encodedsuffix,
        'pluginfile.php/' . $contextid . '/local_page/pagecontent/0/' . $suffix,
        'pluginfile.php/' . $contextid . '/local_page/pagecontent/0/' . $encodedsuffix,
        $contextid . '/local_page/pagecontent/0/' . $suffix,
        $contextid . '/local_page/pagecontent/0/' . $encodedsuffix,
        '/local_page/pagecontent/0/' . $suffix,
        '/local_page/pagecontent/0/' . $encodedsuffix,
        'local_page/pagecontent/0/' . $suffix,
        'local_page/pagecontent/0/' . $encodedsuffix,
    ];
    $encsuffix = rawurlencode($suffix);
    if ($encsuffix !== '') {
        $needles[] = 'local_page%2Fpagecontent%2F0%2F' . $encsuffix;
    }

    $fnesc = $DB->sql_like_escape($filename);
    $encodedfilename = rawurlencode($filename);
    $likesql = [
        $DB->sql_like('pagecontent', ':pc', false),
        $DB->sql_like('contenthtml', ':ch', false),
    ];
    $params = [
        'pc' => '%' . $fnesc . '%',
        'ch' => '%' . $fnesc . '%',
    ];
    if ($encodedfilename !== $filename) {
        $fnencesc = $DB->sql_like_escape($encodedfilename);
        $likesql[] = $DB->sql_like('pagecontent', ':pce', false);
        $likesql[] = $DB->sql_like('contenthtml', ':che', false);
        $params['pce'] = '%' . $fnencesc . '%';
        $params['che'] = '%' . $fnencesc . '%';
    }
    $params['ctx'] = \local_page\local\scope::stored_contextid(\core\context::instance_by_id($contextid, MUST_EXIST));
    $sql = "SELECT * FROM {local_page} WHERE deleted = 0 AND contextid = :ctx AND (" . implode(' OR ', $likesql) . ")";

    $candidates = $DB->get_recordset_sql($sql, $params);
    $matches = [];
    foreach ($candidates as $page) {
        $hay = ($page->pagecontent ?? '') . "\0" . ($page->contenthtml ?? '');
        foreach ($needles as $needle) {
            if (local_page_haystack_contains_pluginfile_needle($hay, $needle)) {
                $matches[$page->id] = $page;
                break;
            }
        }
    }
    $candidates->close();

    return array_values($matches);
}

/**
 * Whether the current user may fetch a pagecontent area file via pluginfile.php.
 *
 * @param int $contextid Context id the file was requested through
 * @param string $filepath Stored file path (with slashes)
 * @param string $filename File name
 * @return bool
 */
function local_page_user_can_serve_pagecontent_file(int $contextid, string $filepath, string $filename): bool {
    $pages = local_page_pages_referencing_pagecontent_file($contextid, $filepath, $filename);
    if ($pages === []) {
        return false;
    }
    foreach ($pages as $page) {
        if (local_page_user_can_view_page($page)) {
            return true;
        }
    }
    return false;
}

/**
 * Retrieves and serves saved files associated with a specific page.
 *
 * This function handles file requests for different file areas, such as
 * page content, Open Graph images. It checks the requested file area
 * and retrieves the corresponding file from the file storage.
 *
 * @param stdClass $course Course object, representing the course context.
 * @param stdClass $birecordorcm Course module object, used for module-specific operations.
 * @param stdClass $context Context object, providing context for file access.
 * @param string $filearea String indicating the area of the file (e.g., 'pagecontent' or 'ogimage').
 * @param array $args Array of arguments used to locate the file within the specified file area.
 * @param bool $forcedownload Flag indicating whether to force the file download.
 * @param array $options Additional options for file serving, such as caching settings.
 * @return bool false if the file not found, just send the file otherwise and do not return anything
 */
function local_page_pluginfile($course, $birecordorcm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB;

    // Check the contextlevel is as expected: a page lives in the system context or in a category.
    if ($context->contextlevel != CONTEXT_SYSTEM && $context->contextlevel != CONTEXT_COURSECAT) {
        return false;
    }

    // Make sure the filearea is one of those used by the plugin.
    if ($filearea !== 'pagecontent' && $filearea !== 'ogimage') {
        return false;
    }

    // Get the file storage instance.
    $fs = get_file_storage();

    // Extract the filename from the arguments.
    $filename = array_pop($args);

    // Handle different file areas.
    $file = false;

    if ($filearea === 'pagecontent') {
        $itemid = (int) array_shift($args);

        // Construct the file path from the remaining arguments.
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

        /*
         * A category page keeps its files under its own id, in its own context, so the itemid IS
         * the page id and one row lookup authorises the file — no LIKE search over the content of
         * every page, which is what the system area needs because everything there shares itemid
         * 0. Both halves of the WHERE carry weight: without contextid this context would serve a
         * page belonging to a different category, and without deleted = 0 it would serve the
         * files of a page that has been deleted.
         */
        $iscategoryfile = $context->contextlevel == CONTEXT_COURSECAT;
        if ($iscategoryfile) {
            $categorypage = $DB->get_record('local_page', [
                'id' => $itemid,
                'contextid' => $context->id,
                'deleted' => 0,
            ]);
            if (!$categorypage || !local_page_user_can_view_page($categorypage)) {
                return false;
            }
        }

        // Attempt to retrieve the file from the pagecontent area.
        $file = $fs->get_file($context->id, 'local_page', 'pagecontent', $itemid, $filepath, $filename);

        if (!$iscategoryfile && $file && !$file->is_directory()) {
            if (!local_page_user_can_serve_pagecontent_file((int) $context->id, $filepath, $filename)) {
                return false;
            }
        }

        // Special handling for H5P files - integrate with Moodle's H5P system
        // This allows H5P content uploaded to pages to be properly displayed
        // through Moodle's core H5P embed functionality.
        if ($file && !$file->is_directory() && pathinfo($filename, PATHINFO_EXTENSION) === 'h5p') {
            // Close the session to prevent locking issues.
            \core\session\manager::write_close();

            // Redirect to Moodle's H5P embed system.
            $embedurl = new moodle_url('/h5p/embed.php', [
                'url' => moodle_url::make_pluginfile_url(
                    $context->id,
                    'local_page',
                    'pagecontent',
                    $itemid,
                    $filepath,
                    $filename
                )->out(false),
            ]);

            redirect($embedurl);
            return true;
        }
    } else if ($filearea === 'ogimage') {
        // For ogimage, we expect the itemid to be in the args.
        $itemid = array_shift($args); // Get the item ID for the Open Graph image.

        /*
         * The ogimage itemid is the page id, so the row it belongs to decides whether the image is
         * published. Without this lookup the area was world-readable by itemid: the image of a
         * draft, archived, expired or deleted page was served to anyone who guessed the number.
         * The gate is publication state only, never accesslevel or onlyloggedin — see
         * local_page_ogimage_is_servable() for why.
         */
        $ogimagepage = $DB->get_record('local_page', ['id' => (int) $itemid]);
        if (!$ogimagepage || !local_page_ogimage_is_servable($ogimagepage)) {
            return false;
        }

        /*
         * ...and it has to be a page of THIS context. Page ids are unique across the whole table,
         * so the itemid alone would let one context hand out another's image whenever a file
         * happened to sit under that id — a category context serving a site-wide page's image, or
         * the reverse. The comparison goes through scope::context() because a system page stores 0
         * in the column rather than the system context id.
         */
        if ((int) \local_page\local\scope::context($ogimagepage)->id !== (int) $context->id) {
            return false;
        }

        // Construct the file path (ogimages are typically stored in root path).
        $filepath = '/';

        // Retrieve the Open Graph image file from storage.
        $file = $fs->get_file($context->id, 'local_page', 'ogimage', $itemid, $filepath, $filename);
    }

    // Check if file was found and is not a directory.
    if (!$file || $file->is_directory()) {
        return false;
    }

    // Close the session to prevent locking issues.
    \core\session\manager::write_close();

    // Serve the requested file to the user.
    send_stored_file($file, null, 0, $forcedownload, $options);
}
