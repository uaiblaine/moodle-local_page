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
 * Uninstall hook: removes the rows this plugin wrote to core's shortlink table.
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Removes this plugin's rows from core's shortlink table when the plugin is removed.
 *
 * Core never deletes from that table, and a /p/ code left behind would point core at a handler that
 * no longer exists. The plugin's files need nothing here: uninstall_plugin() calls
 * file_storage::delete_component_files() after this function returns, and that deletes every file
 * the component owns, in every context.
 *
 * @return bool
 */
function xmldb_local_page_uninstall() {
    global $DB;

    $DB->delete_records('shortlink', ['component' => 'local_page']);

    return true;
}
