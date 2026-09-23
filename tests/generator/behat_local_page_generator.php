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
 * Behat data generator for local_page.
 *
 * @package    local_page
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Lets a feature create custom pages, site-wide or inside a course category.
 *
 * Usage: the following "local_page > pages" exist, with columns pagename, menuname and pagecontent,
 * and optionally category (a course category's idnumber), status and onlyloggedin. Every other column
 * takes the default of local_page_generator::create_page(): a live page that visitors may read.
 *
 * @package    local_page
 * @category   test
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_page_generator extends behat_generator_base {
    /**
     * The entities this generator creates.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'pages' => [
                'singular' => 'page',
                'datagenerator' => 'page',
                'required' => ['pagename'],
                'switchids' => ['category' => 'categoryid'],
            ],
        ];
    }

    /**
     * Creates one page, in the category the row names or in the system context.
     *
     * A category page needs both halves of the context dimension — its category's CONTEXT id and the
     * category id itself — which is what create_category_page() writes, so a row naming a category is
     * sent there rather than to create_page() with only half of it.
     *
     * @param array $data The row, with the category idnumber already switched to categoryid
     * @return void
     */
    protected function process_page(array $data): void {
        $categoryid = (int) ($data['categoryid'] ?? 0);
        unset($data['categoryid']);

        if ($categoryid > 0) {
            $this->componentdatagenerator->create_category_page($categoryid, $data);
        } else {
            $this->componentdatagenerator->create_page($data);
        }
    }
}
