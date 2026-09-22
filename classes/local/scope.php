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
 * Context resolution for local_page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * Which context a page belongs to, and which capability governs it there.
 *
 * Until this stage every row lived in the system context and the code said so in nine places, each
 * of them a bare context_system::instance(). A page now carries its own context, and this class is
 * the only thing that reads the column: a site that adds a third context level later changes this
 * file, not the nine call sites.
 *
 * **A stored contextid of 0 means the system context**, and that convention is the point of this
 * class. Context ids are row ids in {context}: they are assigned at install time, they differ
 * between sites, and they are not available when a column default or an XMLDB file is written. So
 * the column cannot default to "the system context id" — it defaults to 0, and 0 is resolved here.
 * Every row that existed before this stage therefore reads as a system page with no migration at
 * all, which is the whole reason the design is additive.
 *
 * The inverse mapping lives in stored_contextid(): system becomes 0 again, a category context
 * stores its own id. Only those two levels are accepted, in both directions; anything else is a
 * programming error rather than a state a site can reach, and is refused with a coding_exception.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class scope {
    /**
     * The context a page lives in.
     *
     * @param object $page Row from {local_page} (stdClass) or {@see \local_page\custompage}
     * @return \core\context System context for a legacy or system row, the stored one otherwise
     * @throws \dml_missing_record_exception When the stored id names no context
     */
    public static function context(object $page): \core\context {
        $contextid = (int) ($page->contextid ?? 0);

        if ($contextid <= 0) {
            return \core\context\system::instance();
        }

        return \core\context::instance_by_id($contextid, MUST_EXIST);
    }

    /**
     * Whether a page belongs to a course category rather than to the site as a whole.
     *
     * Answered through context() rather than from the column, so there is one place that decides
     * what a stored value means.
     *
     * @param object $page Row from {local_page} (stdClass) or {@see \local_page\custompage}
     * @return bool
     */
    public static function is_category(object $page): bool {
        return self::context($page)->contextlevel == CONTEXT_COURSECAT;
    }

    /**
     * The capability that governs authoring a page in a context.
     *
     * Editing a site-wide page and editing a category's page are different powers held by
     * different people, which is why they are different capabilities rather than one checked at
     * two contexts.
     *
     * @param \core\context $context Context the page lives in
     * @return string Capability name
     * @throws \coding_exception When the context is neither the system nor a course category
     */
    public static function capability(\core\context $context): string {
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            return 'local/page:addpages';
        }

        if ($context->contextlevel == CONTEXT_COURSECAT) {
            return 'local/page:managecategorypages';
        }

        throw new \coding_exception('local_page has no capability for context level ' . $context->contextlevel);
    }

    /**
     * The value the contextid column carries for a context.
     *
     * The inverse of context(): the system context stores 0, a category context stores its own id.
     *
     * @param \core\context $context Context the page lives in
     * @return int Value to store in {local_page}.contextid
     * @throws \coding_exception When the context is neither the system nor a course category
     */
    public static function stored_contextid(\core\context $context): int {
        if ($context->contextlevel == CONTEXT_SYSTEM) {
            return 0;
        }

        if ($context->contextlevel == CONTEXT_COURSECAT) {
            return (int) $context->id;
        }

        throw new \coding_exception('local_page cannot store a page in context level ' . $context->contextlevel);
    }

    /**
     * The context of a course category, refusing a category that does not exist.
     *
     * @param int $categoryid Course category id
     * @return \core\context\coursecat
     * @throws \dml_missing_record_exception When the category does not exist
     */
    public static function for_category(int $categoryid): \core\context\coursecat {
        return \core\context\coursecat::instance($categoryid, MUST_EXIST);
    }
}
