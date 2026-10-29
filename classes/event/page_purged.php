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
 * Event: a page deleted permanently.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\event;

/**
 * Event: a page deleted permanently.
 *
 * Triggered by {@see \local_page\local\bin}; objectid is the page id, in the page's context.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_purged extends \core\event\base {
    /**
     * Initialise the event.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud'] = 'd';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_page';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_page_purged', 'local_page');
    }

    /**
     * Non-localised description for the log.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' permanently deleted the custom page with id '{$this->objectid}'.";
    }

    /**
     * Pages are not part of a course backup, so the page id maps to nothing on restore.
     *
     * @return array
     */
    public static function get_objectid_mapping() {
        return ['db' => 'local_page', 'restore' => self::NOT_MAPPED];
    }

    /**
     * The event carries nothing in 'other'.
     *
     * @return bool
     */
    public static function get_other_mapping() {
        return false;
    }
}
