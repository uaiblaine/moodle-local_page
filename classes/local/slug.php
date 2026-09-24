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
 * Friendly-URL slug rules for local_page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * Normalisation and uniqueness for the menuname column.
 *
 * The slug is a URL, so it is unique among the live rows of one context, and deleting a page
 * mangles its slug so the address is released. custompage::load_by_menuname() resolves a duplicate
 * with ORDER BY id DESC and IGNORE_MULTIPLE, so without the uniqueness rule an editor could take
 * over another page's address just by typing it.
 *
 * A restored page therefore needs a new slug; that is deliberate, because the alternative is a
 * deleted page that keeps holding an address nobody can see.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class slug {
    /** @var int Stored width of the menuname column, in characters (db/install.xml). */
    private const MAXLENGTH = 255;

    /**
     * @var array Slugs Moodle itself answers on, which a page may therefore not take.
     *
     * Source: the top-level entries of the Moodle 5.2 webroot — every directory and every script
     * under public/ — plus the path segments core's routing engine uses in its own routes
     * (p, s, esm, check, templates, api). A friendly URL is served by rewriting the site root, so
     * a page holding one of these names either never answers (the real path wins) or hides part
     * of Moodle (the rewrite wins). Neither is something an author can debug from the form.
     */
    private const RESERVED = [
        'admin', 'ai', 'analytics', 'api', 'auth', 'availability', 'backup', 'badges', 'blocks',
        'blog', 'brokenfile', 'cache', 'calendar', 'check', 'cohort', 'comment', 'communication',
        'competency', 'completion', 'config', 'contentbank', 'course', 'customfield', 'dataformat',
        'draftfile', 'editmode', 'enrol', 'error', 'esm', 'favourites', 'file', 'files', 'filter',
        'grade', 'group', 'h5p', 'help', 'help_ajax', 'index', 'install', 'iplookup', 'lang',
        'lib', 'local', 'login', 'media', 'message', 'mnet', 'mod', 'my', 'notes', 'p', 'payment',
        'pix', 'plagiarism', 'pluginfile', 'portfolio', 'privacy', 'question', 'r', 'rating',
        'report', 'reportbuilder', 'repository', 'rss', 's', 'search', 'security', 'sms', 'tag',
        'templates', 'theme', 'tokenpluginfile', 'user', 'userpix', 'version', 'webservice',
    ];

    /**
     * @var string Slugs shaped like a frankenstyle component name, which are reserved too.
     *
     * A plugin installed tomorrow may register a route under its own frankenstyle name, so the
     * list above cannot be complete by enumeration. Every current plugin type is named here
     * instead, and a slug beginning with one of them plus an underscore is refused.
     */
    private const RESERVED_PREFIX_PATTERN = '/^(local|mod|block|auth|enrol|theme|report|tool|format|qtype|filter|repository'
        . '|portfolio|availability|customfield|editor|media|antivirus|cachestore|cachelock|logstore|mlbackend|paygw'
        . '|aiprovider|aiplacement|communication|h5plib|contentbank|dataformat|fileconverter|mnetservice|search'
        . '|webservice|profilefield|gradingform|gradeexport|gradeimport|gradereport|qbank|qbehaviour|qformat|quizaccess'
        . '|assignsubmission|assignfeedback|booktool|forumreport|datafield|datapreset|ltisource|ltiservice|scormreport'
        . '|workshopform|workshopallocation|workshopeval|tiny|atto|calendartype|coursereport|smsgateway)_/';

    /**
     * Whether a slug is one Moodle answers on itself.
     *
     * Only the form consults this. normalise_all() deliberately does not rename a legacy row whose
     * slug turns out to be reserved: that row has been answering at its address for as long as the
     * site's rewrite rules have allowed it to, and renaming it at upgrade time would break a
     * published URL to fix a URL that may never have been broken. New ones are refused on the way
     * in, which is where the cost of the refusal is zero.
     *
     * @param string $menuname Slug to test; compared trimmed and lower-cased, as it is stored
     * @return bool
     */
    public static function is_reserved(string $menuname): bool {
        $menuname = \core_text::strtolower(trim($menuname));
        if ($menuname === '') {
            return false;
        }

        if (in_array($menuname, self::RESERVED, true)) {
            return true;
        }

        return preg_match(self::RESERVED_PREFIX_PATTERN, $menuname) === 1;
    }

    /**
     * Brings every stored slug into line with the rules above, in place.
     *
     * Runs in four passes over the whole table, in this order, and is idempotent: a second call
     * changes nothing and returns 0.
     *
     * 1. every value is trimmed and lower-cased, and truncated to the column width;
     * 2. a live row with an empty slug is given page-<id>, so every page is addressable;
     * 3. a deleted row is given the deleted_name() form, releasing the address it was holding;
     * 4. among live rows of one context sharing a slug the lowest id keeps it and the others move to
     *    the first free form of -<id>, -<id>-2, -<id>-3 and so on.
     *
     * Pass 4 runs in id order. A form is free when no row has claimed it earlier in the pass and no
     * other live row of the context holds it, so a moved page never takes a later page's slug and
     * never lands on a value it would have to leave on the next run: that is what makes the routine
     * idempotent. unique_in_context() applies the same rule against the stored rows. The pass groups
     * by contextid because uniqueness is per context: two categories may each own a page called
     * "contato" and neither has to move.
     *
     * A slug that is_reserved() would refuse is left exactly as it is, deliberately — see that
     * method for why a rename at upgrade time is the more expensive mistake.
     *
     * @return int Number of rows whose menuname was rewritten
     */
    public static function normalise_all(): int {
        global $DB;

        $rows = $DB->get_records('local_page', null, 'id ASC', 'id, menuname, deleted, contextid');

        // Passes 1 to 3: everything that depends on one row alone.
        $wanted = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $name = self::truncate(\core_text::strtolower(trim((string) $row->menuname)));

            if ((int) $row->deleted !== 0) {
                $name = self::deleted_name($name, $id);
            } else if ($name === '') {
                $name = self::truncate('page-' . $id);
            }

            $wanted[$id] = $name;
        }

        // Pass 4: uniqueness among the rows that are still live, within each context.
        $held = [];
        foreach ($rows as $row) {
            if ((int) $row->deleted === 0) {
                $held[(int) ($row->contextid ?? 0)][$wanted[(int) $row->id]] = true;
            }
        }

        $taken = [];
        foreach ($rows as $row) {
            if ((int) $row->deleted !== 0) {
                continue;
            }
            $id = (int) $row->id;
            $scope = (int) ($row->contextid ?? 0);
            if (isset($taken[$scope][$wanted[$id]])) {
                $wanted[$id] = self::first_free(
                    $wanted[$id],
                    $id,
                    static fn (string $candidate): bool => isset($taken[$scope][$candidate]) || isset($held[$scope][$candidate])
                );
            }
            $taken[$scope][$wanted[$id]] = true;
        }

        $changed = 0;
        foreach ($rows as $row) {
            $id = (int) $row->id;
            if ($wanted[$id] !== (string) $row->menuname) {
                $DB->set_field('local_page', 'menuname', $wanted[$id], ['id' => $id]);
                $changed++;
            }
        }

        return $changed;
    }

    /**
     * The slug a page carries once it has been deleted.
     *
     * The id is part of the suffix so that deleting two pages that once shared an address cannot
     * produce two identical mangled values, and so that the result is stable: applying this to its
     * own output returns it unchanged, which is what lets normalise_all() run twice.
     *
     * An empty slug becomes page-<id> first, so a deleted row is never left with a bare suffix.
     *
     * @param string $menuname Slug as currently stored
     * @param int $id Page id
     * @return string
     */
    public static function deleted_name(string $menuname, int $id): string {
        $suffix = '-deleted-' . $id;

        $base = \core_text::strtolower(trim($menuname));
        if ($base === '') {
            $base = 'page-' . $id;
        }

        if (self::ends_with($base, $suffix)) {
            return self::truncate($base);
        }

        return self::stem($base, $suffix) . $suffix;
    }

    /**
     * Whether a slug is already in use by a page that has not been deleted.
     *
     * Deleted rows are ignored on purpose: their slug has been mangled by deleted_name() and the
     * address they used to hold is free again.
     *
     * Uniqueness is per context, so the answer depends on which scope is asking: two categories
     * may each own "contato", and neither collides with a site-wide page of that name. The
     * parameter carries the stored convention, 0 for the system scope — see
     * {@see \local_page\local\scope} for why the column reads that way.
     *
     * @param string $menuname Slug to test; compared trimmed and lower-cased, as it is stored
     * @param int $exceptid Page id to exclude, so a page keeps its own slug on re-save
     * @param int $contextid Stored contextid to test within; 0 is the system scope
     * @return bool
     */
    public static function is_taken(string $menuname, int $exceptid = 0, int $contextid = 0): bool {
        global $DB;

        $menuname = \core_text::strtolower(trim($menuname));
        if ($menuname === '') {
            return false;
        }

        $select = 'menuname = :menuname AND deleted = 0 AND contextid = :contextid';
        $params = ['menuname' => $menuname, 'contextid' => $contextid];

        if ($exceptid > 0) {
            $select .= ' AND id <> :exceptid';
            $params['exceptid'] = $exceptid;
        }

        return $DB->record_exists_select('local_page', $select, $params);
    }

    /**
     * The slug a live page may carry in a context it is arriving in.
     *
     * Its own, when no other live page of that context holds it. Otherwise the page already there
     * keeps its address and the arriving page moves to the first free form of -<id>, -<id>-2,
     * -<id>-3 and so on, which is how normalise_all() settles a duplicate; the result is never a
     * duplicate.
     *
     * Nothing is written here, and the answer is only as good as the moment it was read: the caller
     * holds the lock the editor's save takes, so no save can take the value before it is stored.
     *
     * @param string $menuname The page's slug as stored
     * @param int $id The page id, excluded from the comparison
     * @param int $contextid Stored contextid of the context the page is arriving in
     * @return string The slug to store: the one given when it is free
     */
    public static function unique_in_context(string $menuname, int $id, int $contextid): string {
        if (!self::is_taken($menuname, $id, $contextid)) {
            return $menuname;
        }

        return self::first_free(
            \core_text::strtolower(trim($menuname)),
            $id,
            static fn (string $candidate): bool => self::is_taken($candidate, $id, $contextid)
        );
    }

    /**
     * The first free slug for a page whose own is taken: -<id>, then -<id>-2, -<id>-3 and so on.
     *
     * The single collision rule of this class, shared by normalise_all() and unique_in_context(),
     * which differ only in how they tell whether a candidate is free. A slug already ending in
     * -<id> is not given a second copy of the id: page-12 of page 12 moves to page-12-2. Each
     * candidate is shortened before its suffix so that it still fits the column.
     *
     * @param string $menuname Slug that is taken, as stored
     * @param int $id Page id of the row being moved
     * @param callable $istaken Answers whether a candidate slug is held by another live page of the context
     * @return string
     */
    private static function first_free(string $menuname, int $id, callable $istaken): string {
        $idsuffix = '-' . $id;
        $root = $menuname;
        if (self::ends_with($root, $idsuffix)) {
            $root = \core_text::substr($root, 0, \core_text::strlen($root) - \core_text::strlen($idsuffix));
        }

        $candidate = self::stem($root, $idsuffix) . $idsuffix;
        for ($counter = 2; $istaken($candidate); $counter++) {
            $suffix = $idsuffix . '-' . $counter;
            $candidate = self::stem($root, $suffix) . $suffix;
        }

        return $candidate;
    }

    /**
     * Shortens a slug so that appending $suffix still fits the column.
     *
     * @param string $menuname Slug to shorten
     * @param string $suffix Suffix that will be appended to the result
     * @return string
     */
    private static function stem(string $menuname, string $suffix): string {
        $room = self::MAXLENGTH - \core_text::strlen($suffix);
        if ($room <= 0) {
            return '';
        }

        return \core_text::substr($menuname, 0, $room);
    }

    /**
     * Whether a slug already carries a suffix.
     *
     * @param string $menuname Slug to test
     * @param string $suffix Suffix to look for
     * @return bool
     */
    private static function ends_with(string $menuname, string $suffix): bool {
        return \core_text::substr($menuname, -\core_text::strlen($suffix)) === $suffix;
    }

    /**
     * Truncates a slug to the stored width of the column.
     *
     * @param string $menuname Slug to truncate
     * @return string
     */
    private static function truncate(string $menuname): string {
        return \core_text::substr($menuname, 0, self::MAXLENGTH);
    }
}
