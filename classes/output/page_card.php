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

    /** @var \core\context|null Context the page belongs to; null means the system context. */
    protected $context;

    /**
     * Constructor
     *
     * @param int $id Page ID
     * @param string $name Page name
     * @param string $status Page status
     * @param int $pagedate Page start date
     * @param int $enddate Page end date
     * @param string|null $menuname Menu name
     * @param \core\context|null $context Context the page belongs to; the system context by default
     */
    public function __construct($id, $name, $status, $pagedate, $enddate, $menuname = null, ?\core\context $context = null) {
        $this->id = $id;
        $this->name = $name;
        $this->status = $status;
        $this->pagedate = $pagedate;
        $this->enddate = $enddate;
        $this->menuname = $menuname;
        $this->context = $context;
    }
    /**
     * Export data for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $CFG;

        $context = $this->context ?? \core\context\system::instance();
        $iscategory = $context->contextlevel == CONTEXT_COURSECAT;

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
        $deleteparams = ['pagedel' => $this->id, 'sesskey' => \sesskey()];
        if ($iscategory) {
            /*
             * The delete action resolves its context from this parameter, checks the capability
             * there and then refuses a row that does not belong to it. Without the parameter the
             * link asks the site-wide screen to delete a category's page, and that is refused
             * twice over — by the capability for a category manager, and by the row comparison
             * even for an administrator.
             */
            $deleteparams['contextid'] = (int) $context->id;
        }
        $data->deleteurl = new moodle_url('/local/page/pages.php', $deleteparams);

        /*
         * Add friendly URL if menuname exists. A category page never gets one: wwwroot/<slug> is
         * the site-wide convention, answered by the web server rewrite for site pages only, and a
         * category page's address is a routed one that arrives in a later stage. Printing the
         * site-wide form here would advertise an address that serves somebody else's page.
         */
        if ($this->menuname && !$iscategory) {
            $data->menuname = $this->menuname;
            $data->friendlyurl = $CFG->wwwroot . '/' . $this->menuname;
        }

        return $data;
    }
}
