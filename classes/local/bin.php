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
 * The recycle bin: what deleting, restoring and permanently deleting a page does.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * The recycle bin: what deleting, restoring and permanently deleting a page does.
 *
 * A deleted page keeps its row, its files and its public short code, and gives up its friendly URL
 * ({@see slug::deleted_name()}): nobody can reach it, and another page may take the address. It stays
 * in its context's bin until somebody restores it, deletes it permanently, or the bin's retention
 * (the binexpiry setting) runs out, when the purge_deleted_pages task deletes it permanently.
 *
 * Every method that takes a context acts only on a page stored in that context, so the caller's
 * capability check in that context is the check for the page. The scripts check the capability; this
 * class checks the context, the deleted flag and nothing else, and writes no output.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bin {
    /** @var int Retention used while the binexpiry setting has never been saved: seven days. */
    public const DEFAULT_EXPIRY = 7 * DAYSECS;

    /**
     * Move a page to its context's bin.
     *
     * The friendly URL is released in the same write, and the time is recorded for the retention.
     * The page's files and its /p/ code stay: the file route and the shortlink handler refuse a deleted
     * page, and a restore gives both back as they were.
     *
     * @param int $pageid Page id
     * @param \core\context $context Context the caller checked its capability in
     * @return bool True when a live page of that context was deleted; false when there was none
     */
    public static function delete(int $pageid, \core\context $context): bool {
        global $DB;

        $row = $DB->get_record('local_page', [
            'id' => $pageid,
            'contextid' => scope::stored_contextid($context),
            'deleted' => 0,
        ], 'id, menuname');
        if (!$row) {
            return false;
        }

        $DB->update_record('local_page', (object) [
            'id' => $pageid,
            'deleted' => 1,
            'timedeleted' => time(),
            'menuname' => slug::deleted_name((string) $row->menuname, $pageid),
        ]);

        \local_page\event\page_deleted::create([
            'objectid' => $pageid,
            'context' => $context,
        ])->trigger();

        return true;
    }

    /**
     * Take a page out of its context's bin.
     *
     * The page asks for its old friendly URL back ({@see slug::restored_name()}). When another live
     * page of the context took that address meanwhile, that page keeps it and the restored one gets
     * the first free -<id> form, the way a page arriving from another category does. The decision and
     * the write happen under the lock the editor's save takes, so no save can take the address in
     * between.
     *
     * @param int $pageid Page id
     * @param \core\context $context Context the caller checked its capability in
     * @return \stdClass name (the page name as stored), menuname (the friendly URL it now has) and
     *     renamed (true when that is not the one it asked for back)
     * @throws \moodle_exception pagenotfound when the context's bin holds no such page; binrestorebusy
     *     while a save holds the lock
     */
    public static function restore(int $pageid, \core\context $context): \stdClass {
        global $DB;

        $contextid = scope::stored_contextid($context);

        $lock = \core\lock\lock_config::get_lock_factory('local_page')->get_lock('menuname', 10);
        if (!$lock) {
            throw new \moodle_exception('binrestorebusy', 'local_page');
        }

        try {
            $row = $DB->get_record('local_page', [
                'id' => $pageid,
                'contextid' => $contextid,
                'deleted' => 1,
            ], 'id, pagename, menuname');
            if (!$row) {
                throw new \moodle_exception('pagenotfound', 'local_page');
            }

            $wanted = slug::restored_name((string) $row->menuname, $pageid);
            $menuname = slug::unique_in_context($wanted, $pageid, $contextid);
            $DB->update_record('local_page', (object) [
                'id' => $pageid,
                'deleted' => 0,
                'timedeleted' => 0,
                'menuname' => $menuname,
            ]);
        } finally {
            $lock->release();
        }

        \local_page\event\page_restored::create([
            'objectid' => $pageid,
            'context' => $context,
        ])->trigger();

        return (object) [
            'name' => (string) $row->pagename,
            'menuname' => $menuname,
            'renamed' => $menuname !== $wanted,
        ];
    }

    /**
     * Delete a page of its context's bin permanently, with its files and its public short code.
     *
     * Only a page in the bin can be deleted this way: a live page goes through delete() first.
     *
     * @param int $pageid Page id
     * @param \core\context $context Context the caller checked its capability in
     * @return bool True when the bin held the page and it was deleted; false when it did not
     */
    public static function purge(int $pageid, \core\context $context): bool {
        global $DB;

        $exists = $DB->record_exists('local_page', [
            'id' => $pageid,
            'contextid' => scope::stored_contextid($context),
            'deleted' => 1,
        ]);
        if (!$exists) {
            return false;
        }

        self::erase($pageid, $context);
        return true;
    }

    /**
     * Delete every page of a context's bin permanently.
     *
     * @param \core\context $context Context the caller checked its capability in
     * @return int Number of pages deleted
     */
    public static function empty(\core\context $context): int {
        $count = 0;
        foreach (self::items($context) as $row) {
            self::erase((int) $row->id, $context);
            $count++;
        }
        return $count;
    }

    /**
     * The pages in a context's bin, most recently deleted first.
     *
     * @param \core\context $context Context whose bin is listed
     * @return array Rows with id, pagename, menuname and timedeleted, keyed by id
     */
    public static function items(\core\context $context): array {
        global $DB;

        return $DB->get_records(
            'local_page',
            ['contextid' => scope::stored_contextid($context), 'deleted' => 1],
            'timedeleted DESC, id DESC',
            'id, pagename, menuname, timedeleted'
        );
    }

    /**
     * How many pages a context's bin holds.
     *
     * @param \core\context $context Context whose bin is counted
     * @return int
     */
    public static function count(\core\context $context): int {
        global $DB;

        return $DB->count_records('local_page', ['contextid' => scope::stored_contextid($context), 'deleted' => 1]);
    }

    /**
     * How long a deleted page stays in the bin, in seconds; 0 keeps it until it is deleted by hand.
     *
     * @return int
     */
    public static function expiry(): int {
        $expiry = get_config('local_page', 'binexpiry');
        return $expiry === false ? self::DEFAULT_EXPIRY : max(0, (int) $expiry);
    }

    /**
     * When a page deleted at a given time leaves the bin for good, or null when nothing removes it.
     *
     * @param int $timedeleted When the page was deleted
     * @return int|null
     */
    public static function purge_time(int $timedeleted): ?int {
        $expiry = self::expiry();
        return $expiry > 0 && $timedeleted > 0 ? $timedeleted + $expiry : null;
    }

    /**
     * Delete permanently every page whose time in the bin is over, and every page that can never be restored.
     *
     * A page is over its time when it was deleted at least binexpiry seconds ago; a retention of 0
     * keeps every page. A page whose context no longer exists can never be restored: core deleted its
     * category, and its files with the category's context ({@see lifecycle::category_deleted()}). Such
     * a row is deleted whatever the retention, with its short codes.
     *
     * @param int $now The time to measure the retention against
     * @return int Number of pages deleted
     */
    public static function purge_expired(int $now): int {
        global $DB;

        $count = 0;

        $expiry = self::expiry();
        if ($expiry > 0) {
            $rows = $DB->get_records_select(
                'local_page',
                'deleted = 1 AND timedeleted > 0 AND timedeleted <= :cutoff',
                ['cutoff' => $now - $expiry],
                'id ASC',
                'id, contextid'
            );
            foreach ($rows as $row) {
                // A row whose context is gone is the orphan pass's, below.
                $context = (int) $row->contextid === 0
                    ? \core\context\system::instance()
                    : \core\context::instance_by_id((int) $row->contextid, IGNORE_MISSING);
                if ($context) {
                    self::erase((int) $row->id, $context);
                    $count++;
                }
            }
        }

        // The stored convention writes 0 for the system context, which always exists.
        $orphans = $DB->get_fieldset_sql(
            'SELECT p.id
               FROM {local_page} p
          LEFT JOIN {context} c ON c.id = p.contextid
              WHERE p.deleted = 1 AND p.contextid <> 0 AND c.id IS NULL
           ORDER BY p.id ASC'
        );
        foreach ($orphans as $pageid) {
            self::erase((int) $pageid, null);
            $count++;
        }

        return $count;
    }

    /**
     * Delete a page's row, files, deployed H5P packages and short codes, and log it.
     *
     * The writes share one transaction, so a failure leaves the page in the bin with everything it
     * had. A context of null is a page whose context is gone: core already deleted its files, and the
     * event is logged against the system context.
     *
     * @param int $pageid Page id
     * @param \core\context|null $context The page's context, or null when it no longer exists
     * @return void
     */
    private static function erase(int $pageid, ?\core\context $context): void {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        if ($context !== null) {
            $fs = get_file_storage();
            foreach (files::FILEAREAS as $filearea) {
                foreach ($fs->get_area_files($context->id, 'local_page', $filearea, $pageid, 'id', false) as $file) {
                    files::forget_h5p($file);
                }
                $fs->delete_area_files($context->id, 'local_page', $filearea, $pageid);
            }
        }

        links::forget($pageid);
        $DB->delete_records('local_page', ['id' => $pageid]);

        $transaction->allow_commit();

        \local_page\event\page_purged::create([
            'objectid' => $pageid,
            'context' => $context ?? \core\context\system::instance(),
        ])->trigger();
    }
}
