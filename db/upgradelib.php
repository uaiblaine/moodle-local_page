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
 * The code the upgrade steps of local_page run, frozen with the steps that call it.
 *
 * An upgrade step runs against the schema of its own version, whatever release the site is
 * upgrading to, so it must not call code that goes on changing with the plugin: a class method that
 * later reads a column added by a later step would kill every upgrade that starts below that step.
 * The functions here are therefore never edited once a step calls them. A change of behaviour is a
 * new function and a new step.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Brings every stored friendly URL into line with the uniqueness rules, in place.
 *
 * A frozen copy of \local_page\local\slug::normalise_all(), run by steps 2026092202 and 2026092210,
 * which reads only the columns that exist from step 2026092201 on: id, menuname, deleted and
 * contextid. tests/upgradelib_test.php runs both on the same rows and requires the same result, so a
 * change to either one that is not made to the other shows up there.
 *
 * In four passes, in id order: every value is trimmed, lower-cased and cut to the column width; a
 * live row with an empty slug is named page-<id>; a deleted row gets <slug>-deleted-<id>, releasing
 * its address; and among live rows of one context sharing a slug the lowest id keeps it while each
 * other row moves to the first form of -<id>, -<id>-2, -<id>-3 and so on that no row claimed earlier
 * in the pass and no other live row of the context holds. A second run therefore changes nothing.
 *
 * @return int Number of rows whose menuname was rewritten
 */
function local_page_upgrade_normalise_slugs(): int {
    global $DB;

    $maxlength = 255;

    // Shortens a slug so that appending $suffix still fits the column.
    $stem = static function (string $name, string $suffix) use ($maxlength): string {
        $room = $maxlength - \core_text::strlen($suffix);
        if ($room <= 0) {
            return '';
        }
        return \core_text::substr($name, 0, $room);
    };

    // Whether a slug already carries a suffix.
    $endswith = static function (string $name, string $suffix): bool {
        return \core_text::substr($name, -\core_text::strlen($suffix)) === $suffix;
    };

    $rows = $DB->get_records('local_page', null, 'id ASC', 'id, menuname, deleted, contextid');

    // Passes 1 to 3: everything that depends on one row alone.
    $wanted = [];
    foreach ($rows as $row) {
        $id = (int) $row->id;
        $name = \core_text::substr(\core_text::strtolower(trim((string) $row->menuname)), 0, $maxlength);

        if ((int) $row->deleted !== 0) {
            $suffix = '-deleted-' . $id;
            $base = \core_text::strtolower(trim($name));
            if ($base === '') {
                $base = 'page-' . $id;
            }
            if ($endswith($base, $suffix)) {
                $name = \core_text::substr($base, 0, $maxlength);
            } else {
                $name = $stem($base, $suffix) . $suffix;
            }
        } else if ($name === '') {
            $name = \core_text::substr('page-' . $id, 0, $maxlength);
        }

        $wanted[$id] = $name;
    }

    // Pass 4: uniqueness among the rows that are still live, within each context.
    $held = [];
    foreach ($rows as $row) {
        if ((int) $row->deleted === 0) {
            $held[(int) ($row->contextid ?? 0)][$wanted[(int) $row->id]] = true;
        }
    }

    $taken = [];
    foreach ($rows as $row) {
        if ((int) $row->deleted !== 0) {
            continue;
        }
        $id = (int) $row->id;
        $scope = (int) ($row->contextid ?? 0);
        if (isset($taken[$scope][$wanted[$id]])) {
            $idsuffix = '-' . $id;
            $root = $wanted[$id];
            if ($endswith($root, $idsuffix)) {
                $root = \core_text::substr($root, 0, \core_text::strlen($root) - \core_text::strlen($idsuffix));
            }

            $candidate = $stem($root, $idsuffix) . $idsuffix;
            for ($counter = 2; isset($taken[$scope][$candidate]) || isset($held[$scope][$candidate]); $counter++) {
                $suffix = $idsuffix . '-' . $counter;
                $candidate = $stem($root, $suffix) . $suffix;
            }
            $wanted[$id] = $candidate;
        }
        $taken[$scope][$wanted[$id]] = true;
    }

    $changed = 0;
    foreach ($rows as $row) {
        $id = (int) $row->id;
        if ($wanted[$id] !== (string) $row->menuname) {
            $DB->set_field('local_page', 'menuname', $wanted[$id], ['id' => $id]);
            $changed++;
        }
    }

    return $changed;
}

/**
 * Makes the hidetitle column NOT NULL, as db/install.xml declares it, filling any NULL with 'no'.
 *
 * Step 2025060200 added the column as nullable while install.xml declares it NOT NULL with the
 * default 'no', so a site that came through that step and a fresh install had different schemas.
 * The NULLs are filled first because a database refuses, or silently rewrites, a NULL in a column
 * being made NOT NULL. Nothing happens when the column does not exist.
 *
 * @return void
 */
function local_page_upgrade_hidetitle_notnull(): void {
    global $DB;

    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_page');
    $field = new xmldb_field('hidetitle', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, 'no', 'metarobots');

    if (!$dbman->field_exists($table, $field)) {
        return;
    }

    $DB->set_field_select('local_page', 'hidetitle', 'no', 'hidetitle IS NULL');
    $dbman->change_field_notnull($table, $field);
}

/**
 * Gives every site page its own pagecontent area: copies each shared item-id-0 file to item id = page id.
 *
 * Until step 2026102800 every site page stored its embedded files in one area of the system context
 * under item id 0, and each save made that whole area equal to the editor's draft, so two editors
 * saving at once deleted each other's new files. A site page now keeps them under its own id, as a
 * category page always has. Its stored text needs no rewrite: the editor stores @@PLUGINFILE@@/<path>,
 * which carries no item id.
 *
 * A file is copied to every site page whose pagecontent or contenthtml names it through the
 * placeholder, deleted pages included, so that a page restored later still has its files. A copy is
 * a new {files} row over the same content hash: no bytes are duplicated on disk. A file no page names
 * is not copied. The item-id-0 originals are left in place: nothing serves them any longer, and
 * removing them is the orphan collector's job, not an upgrade step's. A copy that already exists is
 * skipped, so a second run copies nothing.
 *
 * Reads only id, contextid, pagecontent and contenthtml, which exist from step 2026092202 on.
 *
 * @return int Number of files copied
 */
function local_page_upgrade_split_site_pagecontent(): int {
    global $DB;

    $system = \context_system::instance();
    $fs = get_file_storage();
    $files = $fs->get_area_files($system->id, 'local_page', 'pagecontent', 0, 'filepath, filename', false);
    if ($files === []) {
        return 0;
    }

    $copied = 0;
    $rows = $DB->get_recordset('local_page', ['contextid' => 0], 'id ASC', 'id, pagecontent, contenthtml');
    foreach ($rows as $row) {
        $pageid = (int) $row->id;
        $hay = (string) $row->pagecontent . "\0" . (string) $row->contenthtml;
        foreach ($files as $file) {
            if (!local_page_upgrade_text_names_file($hay, $file->get_filepath(), $file->get_filename())) {
                continue;
            }
            if ($fs->file_exists($system->id, 'local_page', 'pagecontent', $pageid, $file->get_filepath(), $file->get_filename())) {
                continue;
            }
            $fs->create_file_from_storedfile(['itemid' => $pageid], $file);
            $copied++;
        }
    }
    $rows->close();

    return $copied;
}

/**
 * Whether a page's text names a file of its area through the @@PLUGINFILE@@ placeholder.
 *
 * The path is looked for as written and with each segment URL-encoded, the two spellings the editor
 * stores. A match must end the reference: the next character is the end of the text or one that
 * cannot continue a file name in HTML (quote, whitespace, ?, #, <, ), ], & or the NUL that joins the
 * page's two fields), so a.png is not found inside a.png2.
 *
 * @param string $hay The page's pagecontent and contenthtml, joined by a NUL
 * @param string $filepath The file's path, with leading and trailing slashes
 * @param string $filename The file's name
 * @return bool
 */
function local_page_upgrade_text_names_file(string $hay, string $filepath, string $filename): bool {
    $relative = trim($filepath, '/');
    $suffix = $relative === '' ? $filename : $relative . '/' . $filename;
    $encoded = implode('/', array_map('rawurlencode', explode('/', $suffix)));

    foreach (array_unique(['@@PLUGINFILE@@/' . $suffix, '@@PLUGINFILE@@/' . $encoded]) as $needle) {
        $offset = 0;
        while (($pos = strpos($hay, $needle, $offset)) !== false) {
            $next = substr($hay, $pos + strlen($needle), 1);
            if ($next === '' || strpos("\0?#\"' \t\r\n<)]&", $next) !== false) {
                return true;
            }
            $offset = $pos + 1;
        }
    }

    return false;
}

/**
 * Starts the recycle bin's clock for the pages deleted before the bin existed.
 *
 * Their time of deletion was never recorded, so they are stamped with the time of the upgrade:
 * the retention then runs from the upgrade, and nothing is deleted permanently on the first run of
 * the task. Only rows that are deleted and carry no time yet are written, so a second run changes
 * nothing.
 *
 * Reads only deleted and timedeleted; timedeleted exists from step 2026102900 on.
 *
 * @param int $now The time to stamp
 * @return int Number of rows stamped
 */
function local_page_upgrade_stamp_deleted_pages(int $now): int {
    global $DB;

    $count = $DB->count_records('local_page', ['deleted' => 1, 'timedeleted' => 0]);
    $DB->set_field('local_page', 'timedeleted', $now, ['deleted' => 1, 'timedeleted' => 0]);

    return $count;
}
