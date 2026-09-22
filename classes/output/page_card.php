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
 * Output class for page card template
 *
 * @package     local_page
 * @author      Marcin Czaja RoseaThemes
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\output;

use renderable;
use renderer_base;
use templatable;
use stdClass;
use moodle_url;

/**
 * Class representing data for page card template
 *
 * @copyright   2025 Marcin Czaja RoseaThemes
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page_card implements renderable, templatable {
    /** @var int Page ID */
    protected $id;

    /** @var string Page name */
    protected $name;

    /** @var string Page status */
    protected $status;

    /** @var int Page start date */
    protected $pagedate;

    /** @var int Page end date */
    protected $enddate;

    /** @var string Menu name */
    protected $menuname;

    /**
     * Constructor
     *
     * @param int $id Page ID
     * @param string $name Page name
     * @param string $status Page status
     * @param int $pagedate Page start date
     * @param int $enddate Page end date
     * @param string|null $menuname Menu name
     */
    public function __construct($id, $name, $status, $pagedate, $enddate, $menuname = null) {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
        $this->pagedate = $pagedate;
        $this->enddate = $enddate;
        $this->menuname = $menuname;
    }
    /**
     * Export data for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;

        $data = new stdClass();
        $data->id = $this->id;
        $shortname = shorten_text($this->name, 100);
        $data->name = $shortname;
        $data->status = $this->status;

        // Generate card body class based on status.
        $data->cardbodyclass = 'custompages-card-body rounded mb-3';
        if ($this->status === 'draft') {
            $data->cardbodyclass .= ' custompages-card-body--draft';
        } else if ($this->status === 'archived') {
            $data->cardbodyclass .= ' custompages-card-body--archived';
        }

        /*
         * Generate status badge. The string id is a literal per arm, never
         * get_string('status_' . $status): a dynamic id is invisible to the lang
         * tooling, so an unknown status would reach get_string() and raise a
         * developer notice instead of simply rendering no badge.
         *
         * Every bg-* utility is paired with a text utility. Bootstrap 5 defaults
         * badge text to white, which is unreadable on bg-warning (contrast 1.95
         * against the 4.5:1 AA floor), so the pairing is not optional.
         */
        $badgeclasses = [
            'live' => 'badge bg-success text-white',
            'draft' => 'badge bg-warning text-dark',
            'archived' => 'badge bg-danger text-white',
        ];
        $statusstring = match ($this->status) {
            'live' => get_string('status_live', 'local_page'),
            'draft' => get_string('status_draft', 'local_page'),
            'archived' => get_string('status_archived', 'local_page'),
            default => '',
        };
        if ($statusstring !== '' && isset($badgeclasses[$this->status])) {
            $data->statusbadge = \html_writer::tag('span', $statusstring, ['class' => $badgeclasses[$this->status]]);
        } else {
            $data->statusbadge = '';
        }

        // Check if page has restrictions.
        $data->restricted = false;
        if ($this->pagedate > 0 && $this->status === 'live' && $this->pagedate > time()) {
            $data->restricted = true;
        } else if ($this->pagedate > 0 && $this->status === 'draft' && $this->pagedate <= time()) {
            $data->restricted = true;
        } else if ($this->enddate > 0 && $this->enddate < time()) {
            $data->restricted = true;
        }

        // Generate URLs.
        $data->editurl = new moodle_url($CFG->wwwroot . '/local/page/edit.php', ['id' => $this->id]);
        $data->pageurl = $CFG->wwwroot . '/local/page/?id=' . $this->id;
        $data->viewurl = new moodle_url($CFG->wwwroot . '/local/page/', ['id' => $this->id]);
        $data->deleteurl = new moodle_url(
            '/local/page/pages.php',
            ['pagedel' => $this->id, 'sesskey' => \sesskey()]
        );

        // Add friendly URL if menuname exists.
        if ($this->menuname) {
            $data->menuname = $this->menuname;
            $data->friendlyurl = $CFG->wwwroot . '/' . $this->menuname;
        }

        return $data;
    }
}
