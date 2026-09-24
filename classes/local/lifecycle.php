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
 * Core deletes a category in one of two ways, and each first calls a lib.php callback in every
 * plugin that declares it ({@see \core_course_category::delete_full()} and
 * {@see \core_course_category::delete_move()}):
 *
 * - delete_full() calls local_page_pre_course_category_delete(), then deletes the category's
 *   children, courses and content, the category row, and finally its context, which purges every
 *   file of every component stored there. Each child category is deleted by its own delete_full(),
 *   which calls the callback again for that child, so this class never walks the category tree.
 * - delete_move() calls local_page_pre_course_category_delete_move(), then re-parents the
 *   children, moves the courses, cohorts, grades and content bank to the new parent, and deletes
 *   the category row and its context the same way. The children keep their own contexts, so their
 *   pages stay where they are.
 *
 * Both callbacks run before core has changed anything. Because the context is deleted afterwards, a
 * page that is to survive the deletion must have its files out of that context by the time the
 * callback returns.
 *
 * Nothing here writes output, redirects or logs, so tests can call both entry points exactly as core
 * does.
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
     * The order is the property, and it runs as one block for each page:
     *
     * 1. the page's files, both areas, into the new parent's context: core deletes the old context
     *    and every file in it once this returns, which is also why it relocates the content bank first;
     * 2. then the row, so that it names the context its files are now in;
     * 3. then its friendly URL, which was unique in the old context and may not be in the new one:
     *    a page the new parent already holds keeps its slug, and the arriving page gains its id,
     *    the way slug::normalise_all() settles a duplicate.
     *
     * The loop runs under the lock the editor's save takes, so a page saved into the new parent at the
     * same moment cannot claim a slug between the check and the write.
     *
     * Deleted rows move too, with their files, so a page deleted by hand can still be restored by hand;
     * their -deleted-<id> slugs are never compared, since uniqueness binds only live pages. The public
     * short codes need nothing: a code holds a page id, and the handler answers the page's current
     * address.
     *
     * A move to the root is refused while the category holds a live page, with the error core's own
     * web service gives for that move: a page belongs to a course category or to the site, never to
     * the root. Core cannot complete that move anyway (delete_move() fails resolving the root's
     * category context, after the callbacks); refusing here fails before core has changed anything.
     * A category holding no live page is not this plugin's to refuse.
     *
     * There is no rollback. The loop opens no transaction, and course/management.php calls
     * delete_move() outside one, so a database failure part-way leaves the pages already handled in the
     * new parent with their files and the rest in the old category with theirs; the exception stops
     * delete_move() before core has changed anything, so the category stays too. Running the move again
     * completes it: a page already carried no longer names the old context, and a page whose files moved
     * before its row finds its old area empty. The exception is a failure inside
     * move_area_files_to_new_context(), which copies an area's files before deleting the originals: the
     * copies left in the new context make the next run stop on that page with a
     * stored_file_creation_exception until they are deleted. The web service core_course_delete_categories
     * wraps the whole deletion in one delegated transaction, so none of this arises through it.
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
