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
 * The recycle bin of one context, for bin.php.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\output;

use local_page\local\bin;
use local_page\local\slug;

/**
 * The recycle bin of one context, for bin.php.
 *
 * Restoring posts a form with the session key; deleting permanently and emptying link to bin.php's
 * confirmation step, which posts its own. Every address carries the context, because bin.php
 * resolves the context, and checks the capability in it, from that parameter.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bin_list implements \renderable, \templatable {
    /** @var \core\context Context whose bin is shown. */
    protected \core\context $context;

    /**
     * Constructor.
     *
     * @param \core\context $context Context whose bin is shown
     */
    public function __construct(\core\context $context) {
        $this->context = $context;
    }

    /**
     * Export data for the template.
     *
     * Names and slugs are handed over in their plain spelling: the template escapes them once.
     *
     * @param \renderer_base $output Renderer
     * @return array
     */
    public function export_for_template(\renderer_base $output): array {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/lib.php');

        $items = [];
        foreach (bin::items($this->context) as $row) {
            $pageid = (int) $row->id;
            $purgetime = bin::purge_time((int) $row->timedeleted);
            $items[] = [
                'id' => $pageid,
                'name' => format_string($row->pagename, true, ['context' => $this->context, 'escape' => false]),
                'menuname' => slug::restored_name((string) $row->menuname, $pageid),
                'timedeleted' => (int) $row->timedeleted > 0 ? userdate((int) $row->timedeleted) : '',
                'purgetime' => $purgetime === null ? '' : userdate($purgetime),
                'restoreurl' => local_page_bin_url($this->context)->out(false),
                'purgeurl' => local_page_bin_url($this->context, ['action' => 'purge', 'pageid' => $pageid])->out(false),
            ];
        }

        $expiry = bin::expiry();

        return [
            'items' => $items,
            'hasitems' => $items !== [],
            'sesskey' => sesskey(),
            'retention' => $expiry > 0 ? format_time($expiry) : '',
            'emptyurl' => local_page_bin_url($this->context, ['action' => 'empty'])->out(false),
            'backurl' => local_page_list_url($this->context)->out(false),
        ];
    }
}
