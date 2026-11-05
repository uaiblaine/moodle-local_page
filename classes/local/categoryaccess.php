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
 * Whether a logged-in user may reach a category's pages.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * Whether the current logged-in user may reach a category's pages: where the site would name the category to them.
 *
 * The logged-in counterpart of {@see publicaccess}, which answers for visitors only. A category's
 * pages reach a logged-in user where the rest of the site would show them the category, and nowhere
 * else: core's visibility (a hidden category is refused unless the user holds
 * moodle/category:viewhiddencategories there, which is what core_course_category::get() asks), and,
 * when local_unlistedcourses is installed, its discoverability of an unlisted category (a cohort, a
 * role or the hidden-categories capability on every unlisted category of the path). An unlisted
 * category keeps visible = 1, so core alone would hand it to every logged-in user.
 *
 * Whoever may author the category's pages reaches them whatever the category's state: the pages
 * screen lets them manage those pages anyway, and the read rule's editor preview is for them.
 *
 * Fails OPEN on the second half only, the opposite of {@see publicaccess}: without
 * local_unlistedcourses no category is unlisted, so there is nothing to withhold beyond core's own
 * answer. Core's answer is always asked.
 *
 * Not for visitors: their question is {@see publicaccess::is_public()}, whose answer already requires
 * a visible path with no unlisted category on it. A missing category, a deleted one or the root is
 * answered false, never with an exception, so the caller can refuse it like a hidden one.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class categoryaccess {
    /** @var string The discoverability predicate; it may be absent, and then no category is unlisted. */
    public const PREDICATE = \local_unlistedcourses\category_access::class;

    /**
     * Whether the current user may reach the pages of this category.
     *
     * @param int $categoryid Course category id, as the request or the stored row names it
     * @param string|null $predicate Discoverability class; tests pass a double, or a class that does not exist
     * @return bool
     */
    public static function is_reachable(int $categoryid, ?string $predicate = null): bool {
        if ($categoryid <= 0) {
            return false;
        }

        $context = \core\context\coursecat::instance($categoryid, IGNORE_MISSING);
        if (!$context) {
            return false;
        }

        if (has_capability(scope::capability($context), $context)) {
            return true;
        }

        // Null for a category the user may not see: hidden without moodle/category:viewhiddencategories there.
        if (\core_course_category::get($categoryid, IGNORE_MISSING) === null) {
            return false;
        }

        $predicate = $predicate ?? self::PREDICATE;
        if (!class_exists($predicate)) {
            return true;
        }

        return (bool) $predicate::is_category_discoverable($categoryid);
    }
}
