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
 * Lists, and with --run deletes, the file areas of local_page that belong to no page.
 *
 * The same collection the weekly task collect_orphan_files runs ({@see \local_page\local\files}).
 * Without --run nothing is deleted, so a first look at a site is always safe.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(['run' => false, 'help' => false], ['h' => 'help']);

if ($unrecognized) {
    cli_error(get_string('cliunknowoption', 'admin', implode("\n  ", $unrecognized)));
}

if ($options['help']) {
    echo "Lists the file areas of local_page that belong to no page: files under item id 0 (the area
every site page shared before version 2026102800) and files whose page no longer exists in the
context they are stored in. A page in the recycle bin still owns its files.

Options:
--run         Delete the areas listed. Without it nothing is deleted.
-h, --help    Print this help.

Example:
\$ sudo -u www-data /usr/bin/php local/page/cli/orphan_files.php
\$ sudo -u www-data /usr/bin/php local/page/cli/orphan_files.php --run
";
    exit(0);
}

$areas = \local_page\local\files::collect_orphans((bool) $options['run']);
if ($areas === null) {
    cli_error('A page save or a category move holds the lock. Try again in a moment.');
}

foreach ($areas as $area) {
    mtrace(sprintf(
        'context %d, %s, item %d: %d file(s), %s',
        $area->contextid,
        $area->filearea,
        $area->itemid,
        $area->files,
        display_size($area->bytes)
    ));
}

$files = array_sum(array_column($areas, 'files'));
if ($options['run']) {
    mtrace(count($areas) . " orphan file area(s) deleted, {$files} file(s).");
} else {
    mtrace(count($areas) . " orphan file area(s), {$files} file(s). Dry run: nothing was deleted; run again with --run.");
}
