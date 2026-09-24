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
    require_once(__DIR__ . '/upgradelib.php');
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

    if ($oldversion < 2026092201) {
        // A page now records the context it belongs to. The column defaults to 0, which means the
        // system context: context ids are row ids assigned at install time, so no XMLDB default
        // can name one. Every existing row therefore reads as a site-wide page with nothing to
        // migrate, and categoryid stays NULL until a page is authored in a category.
        //
        // This step must stay numbered below the slug normalisation, which selects contextid. A
        // fresh install reads install.xml and never walks these steps, so only an upgrade from an
        // earlier release shows a wrong order, as a fatal error part-way through the upgrade.

        $table = new xmldb_table('local_page');

        $field = new xmldb_field('contextid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'contextid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Friendly URLs are unique per context, so the lookup is by both.
        $index = new xmldb_index('contextmenuname', XMLDB_INDEX_NOTUNIQUE, ['contextid', 'menuname']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026092201, 'local', 'page');
    }

    if ($oldversion < 2026092202) {
        // Friendly URLs are now unique among pages that have not been deleted, and a deleted page
        // releases the one it was holding. Existing rows predate both rules, so bring them into
        // line once: empty slugs are named, duplicates gain their page id, and deleted rows are
        // mangled. The routine is idempotent, so re-running this step is harmless.
        //
        // Every row the step above touched carries the 0 that means the system context, so this
        // pass groups them all together and sees exactly the site-wide table it was written for.
        // The routine is the frozen copy in db/upgradelib.php, never the class, so that a later
        // change to the class cannot make this step read a column that does not exist yet.
        local_page_upgrade_normalise_slugs();

        upgrade_plugin_savepoint(true, 2026092202, 'local', 'page');
    }

    if ($oldversion < 2026092203) {
        // A page now records whether the author of its content was trusted with unclean HTML at
        // the moment it was saved, in core's trusttext sense. The column defaults to 0, which is
        // the safe reading for every row written before this release: a category page whose flag
        // is 0 is cleaned on the way out and on the way back into the editor. Site-wide pages
        // ignore the flag and keep their trusted rendering, so nothing an existing site is
        // serving changes.

        $table = new xmldb_table('local_page');

        $field = new xmldb_field('contenttrust', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'contenthtml');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026092203, 'local', 'page');
    }

    if ($oldversion < 2026092209) {
        // Step 2025060200 added hidetitle as nullable, while install.xml declares it NOT NULL with
        // the default 'no', so an upgraded site and a fresh install had different schemas. Bring
        // the upgraded ones into line: NULLs become 'no', then the column becomes NOT NULL.
        local_page_upgrade_hidetitle_notnull();

        upgrade_plugin_savepoint(true, 2026092209, 'local', 'page');
    }

    if ($oldversion < 2026092210) {
        // The slug normalisation of step 2026092202 could hand a moved page a slug another live
        // page of the same context held, and a second run kept it. It is run again, corrected, so
        // that a site which already passed that step loses the duplicates it may have been left
        // with. The routine is idempotent: on a site with none this step changes nothing.
        local_page_upgrade_normalise_slugs();

        upgrade_plugin_savepoint(true, 2026092210, 'local', 'page');
    }

    return true;
}
