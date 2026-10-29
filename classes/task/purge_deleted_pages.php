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
 * Scheduled task: delete pages permanently once their time in the recycle bin is over.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\task;

/**
 * Scheduled task: delete pages permanently once their time in the recycle bin is over.
 *
 * All the rules are {@see \local_page\local\bin::purge_expired()}'s; the task only supplies the time.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_deleted_pages extends \core\task\scheduled_task {
    /**
     * The task's name, as the scheduled tasks screen shows it.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskpurgedeletedpages', 'local_page');
    }

    /**
     * Delete the pages whose time is over.
     *
     * @return void
     */
    public function execute(): void {
        $count = \local_page\local\bin::purge_expired(time());
        mtrace("local_page: {$count} page(s) deleted permanently from the recycle bin.");
    }
}
