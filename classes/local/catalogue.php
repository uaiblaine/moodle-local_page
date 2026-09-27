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
 * The pages of a category a viewer may read, for another plugin to list.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * The pages of a course category the current viewer may read, for a list another plugin draws.
 *
 * The plugin's read API for a caller outside it - the category showcase of the FUNDASEG theme lists a
 * category's pages beside its courses. Its answer is exactly what the viewer would be let through by
 * the page address, and nothing more, so a list can never name a page its link would refuse:
 *
 * 1. A visitor ({@see publicaccess::is_visitor()}) is refused before anything is looked up unless the
 *    category is public, as in {@see request::category()}: no statement, and the same empty answer
 *    for a category that is private, hidden or not there at all.
 * 2. Then the category's context; a category that is not there has no pages.
 * 3. Then one statement: the category's own pages - never another context's, never a subcategory's -
 *    that are not deleted and are live.
 * 4. Then each row inside its publish window, and through local_page_user_can_view_page(), the whole
 *    read-side rule (access level, logged-in only, the public clause).
 *
 * Steps 3 and 4 ask for a LIVE page inside its window even of somebody who may edit pages, whom the
 * page address itself lets preview a draft: a list is what readers see, and a draft there would look
 * published to the one person who could tell. Drafts are the managers' screen's business.
 *
 * Nothing declares a dependency on this class: a caller asks class_exists() and draws nothing when it
 * is absent. Its answer is plain data - the name and the description in the plain spelling, to be
 * escaped once where they are written, and the address {@see links::page()} spells - so a change in
 * how this plugin renders a page never reaches the caller's markup.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class catalogue {
    /**
     * The pages of a category the current viewer may read, ordered by name.
     *
     * @param int $categoryid Course category id
     * @param string|null $predicate Public predicate class; tests pass a double
     * @return array[] Each page as ['id' => int, 'name' => string, 'url' => string, 'description' => string],
     *   the name and the description in the plain spelling (unescaped), the description '' when none
     */
    public static function for_viewer(int $categoryid, ?string $predicate = null): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/page/lib.php');

        // Step 1: the visitor's refusal, before any lookup - every id that is not public answers alike.
        if (publicaccess::is_visitor() && !publicaccess::is_public($categoryid, $predicate)) {
            return [];
        }

        // Step 2: the category's context. The root has none, and a missing category has no pages.
        $context = $categoryid > 0 ? \core\context\coursecat::instance($categoryid, IGNORE_MISSING) : false;
        if (!$context) {
            return [];
        }

        // Step 3: this context's live pages, one statement. The deleted term only narrows: step 4 refuses one too.
        $rows = $DB->get_records('local_page', [
            'contextid' => (int) $context->id,
            'deleted' => 0,
            'status' => 'live',
        ], 'id ASC');

        // Step 4: the window, then the read-side rule, row by row.
        $now = time();
        $pages = [];
        foreach ($rows as $row) {
            if (!local_page_publish_window_is_open($row, $now) || !local_page_user_can_view_page($row, $predicate)) {
                continue;
            }
            $pages[] = [
                'id' => (int) $row->id,
                'name' => self::plain((string) $row->pagename, $context),
                'url' => links::page($row)->out(false),
                'description' => self::plain((string) ($row->metadescription ?? ''), $context),
            ];
        }

        $names = array_column($pages, 'name');
        \core_collator::asort($names);
        return array_values(array_map(fn($key) => $pages[$key], array_keys($names)));
    }

    /**
     * A stored text in the plain spelling: filtered and stripped of tags, never escaped.
     *
     * The same rule the page's own head tags use ({@see \local_page\output\opengraph}): the fields
     * keep multilang spans, so they go through format_string() like a heading would, with escaping
     * left to whoever writes the value.
     *
     * @param string $text The stored text
     * @param \core\context $context The context to format in
     * @return string The plain spelling, trimmed
     */
    private static function plain(string $text, \core\context $context): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return trim(format_string($text, true, ['context' => $context, 'escape' => false]));
    }
}
