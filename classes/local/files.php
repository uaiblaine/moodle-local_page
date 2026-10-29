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
 * Keeping a page's file areas to the files the page uses.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * Keeping a page's file areas to the files the page uses.
 *
 * Two ways a stored file stops being used, one rule each:
 *
 * - The author removes it from the text. The editor's draft area still holds it, and a save makes
 *   the page's area equal to the draft, so it would stay for good. prune_draft() removes, before the
 *   save, every draft file neither of the page's two HTML fields names.
 * - Its page is gone, or never existed: the page was deleted permanently by something other than the
 *   recycle bin, or the file sits under item id 0, the area every site page shared before step
 *   2026102800. Every page keeps its files under its own id in its own context, so a file whose
 *   (context, item id) names no page is used by nothing, and the file route refuses it already.
 *   collect_orphans() deletes those, and lists them first when asked for a dry run.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class files {
    /** @var array The file areas a page keeps under its own id. */
    public const FILEAREAS = ['pagecontent', 'ogimage'];

    /**
     * Remove from an editor's draft area every file the page's text does not name.
     *
     * A file is kept when either field names it: the editor's text by its draft address (the
     * editor rewrites every embedded file to one), contenthtml by the @@PLUGINFILE@@ placeholder or
     * by the page's own file address, which an author may paste there. Each file looks for its own
     * path, as written and with each segment URL-encoded, in both fields as posted, URL-decoded once
     * (an embedded H5P package names its file inside the query string of the embed address) and
     * with HTML entities decoded (an author may write é as &eacute; in hand-written HTML). The name must end the
     * reference ({@see names()}), so a.png is not kept by a.png2, while a hand-written name with a
     * space or an ampersand in contenthtml still keeps its file. Core's
     * file_remove_editor_orphaned_files() reads the editor's text alone, so it would delete a file
     * only contenthtml uses.
     *
     * Only the current user's draft area is read and written, so a posted draft item id can reach
     * nothing else.
     *
     * @param int $draftitemid The editor's draft area
     * @param string $text The editor's text, as posted
     * @param string $contenthtml The page's raw HTML field, as posted
     * @param int $pageid The page's id, or 0 for a page not saved yet
     * @return int Number of draft files removed
     */
    public static function prune_draft(int $draftitemid, string $text, string $contenthtml, int $pageid): int {
        global $USER;

        if ($draftitemid <= 0) {
            return 0;
        }

        $prefixes = [
            '/user/draft/' . $draftitemid . '/',
            '@@PLUGINFILE@@/',
        ];
        if ($pageid > 0) {
            $prefixes[] = '/local_page/pagecontent/' . $pageid . '/';
        }

        $raw = $text . "\0" . $contenthtml;
        $hays = [$raw, rawurldecode($raw), html_entity_decode($raw, ENT_QUOTES | ENT_HTML5)];

        $removed = 0;
        $usercontext = \context_user::instance($USER->id);
        $files = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
        foreach ($files as $file) {
            $path = ltrim($file->get_filepath() . $file->get_filename(), '/');
            $spellings = array_unique([$path, implode('/', array_map('rawurlencode', explode('/', $path)))]);

            $used = false;
            foreach ($hays as $hay) {
                foreach ($prefixes as $prefix) {
                    foreach ($spellings as $spelling) {
                        if (self::names($hay, $prefix . $spelling)) {
                            $used = true;
                            break 3;
                        }
                    }
                }
            }

            if (!$used) {
                $file->delete();
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * Whether a text contains a reference as a whole, not as the start of a longer name.
     *
     * The reference must be followed by the end of the text or by a character that cannot continue
     * a file address in HTML: a quote, whitespace, ?, #, <, >, ), ] or &, or the NUL that joins the
     * page's two fields.
     *
     * @param string $hay The text
     * @param string $needle The reference
     * @return bool
     */
    private static function names(string $hay, string $needle): bool {
        $offset = 0;
        while (($pos = strpos($hay, $needle, $offset)) !== false) {
            $next = substr($hay, $pos + strlen($needle), 1);
            if ($next === '' || strpos("\0?#\"' \t\r\n<>)]&", $next) !== false) {
                return true;
            }
            $offset = $pos + 1;
        }
        return false;
    }

    /**
     * The file areas of this plugin that belong to no page.
     *
     * An area is (context, file area, item id). It belongs to a page when a row has that id and is
     * stored in that context, the system context being stored as 0. Deleted rows count: a page in
     * the recycle bin keeps its files until it is deleted permanently.
     *
     * @return array One object per area: contextid, filearea, itemid, files (count of files, the
     *     directory entries left out) and bytes
     */
    public static function orphan_areas(): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal(self::FILEAREAS, SQL_PARAMS_NAMED, 'area');
        $params['component'] = 'local_page';
        $params['systemcontextid'] = \core\context\system::instance()->id;
        $params['dot'] = '.';

        // A recordset, because no column of the grouping is unique on its own.
        $rows = $DB->get_recordset_sql(
            "SELECT f.contextid, f.filearea, f.itemid,
                    SUM(CASE WHEN f.filename = :dot THEN 0 ELSE 1 END) AS files,
                    SUM(f.filesize) AS bytes
               FROM {files} f
          LEFT JOIN {local_page} p
                 ON p.id = f.itemid
                AND (p.contextid = f.contextid OR (p.contextid = 0 AND f.contextid = :systemcontextid))
              WHERE f.component = :component
                AND f.filearea $insql
                AND p.id IS NULL
           GROUP BY f.contextid, f.filearea, f.itemid
           ORDER BY f.contextid, f.filearea, f.itemid",
            $params
        );

        $areas = [];
        foreach ($rows as $row) {
            $areas[] = (object) [
                'contextid' => (int) $row->contextid,
                'filearea' => (string) $row->filearea,
                'itemid' => (int) $row->itemid,
                'files' => (int) $row->files,
                'bytes' => (int) $row->bytes,
            ];
        }
        $rows->close();

        return $areas;
    }

    /**
     * Delete every file area of this plugin that belongs to no page, or only list them.
     *
     * The decision is taken under the lock a category move holds while it carries a page's files into
     * the new context ahead of the row ({@see lifecycle::category_moved()}): in between, those files
     * belong to no row of the context they are in.
     *
     * @param bool $run True to delete; false lists what would be deleted and deletes nothing
     * @return array|null The areas found, as orphan_areas() returns them; null when the lock was busy
     */
    public static function collect_orphans(bool $run): ?array {
        $lock = \core\lock\lock_config::get_lock_factory('local_page')->get_lock('menuname', 10);
        if (!$lock) {
            return null;
        }

        try {
            $areas = self::orphan_areas();
            if ($run) {
                $fs = get_file_storage();
                foreach ($areas as $area) {
                    $files = $fs->get_area_files($area->contextid, 'local_page', $area->filearea, $area->itemid, 'id', false);
                    foreach ($files as $file) {
                        self::forget_h5p($file);
                    }
                    $fs->delete_area_files($area->contextid, 'local_page', $area->filearea, $area->itemid);
                }
            }
            return $areas;
        } finally {
            $lock->release();
        }
    }

    /**
     * Delete the H5P package core deployed from a file, if it deployed one.
     *
     * Core keeps the deployment ({h5p} and its content files) after the package file is gone, keyed
     * by the file's path hash, so the deployment has to go before the file does.
     *
     * @param \stored_file $file A file of a page's areas
     * @return void
     */
    public static function forget_h5p(\stored_file $file): void {
        global $DB;

        if (\core_text::strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) !== 'h5p') {
            return;
        }

        $h5p = $DB->get_record('h5p', ['pathnamehash' => $file->get_pathnamehash()]);
        if ($h5p) {
            \core_h5p\api::delete_content($h5p, new \core_h5p\factory());
        }
    }
}
