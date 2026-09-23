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
 * What becomes of a category's pages when core deletes the category.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * What becomes of a category's pages when core deletes the category.
 *
 * Core deletes a category in one of two ways, and each asks every plugin first through a lib.php
 * callback (course/classes/category.php, MOODLE_502_STABLE):
 *
 * - delete_full() calls local_page_pre_course_category_delete() and then deletes the category's
 *   children, courses and content, the category row, and finally its CONTEXT, which purges every
 *   file of every component stored there. Each child category is deleted by its own delete_full(),
 *   which calls the callback again for that child: core's recursion reaches the children, so this
 *   class never walks the category tree itself.
 * - delete_move() calls local_page_pre_course_category_delete_move() and then re-parents the
 *   children, moves the courses, cohorts, grades and content bank to the new parent, and deletes
 *   the category row and its context in the same way. The children keep their own contexts, and so
 *   their pages stay where they are.
 *
 * Both callbacks run before core has touched anything, and the context deletion that follows them
 * is why the files cannot be left for later: a page that is to survive the deletion has to have its
 * files out of the dying context by the time the callback returns.
 *
 * Nothing here writes output, redirects or logs, so both entry points can be called from a test
 * exactly as core calls them.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class lifecycle {
    /** @var array The file areas a category page keeps under its own id: both move with the page. */
    private const FILEAREAS = ['pagecontent', 'ogimage'];

    /**
     * Soft-delete the pages of a category that core is about to delete with all its content.
     *
     * Every live page of the category's context is marked deleted with its friendly URL released —
     * the shape pages.php gives a page deleted by hand, so a restore by hand meets the same row — and
     * every row of the context loses its public short codes, which core never deletes on its own.
     *
     * The files are not touched: core purges the whole context right after this returns. Nor are the
     * pages of the category's children, which live in contexts of their own and are handed to this
     * method by core's recursion, one child at a time.
     *
     * @param int $categoryid The category being deleted
     * @return void
     */
    public static function category_deleted(int $categoryid): void {
        global $DB;

        $context = \core\context\coursecat::instance($categoryid, IGNORE_MISSING);
        if (!$context) {
            // Every row is written with its category's context id, so a category without one stores no page.
            return;
        }

        $rows = $DB->get_records('local_page', ['contextid' => (int) $context->id], 'id ASC', 'id, menuname, deleted');
        foreach ($rows as $row) {
            $pageid = (int) $row->id;
            if ((int) $row->deleted === 0) {
                $DB->update_record('local_page', (object) [
                    'id' => $pageid,
                    'deleted' => 1,
                    'menuname' => slug::deleted_name((string) $row->menuname, $pageid),
                ]);
            }
            links::forget($pageid);
        }
    }

    /**
     * Carry the pages of a category whose content core is moving to a new parent before deleting it.
     *
     * The order below is the property, and it runs as one block for each page:
     *
     * 1. the page's files, both areas, into the new parent's context — core deletes the old context
     *    and every file in it once this returns, the same way it relocates the content bank first;
     * 2. then the row, so that it names the context its files are now in;
     * 3. then its friendly URL, which was unique in the old context and may not be in the new one:
     *    a page the new parent already holds keeps its slug, and the arriving page gains its id,
     *    the way slug::normalise_all() settles a duplicate.
     *
     * The whole loop runs under the lock the editor's save takes, so a page saved into the new parent
     * at the same moment cannot claim a slug between the check and the write.
     *
     * Deleted rows move too, with their files, so that a page deleted by hand can still be restored by
     * hand; their slugs already carry -deleted-<id> and are never compared, since uniqueness only
     * binds live pages. The public short codes need nothing: a code holds a page id, and the handler
     * answers the page's current address.
     *
     * A move to the root is refused while the category holds a live page, with the error core's own
     * web service gives for that move: a page belongs to a course category or to the site, never to
     * the root, and core cannot complete that move anyway — delete_move() dies resolving the root's
     * category context, after the callbacks, leaving the category in place. Refusing here, before core
     * has changed anything, leaves the whole site exactly as it was. A category holding no live page
     * is not this plugin's to refuse.
     *
     * There is no rollback, by design: this is the accepted risk of the stage-8 review. The loop opens
     * no transaction, and core's management screen (course/management.php) calls delete_move() outside
     * one, so a database failure part-way through a category holding several pages leaves the pages
     * already handled in the new parent with their files, and the rest in the old category with
     * theirs. The exception stops delete_move() before core has changed anything, so the category
     * stays too. Running the same move again completes it: each page is handled on its own, a page
     * already carried no longer names the old context and is not selected again, and a page whose
     * files moved before its row did finds its old area empty and moves nothing. One window is
     * narrower: core's move_area_files_to_new_context() copies every file of an area and only then
     * deletes the originals, so a failure inside that copy leaves some copies in the new context beside
     * the intact originals, and the next run stops on that page with a stored_file_creation_exception
     * until those copies are deleted from the new context. Both cases were measured on m502 on
     * 2026-09-23. Through core's web service core_course_delete_categories none of this arises: it
     * wraps the whole deletion in one delegated transaction, which rolls every row back, file records
     * included.
     *
     * @param int $categoryid The category being deleted
     * @param int $newparentid The category its content moves to; 0 is the root
     * @return void
     * @throws \moodle_exception movecatcontentstoroot for a move to the root; categorymovebusy while a save holds the lock
     */
    public static function category_moved(int $categoryid, int $newparentid): void {
        global $DB;

        $oldcontext = \core\context\coursecat::instance($categoryid, IGNORE_MISSING);
        if (!$oldcontext) {
            // Every row is written with its category's context id, so a category without one stores no page.
            return;
        }
        $oldcontextid = (int) $oldcontext->id;

        $rows = $DB->get_records('local_page', ['contextid' => $oldcontextid], 'id ASC', 'id, menuname, deleted');
        if (!$rows) {
            return;
        }

        if ($newparentid <= 0) {
            foreach ($rows as $row) {
                if ((int) $row->deleted === 0) {
                    throw new \moodle_exception('movecatcontentstoroot', 'error');
                }
            }
            return;
        }

        $newcontextid = (int) scope::for_category($newparentid)->id;
        $fs = get_file_storage();

        $lock = \core\lock\lock_config::get_lock_factory('local_page')->get_lock('menuname', 10);
        if (!$lock) {
            throw new \moodle_exception('categorymovebusy', 'local_page');
        }

        try {
            foreach ($rows as $row) {
                $pageid = (int) $row->id;

                foreach (self::FILEAREAS as $filearea) {
                    $fs->move_area_files_to_new_context($oldcontextid, $newcontextid, 'local_page', $filearea, $pageid);
                }

                $DB->update_record('local_page', (object) [
                    'id' => $pageid,
                    'contextid' => $newcontextid,
                    'categoryid' => $newparentid,
                ]);

                if ((int) $row->deleted === 0) {
                    $menuname = slug::unique_in_context((string) $row->menuname, $pageid, $newcontextid);
                    if ($menuname !== (string) $row->menuname) {
                        $DB->set_field('local_page', 'menuname', $menuname, ['id' => $pageid]);
                    }
                }
            }
        } finally {
            $lock->release();
        }
    }
}
