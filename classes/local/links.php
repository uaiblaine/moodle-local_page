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
 * The addresses of a custom page, spelled in one place.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * The one place a custom page's addresses are spelled.
 *
 * A category page has three addresses:
 *
 * - The script, /local/page/index.php?category=N&page=slug (or &id=M). It always answers, router or
 *   no router, and it is what every address falls back to.
 * - The route, /local_page/category/N/slug, answered by {@see \local_page\route\controller\page}.
 *   It is the page's canonical address while the site's router is configured — what $PAGE, the
 *   visitor's wantsurl, the canonical tag and og:url carry — and the script answers its slug form
 *   with a 303 to it. The frankenstyle prefix is core's: a plugin's page route always carries it.
 * - The public short address, /p/code, resolved by core's own route into {@see page()} through
 *   {@see \local_page\shortlink_handler}. It is minted when the page is saved, never on a GET, and
 *   only while the router is configured: without it core would spell the code /r.php/p/code,
 *   which is longer than the script's own address, so {@see share()} answers page() and mints
 *   nothing.
 *
 * A site-wide page is addressed as /local/page/index.php?id=N or ?menuname=slug. {@see page()} never
 * spells the wwwroot/slug form for it, because that address exists only where the web server
 * rewrites it; {@see legacy_menuname()} is used only for a request that arrived with ?menuname=,
 * which is what that rewrite produces.
 *
 * The router gate is $CFG->routerconfigured, the flag core's own URL builders read
 * (\core\url::routed_path()): the administrator's word that the web server hands unknown paths to
 * r.php. Nothing here sets $CFG->urlrewriteclass: that is one global slot with no chaining, and a
 * plugin that claimed it would evict whatever the site put there.
 *
 * Codes are drawn by core's \core\shortlink::create_public_shortlink(), which writes a public row
 * (userid 0) and keeps a public code unique across the whole table, but cannot guarantee one code per
 * page; {@see share()} reuses an existing code and {@see mint()} reads the oldest row back.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class links {
    /** @var string The component the shortlink rows are written under, and whose handler core asks. */
    public const COMPONENT = 'local_page';

    /** @var string The link type this plugin mints, as stored in the shortlink table and answered by the handler. */
    public const LINKTYPE = 'page';

    /**
     * Whether the site's router is configured, so that routed addresses are short ones.
     *
     * @return bool True when $CFG->routerconfigured is set
     */
    public static function routing_enabled(): bool {
        global $CFG;

        return !empty($CFG->routerconfigured);
    }

    /**
     * The script's own address for a category's page, by its slug.
     *
     * @param int $categoryid Course category id
     * @param string $slug The page's friendly URL within the category
     * @return \moodle_url
     */
    public static function legacy_category(int $categoryid, string $slug): \moodle_url {
        return new \moodle_url('/local/page/index.php', ['category' => $categoryid, 'page' => $slug]);
    }

    /**
     * The script's address for a category's page, by its id. No route exists for this form.
     *
     * @param int $categoryid Course category id
     * @param int $pageid Page id
     * @return \moodle_url
     */
    public static function legacy_category_id(int $categoryid, int $pageid): \moodle_url {
        return new \moodle_url('/local/page/index.php', ['category' => $categoryid, 'id' => $pageid]);
    }

    /**
     * The script's address of a page, by id; what {@see page()} answers for a site-wide page.
     *
     * @param int $pageid Page id
     * @return \moodle_url
     */
    public static function legacy(int $pageid): \moodle_url {
        return new \moodle_url('/local/page/index.php', ['id' => $pageid]);
    }

    /**
     * The canonical of a site-wide page requested by its friendly URL: wwwroot/slug.
     *
     * Only emitted for a request that named ?menuname=, which is what the web server rewrite produces;
     * without that rewrite the address answers nothing.
     *
     * @param string $menuname The page's site-wide friendly URL
     * @return \moodle_url
     */
    public static function legacy_menuname(string $menuname): \moodle_url {
        return new \moodle_url('/' . $menuname);
    }

    /**
     * The canonical address of a category's page: the route while the router is configured, the script otherwise.
     *
     * Never the /r.php/ spelling, which answers but is longer than the script's own address.
     *
     * @param int $categoryid Course category id
     * @param string $slug The page's friendly URL within the category
     * @return \moodle_url
     */
    public static function category_page(int $categoryid, string $slug): \moodle_url {
        if (!self::routing_enabled()) {
            return self::legacy_category($categoryid, $slug);
        }

        return \core\router\util::get_path_for_callable(
            [\local_page\route\controller\page::class, 'view'],
            ['category' => $categoryid, 'slug' => $slug]
        );
    }

    /**
     * The canonical address of any stored page.
     *
     * A category page is addressed by its category and its slug; one without a usable slug — which
     * cannot be stored since every save names the page, but a row edited by hand could hold one —
     * by its category and its id, which the script answers. A site-wide page is addressed by ?id=.
     *
     * @param object $row Row from {local_page}, or a {@see \local_page\custompage}
     * @return \moodle_url
     * @throws \dml_missing_record_exception When the row's stored context no longer exists
     */
    public static function page(object $row): \moodle_url {
        $pageid = (int) ($row->id ?? 0);
        $context = scope::context($row);
        if ($context->contextlevel != CONTEXT_COURSECAT) {
            return self::legacy($pageid);
        }

        $categoryid = (int) $context->instanceid;
        $slug = (string) ($row->menuname ?? '');
        // The route's parameter is ALPHANUMEXT; a slug it would refuse gets the address that answers.
        if ($slug === '' || clean_param($slug, PARAM_ALPHANUMEXT) !== $slug) {
            return self::legacy_category_id($categoryid, $pageid);
        }

        return self::category_page($categoryid, $slug);
    }

    /**
     * The public short address of a category page: the one to share.
     *
     * The page's code, minted on the first call; the page's own address while the router is not
     * configured, and for a site-wide page always, with nothing minted: site-wide pages have their own
     * friendly URL.
     *
     * Called from the save path only. A GET never mints: the listing reads {@see existing_share()}.
     *
     * @param object $row Row from {local_page}, or a {@see \local_page\custompage}
     * @return \moodle_url
     */
    public static function share(object $row): \moodle_url {
        $pageid = (int) ($row->id ?? 0);
        if ($pageid <= 0 || !self::routing_enabled() || !scope::is_category($row)) {
            return self::page($row);
        }

        $code = self::existing_code($pageid) ?? self::mint($pageid);

        return self::public_url($code);
    }

    /**
     * The public short address of a page if one was minted, without minting one.
     *
     * Null while the router is not configured, even for a page that has a code: core would spell it
     * /r.php/p/code, which is not an address worth handing anybody.
     *
     * @param int $pageid Page id
     * @return \moodle_url|null
     */
    public static function existing_share(int $pageid): ?\moodle_url {
        if (!self::routing_enabled()) {
            return null;
        }

        $code = self::existing_code($pageid);

        return $code === null ? null : self::public_url($code);
    }

    /**
     * The page's public short code, when one has been minted.
     *
     * The oldest row wins, so that two rows left by a race still spell one address.
     *
     * @param int $pageid Page id
     * @return string|null The code, or null when none was minted
     */
    public static function existing_code(int $pageid): ?string {
        global $DB;

        $rows = $DB->get_records('shortlink', [
            'component' => self::COMPONENT,
            'linktype' => self::LINKTYPE,
            'identifier' => (string) $pageid,
            'userid' => 0,
        ], 'id ASC', 'id, shortcode', 0, 1);
        $row = reset($rows);

        return $row ? (string) $row->shortcode : null;
    }

    /**
     * The address core's public shortlink route answers for a code.
     *
     * @param string $code The short code
     * @return \moodle_url The address, /p/code under the site's root
     */
    public static function public_url(string $code): \moodle_url {
        return \core\router\util::get_path_for_callable(
            [\core\route\shortlink::class, 'handle_public_shortlink'],
            ['shortcode' => $code]
        );
    }

    /**
     * Forget every short code of a page: its rows in the shortlink table, and no other page's.
     *
     * Core never deletes from that table, so a page that goes away takes its codes with it here.
     *
     * @param int $pageid Page id
     * @return void
     */
    public static function forget(int $pageid): void {
        global $DB;

        $DB->delete_records('shortlink', [
            'component' => self::COMPONENT,
            'linktype' => self::LINKTYPE,
            'identifier' => (string) $pageid,
        ]);
    }

    /**
     * Mint a public short code for a page, through core's own manager.
     *
     * Core draws codes until it finds one no row of the table holds, and inserts it inside a
     * delegated transaction of its own; the page's oldest row is then read back and returned, so two
     * saves of the same page racing each other leave two rows and one address.
     *
     * There is deliberately no retry around the call. The one failure left is a concurrent insert of
     * the very same random code between core's check and its insert, and core's transaction is then
     * left undisposed — on PostgreSQL every later statement of the request would fail inside it — so
     * catching the exception here would turn a vanishingly rare error into a broken request. It
     * propagates instead, and the default exception handler rolls the transaction back.
     *
     * @param int $pageid Page id
     * @return string The code now on record for the page
     * @throws \coding_exception When no row can be read back after the insert
     */
    private static function mint(int $pageid): string {
        \core\di::get(\core\shortlink::class)->create_public_shortlink(self::COMPONENT, self::LINKTYPE, (string) $pageid);

        return self::existing_code($pageid) ?? throw new \coding_exception('No short code on record for page ' . $pageid);
    }
}
