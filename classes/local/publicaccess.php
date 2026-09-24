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
 * Whether a category's pages may be served to visitors who are not logged in.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * Whether a category's pages may be served to a visitor, and who counts as one.
 *
 * A thin adapter over {@see \local_unlistedcourses\category_discoverability::is_public()}, the
 * site's one answer to whether a category may be shown to visitors who are not logged in. The
 * answer depends on the category's discoverability state and its path, never on the viewer, and
 * this plugin adds no switch of its own: a category is public here exactly when it is public
 * everywhere else on the site.
 *
 * Only a visitor ({@see is_visitor()}) is ever asked about, by two callers: the request class,
 * before it looks anything up, and local_page_user_can_view_page(), which also decides whether a
 * category page's embedded files are served. What a logged-in user may read is decided by the
 * page's own rules, at the page's own context.
 *
 * Fails closed: when the predicate class is missing (local_unlistedcourses not installed, or a
 * broken deploy) nothing is public and the visitor gets the login page. Neither plugin declares a
 * dependency on the other, so a site without local_unlistedcourses serves category pages to
 * logged-in users only.
 *
 * An id that names no category, a deleted category or the root is answered false, never with an
 * exception: the id comes from a visitor's request, and a refusal that differed between "missing"
 * and "not public" would tell an anonymous client which ids exist.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class publicaccess {
    /** @var string The predicate this adapter delegates to; it may be absent, and then nothing is public. */
    public const PREDICATE = \local_unlistedcourses\category_discoverability::class;

    /**
     * Whether the current viewer is a visitor: nobody logged in, or the guest account.
     *
     * The guest counts, because core treats a guest session as logged in while it carries none of
     * the attributes this question is about — it is the internet with a session cookie. Both callers
     * read the viewer class from here so that the request's refusal and the file route cannot come
     * to disagree about who is refused.
     *
     * @return bool
     */
    public static function is_visitor(): bool {
        return !isloggedin() || isguestuser();
    }

    /**
     * Whether a category's pages may be served to a visitor.
     *
     * @param int $categoryid Course category id, as the request or the stored row names it
     * @param string|null $predicate Predicate class; tests pass a double, or a class that does not exist
     * @return bool True only when the predicate exists and says the category is public
     */
    public static function is_public(int $categoryid, ?string $predicate = null): bool {
        // A page never belongs to the root, so there is no public category 0 to ask about.
        if ($categoryid <= 0) {
            return false;
        }

        $predicate = $predicate ?? self::PREDICATE;
        if (!class_exists($predicate)) {
            // Fail closed: no predicate, no public category.
            return false;
        }

        return (bool) $predicate::is_public($categoryid);
    }
}
