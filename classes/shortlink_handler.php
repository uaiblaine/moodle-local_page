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
 * Resolves this plugin's public short codes for core's /p/{shortcode} route.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use local_page\local\links;

/**
 * Resolves this plugin's public short codes for core's /p/{shortcode} route.
 *
 * Core finds this class by name — \core\shortlink::get_shortlink_handler() asks the dependency
 * injection container for "<component>\shortlink_handler" — so nothing registers it. It answers the
 * one link type this plugin mints, with the page's canonical address: a row holds a page id, never
 * an address, so a code minted while the site had no router opens the routed page the day the
 * router is configured.
 *
 * It makes no access decision. The redirect lands on the page, and the page applies its own rules
 * there — for a visitor, the one refusal a category page gives. What it does refuse is a code whose
 * page is gone: deleted, or no longer in a context that exists. Null is core's "not found".
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortlink_handler implements \core\shortlink_handler_interface {
    #[\Override]
    public function get_valid_linktypes(): array {
        return [links::LINKTYPE];
    }

    #[\Override]
    public function process_shortlink(string $type, string $identifier): ?\core\url {
        global $DB;

        if ($type !== links::LINKTYPE) {
            return null;
        }
        if (!ctype_digit($identifier) || (int) $identifier <= 0) {
            return null;
        }

        $row = $DB->get_record('local_page', ['id' => (int) $identifier, 'deleted' => 0]);
        if (!$row) {
            return null;
        }

        try {
            return links::page($row);
        } catch (\dml_missing_record_exception $exception) {
            // The row names a context that no longer exists: its category was deleted under it.
            return null;
        }
    }
}
