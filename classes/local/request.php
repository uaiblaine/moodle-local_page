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
 * One request for a custom page, decided in one order for every address.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use local_page\custompage;

/**
 * One request for a custom page, decided in one order for every address the viewer answers on.
 *
 * index.php used to load the row, set up $PAGE and ask the access predicate itself, which was fine
 * while every page was the site's. A category page adds a question that has to be asked BEFORE the
 * lookup, and a script is a place no test reaches, so the decision lives here and the script only
 * translates the answer: a redirect target, or a page to render together with whether the viewer
 * may read it.
 *
 * The order of {@see category()} is a security property and moves as one block:
 *
 * 1. A VISITOR ({@see publicaccess::is_visitor()}: nobody logged in, or the guest account) is
 *    refused before anything is looked up unless the category is public. One refusal — the login
 *    page, with wantsurl pointing back here — for a category that is listed, unlisted, hidden or
 *    not there at all, so an anonymous client cannot tell which category ids exist. The refusal
 *    costs no database statement.
 * 2. Then the category's context. A category that is not there is "no page".
 * 3. Then the lookup, scoped to that context: by slug, or by id with the row's own context compared
 *    against the address. A page that is not this address's page is "not found", which a visitor
 *    meets as the same login redirect and a logged-in user as the "no access" page.
 * 4. Then the page's own rules — status, publish window, onlyloggedin, accesslevel — through
 *    local_page_user_can_view_page(), at the context read from the row. A visitor those rules
 *    withhold the page from meets the same login refusal as for a page that is not there: one
 *    refusal for visitors, so a public category's withheld slugs cannot be told from its missing
 *    ones, and for a page kept for logged-in readers the login page is the way in.
 * 5. Then $PAGE: set_category_by_id() first for a category page, because it sets the course and the
 *    context as well and throws once either is set; set_context() otherwise; then the URL.
 *
 * No require_login() for the page itself, anywhere. This plugin serves pages to visitors who are not
 * logged in, by design, on a site that keeps $CFG->forcelogin on: forcelogin is not an ambient gate
 * but a set of explicit reads in core, none of which this class makes. index.php keeps upstream's
 * require_login() for a page with an access level, and it runs after this class has answered — so
 * a visitor refused here meets this class's refusal, never that one.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class request {
    /**
     * Constructor: either a redirect, or a page and whether the viewer may read it.
     *
     * @param \moodle_url|null $redirect Where to send the client, when the answer is a redirect
     * @param custompage|null $page The page to render, or the "no access" placeholder
     * @param bool $canview Whether the viewer may read $page; false for the placeholder
     */
    private function __construct(
        /** @var \moodle_url|null Where to send the client, when the answer is a redirect. */
        public readonly ?\moodle_url $redirect,
        /** @var custompage|null The page to render, or the "no access" placeholder; null for a redirect. */
        public readonly ?custompage $page,
        /** @var bool Whether the viewer may read the page; false for the placeholder and for a redirect. */
        public readonly bool $canview,
    ) {
    }

    /**
     * A request for a category's page: /local/page/index.php?category=N&page=slug, or &id=M.
     *
     * @param int $categoryid Course category id from the request
     * @param int $pageid Page id from the request, used when no slug was given
     * @param string $slug Friendly URL of the page within the category, or '' for the id form
     * @param string|null $predicate Public predicate class; tests pass a double
     * @return self A redirect target, or a page to render
     */
    public static function category(int $categoryid, int $pageid, string $slug, ?string $predicate = null): self {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/lib.php');

        $params = $slug !== '' ? ['category' => $categoryid, 'page' => $slug] : ['category' => $categoryid, 'id' => $pageid];
        $url = new \moodle_url('/local/page/index.php', $params);
        $isvisitor = publicaccess::is_visitor();

        // Step 1: the visitor's one refusal, before any lookup — every id that is not public answers alike.
        if ($isvisitor && !publicaccess::is_public($categoryid, $predicate)) {
            return self::refuse($url);
        }

        // Step 2: the category's context. A category that is not there has no page.
        $context = $categoryid > 0 ? \core\context\coursecat::instance($categoryid, IGNORE_MISSING) : false;

        // Step 3: the lookup, scoped to that context.
        $page = null;
        if ($context) {
            if ($slug !== '') {
                $page = custompage::load_by_menuname($slug, false, (int) $context->id);
            } else {
                $page = custompage::load($pageid);
                // A page of another context is not this address's page, whatever its id.
                if ((int) $page->contextid !== (int) $context->id) {
                    $page = null;
                }
            }
        }

        if ($page === null || (int) $page->id <= 0) {
            if ($isvisitor) {
                return self::refuse($url);
            }
            return self::answer(custompage::load(0), false, $url);
        }

        // Step 4: the page's own rules, at the context read from the row.
        $canview = local_page_user_can_view_page($page, $predicate);

        /*
         * A visitor those rules withhold the page from — a draft, a page for logged-in readers, one
         * outside its publish window — meets the refusal a missing page gets, not the "no access"
         * page: otherwise, inside a public category, a 200 against a redirect would tell an
         * anonymous client which slugs exist; and for a page kept for logged-in readers the login
         * page, with wantsurl, is the answer that brings them to it.
         */
        if ($isvisitor && !$canview) {
            return self::refuse($url);
        }

        // Step 5: $PAGE.
        return self::answer($page, $canview, $url);
    }

    /**
     * A request on one of upstream's addresses: /local/page/index.php?id=N, or ?menuname=slug.
     *
     * The menuname address is the SYSTEM scope, exactly as it always was; a category's friendly URL
     * is answered by {@see category()} alone.
     *
     * A category page can still be reached here through its id, and for a visitor the public
     * predicate is then applied AFTER the lookup — through local_page_user_can_view_page(), the rule
     * the file route shares — because the id is all this address carries and the category is only
     * known once the row is read. That is unavoidable for an id address and is the one place the
     * order of {@see category()} does not hold; stage 6 answers a category page's id address with a
     * redirect to its category address, so the pre-lookup refusal is the one visitors actually meet.
     *
     * @param int $pageid Page id from the request
     * @param string $menuname Site-wide friendly URL from the request, or ''
     * @param string|null $predicate Public predicate class; tests pass a double
     * @return self A redirect target, or a page to render
     */
    public static function legacy(int $pageid, string $menuname, ?string $predicate = null): self {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/lib.php');

        if ($menuname !== '') {
            $page = custompage::load_by_menuname($menuname);
            $url = new \moodle_url('/' . $menuname);
        } else {
            $page = custompage::load($pageid);
            $url = new \moodle_url('/local/page/index.php', ['id' => $pageid]);
        }

        /*
         * The page's own rules, which for a category page include the public predicate: a visitor is
         * refused there unless the category is public, post-lookup, since the id is all this address
         * carries. A category page withheld from a visitor — by the predicate or by its own rules —
         * meets the same refusal as at its category address; a site-wide page keeps upstream's
         * "no access" render for everybody.
         */
        $canview = local_page_user_can_view_page($page, $predicate);
        if ((int) $page->id > 0 && scope::is_category($page) && publicaccess::is_visitor() && !$canview) {
            return self::refuse($url);
        }

        return self::answer($page, $canview, $url);
    }

    /**
     * The visitor's refusal: the login page, with wantsurl pointing back at the address asked for.
     *
     * @param \moodle_url $url The address the visitor asked for
     * @return self The answer
     */
    private static function refuse(\moodle_url $url): self {
        global $SESSION;

        $SESSION->wantsurl = $url->out(false);

        return new self(new \moodle_url(get_login_url()), null, false);
    }

    /**
     * The answer "render this", with $PAGE set up for it.
     *
     * The context comes from the row, so the "no access" placeholder — which has no row — renders
     * in the system context and says nothing about the category the address named.
     *
     * @param custompage $page The page, or the "no access" placeholder
     * @param bool $canview Whether the viewer may read it
     * @param \moodle_url $url The address the page is rendered at
     * @return self The answer
     */
    private static function answer(custompage $page, bool $canview, \moodle_url $url): self {
        global $PAGE;

        $context = scope::context($page);
        if ($context->contextlevel == CONTEXT_COURSECAT) {
            // First: set_category_by_id() sets the course and the context too, and throws once either is set.
            $PAGE->set_category_by_id((int) $context->instanceid);
        } else {
            $PAGE->set_context($context);
        }
        $PAGE->set_url($url);

        return new self(null, $page, $canview);
    }
}
