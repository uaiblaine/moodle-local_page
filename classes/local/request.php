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
 * Every address it sets on $PAGE, stores in wantsurl or redirects to is spelled by {@see links}.
 * While the site's router is configured, the legacy script is a doorway to the route: its slug form
 * ({@see category()} with $legacy) answers a 303 to the routed address BEFORE anything else — no
 * lookup and no visitor refusal, so every id and every slug gets the same answer, and the guard
 * order above runs where the redirect lands. Upstream's ?id= address ({@see legacy()}) can only
 * redirect AFTER the page's own rules and the public predicate have answered, because the redirect
 * spells the page's slug, and handing a slug to a visitor the page is withheld from would tell them
 * what the id names.
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
    /** @var int The status of an answer that renders a page. */
    public const STATUS_OK = 200;

    /** @var int The status of the visitor's login refusal: what core's route redirect and require_login() send. */
    public const STATUS_LOGIN = 302;

    /** @var int The status of "read this at its canonical address": the legacy script while the router is configured. */
    public const STATUS_SEE_OTHER = 303;

    /**
     * Constructor: either a redirect, or a page and whether the viewer may read it.
     *
     * @param \moodle_url|null $redirect Where to send the client, when the answer is a redirect
     * @param custompage|null $page The page to render, or the "no access" placeholder
     * @param bool $canview Whether the viewer may read $page; false for the placeholder
     * @param int $status The HTTP status the answer calls for: one of the STATUS_ constants
     * @param \moodle_url|null $canonical The page's canonical address, when there is a page to name
     */
    private function __construct(
        /** @var \moodle_url|null Where to send the client, when the answer is a redirect. */
        public readonly ?\moodle_url $redirect,
        /** @var custompage|null The page to render, or the "no access" placeholder; null for a redirect. */
        public readonly ?custompage $page,
        /** @var bool Whether the viewer may read the page; false for the placeholder and for a redirect. */
        public readonly bool $canview,
        /** @var int The HTTP status the answer calls for; the route controller sends it, index.php cannot. */
        public readonly int $status,
        /** @var \moodle_url|null The page's canonical address for the head tags; null for a redirect or no page. */
        public readonly ?\moodle_url $canonical = null,
    ) {
    }

    /**
     * A request for a category's page: the route /local_page/category/N/slug, or the script's
     * /local/page/index.php?category=N&page=slug, or its &id=M form.
     *
     * @param int $categoryid Course category id from the request
     * @param int $pageid Page id from the request, used when no slug was given
     * @param string $slug Friendly URL of the page within the category, or '' for the id form
     * @param string|null $predicate Public predicate class; tests pass a double
     * @param bool $legacy Whether the request arrived on the legacy script rather than on the route
     * @return self A redirect target, or a page to render
     */
    public static function category(
        int $categoryid,
        int $pageid,
        string $slug,
        ?string $predicate = null,
        bool $legacy = false
    ): self {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/lib.php');

        /*
         * Step 0, the legacy script's slug form while the router is configured: the route is this
         * address's canonical spelling, so the script answers with a 303 to it before anything else.
         * No lookup and no visitor refusal here — every id and every slug gets the same redirect, so
         * the redirect tells nobody anything, and the guard order below runs where it lands. The id
         * form has no route and carries on.
         */
        if ($legacy && $slug !== '' && links::routing_enabled()) {
            return self::moved(links::category_page($categoryid, $slug));
        }

        $url = $slug !== '' ? links::category_page($categoryid, $slug) : links::legacy_category_id($categoryid, $pageid);
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
        return self::answer($page, $canview, $url, links::page($page));
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
     * order of {@see category()} does not hold.
     *
     * While the router is configured, a category page this viewer MAY read is answered with a 303 to
     * its routed address instead of being rendered here. The redirect comes after the rules and the
     * predicate on purpose: it spells the page's category and slug, so issuing it to a viewer the
     * page is withheld from would tell them what the id names. They get the refusal, or the "no
     * access" page, exactly as without the router.
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
            $url = links::legacy_menuname($menuname);
        } else {
            $page = custompage::load($pageid);
            $url = links::legacy($pageid);
        }

        /*
         * The page's own rules, which for a category page include the public predicate: a visitor is
         * refused there unless the category is public, post-lookup, since the id is all this address
         * carries. A category page withheld from a visitor — by the predicate or by its own rules —
         * meets the same refusal as at its category address; a site-wide page keeps upstream's
         * "no access" render for everybody.
         */
        $canview = local_page_user_can_view_page($page, $predicate);
        $iscategory = (int) $page->id > 0 && scope::is_category($page);
        if ($iscategory && publicaccess::is_visitor() && !$canview) {
            return self::refuse($url);
        }

        // Only now, with the rules and the predicate answered: a readable category page is read at its route.
        if ($iscategory && $canview && links::routing_enabled()) {
            return self::moved(links::page($page));
        }

        /*
         * The canonical of a site-wide page is upstream's, byte for byte: wwwroot/slug when the
         * request came through the friendly URL, the ?id= address otherwise. A category page's is
         * links::page(), which for a page READ here is the script's ?category=&page= form — with the
         * router on, a readable one was redirected above.
         */
        $canonical = null;
        if ($iscategory) {
            $canonical = links::page($page);
        } else if ((int) $page->id > 0) {
            $canonical = $menuname !== '' ? links::legacy_menuname((string) $page->menuname) : links::legacy((int) $page->id);
        }

        return self::answer($page, $canview, $url, $canonical);
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

        return new self(new \moodle_url(get_login_url()), null, false, self::STATUS_LOGIN);
    }

    /**
     * The answer "read this at its canonical address": a 303, which a browser does not cache.
     *
     * Not a 301: a browser keeps a permanent redirect, and a router switched off later would strand
     * every cached visitor on a route nothing answers any more.
     *
     * @param \moodle_url $url The canonical address
     * @return self The answer
     */
    private static function moved(\moodle_url $url): self {
        return new self($url, null, false, self::STATUS_SEE_OTHER);
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
     * @param \moodle_url|null $canonical The page's canonical address, for the head tags
     * @return self The answer
     */
    private static function answer(custompage $page, bool $canview, \moodle_url $url, ?\moodle_url $canonical = null): self {
        global $PAGE;

        $context = scope::context($page);
        if ($context->contextlevel == CONTEXT_COURSECAT) {
            // First: set_category_by_id() sets the course and the context too, and throws once either is set.
            $PAGE->set_category_by_id((int) $context->instanceid);
        } else {
            $PAGE->set_context($context);
        }
        $PAGE->set_url($url);

        return new self(null, $page, $canview, self::STATUS_OK, $canonical);
    }
}
