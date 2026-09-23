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
 * Uninstall hook: remove files stored in system context (not purged automatically).
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Cleans up file areas when this plugin is removed.
 *
 * A page's files live in the page's own context, so there is no single area to empty: every
 * context named by a row has to be visited, plus the system context itself — which is where the
 * legacy rows keep their files and where the column's 0 resolves to, and which must be cleaned
 * even when the table holds no rows at all.
 *
 * It also removes the plugin's rows from core's shortlink table, which nothing else would: a /p/
 * code left behind would point core at a handler that no longer exists.
 *
 * @return bool
 */
function xmldb_local_page_uninstall() {
    global $DB;

    $fs = get_file_storage();
    $systemcontextid = (int) context_system::instance()->id;

    $contextids = [$systemcontextid => $systemcontextid];

    $dbman = $DB->get_manager();
    $table = new xmldb_table('local_page');
    $field = new xmldb_field('contextid');
    if ($dbman->table_exists($table) && $dbman->field_exists($table, $field)) {
        // A plugin uninstalled before this column was added has nothing else to visit.
        $stored = $DB->get_fieldset_sql('SELECT DISTINCT contextid FROM {local_page}');
        foreach ($stored as $contextid) {
            $contextid = (int) $contextid === 0 ? $systemcontextid : (int) $contextid;
            $contextids[$contextid] = $contextid;
        }
    }

    foreach ($contextids as $contextid) {
        $fs->delete_area_files($contextid, 'local_page', 'pagecontent');
        $fs->delete_area_files($contextid, 'local_page', 'ogimage');
    }

    // The public short codes minted for category pages: core never deletes from its shortlink table.
    $DB->delete_records('shortlink', ['component' => 'local_page']);

    return true;
}
