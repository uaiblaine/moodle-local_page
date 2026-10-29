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
 * Scheduled task: delete the file areas that belong to no page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\task;

/**
 * Scheduled task: delete the file areas that belong to no page.
 *
 * All the rules are {@see \local_page\local\files::collect_orphans()}'s. cli/orphan_files.php runs
 * the same collection on demand, and lists it without deleting by default.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class collect_orphan_files extends \core\task\scheduled_task {
    /**
     * The task's name, as the scheduled tasks screen shows it.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskcollectorphanfiles', 'local_page');
    }

    /**
     * Delete the orphan areas; a run that finds the lock busy leaves them to the next one.
     *
     * @return void
     */
    public function execute(): void {
        $areas = \local_page\local\files::collect_orphans(true);
        if ($areas === null) {
            mtrace('local_page: a page save or a category move holds the lock; orphan files are left to the next run.');
            return;
        }
        $files = array_sum(array_column($areas, 'files'));
        mtrace('local_page: ' . count($areas) . " orphan file area(s) deleted, {$files} file(s).");
    }
}
