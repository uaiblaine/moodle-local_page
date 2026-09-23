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
 * Local Page Upgrade
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 *
 * This is to upgrade the older versions of the plugin.
 *
 * @param integer $oldversion
 * @return bool
 * @copyright   2017 LearningWorks Ltd
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
function xmldb_local_page_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025060200) {
        // Define field meta to be added to local_pages.

        $table = new xmldb_table('local_page');
        $field = new xmldb_field('hidetitle', XMLDB_TYPE_CHAR, '10', null, null, null, 'no', 'pagename');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025060200, 'local', 'page');
    }

    if ($oldversion < 2025100804) {
        // Define field contenthtml to be added to local_page.

        $table = new xmldb_table('local_page');
        $field = new xmldb_field('contenthtml', XMLDB_TYPE_TEXT, null, null, null, null, null, 'onlyloggedin');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2025100804, 'local', 'page');
    }

    if ($oldversion < 2026050806) {
        // Friendly URLs are now unique among pages that are not deleted, and a deleted page releases its own.
        // Bring existing rows into line once: empty slugs are named, live duplicates gain their page id and
        // deleted rows are renamed. slug::normalise_all() is idempotent, so running this step again is harmless.
        \local_page\local\slug::normalise_all();

        upgrade_plugin_savepoint(true, 2026050806, 'local', 'page');
    }

    return true;
}
