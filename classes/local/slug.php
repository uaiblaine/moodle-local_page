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
 * Upstream treats menuname as a free-text field: nothing stops two live pages carrying the same
 * slug, and nothing releases a slug when a page is deleted. Both matter because the slug is a URL.
 * custompage::load_by_menuname() resolves a duplicate with ORDER BY id DESC and IGNORE_MULTIPLE, so
 * the page a visitor reaches is whichever was saved last — an editor can take over somebody else's
 * address by typing it, without any warning, and the previous owner's page simply stops answering.
 *
 * From this fork onwards a slug is unique among rows that are not deleted, and deleting a page
 * mangles its slug so the address is released. A restored page therefore needs a new slug; that is
 * deliberate, because the alternative is a delete that keeps holding an address nobody can see.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class slug {
    /** @var int Stored width of the menuname column, in characters (db/install.xml). */
    private const MAXLENGTH = 255;

    /**
     * Brings every stored slug into line with the rules above, in place.
     *
     * Runs in four passes over the whole table, in this order, and is idempotent: a second call
     * changes nothing and returns 0.
     *
     * 1. every value is trimmed and lower-cased, and truncated to the column width;
     * 2. a live row with an empty slug is given page-<id>, so every page is addressable;
     * 3. a deleted row is given the deleted_name() form, releasing the address it was holding;
     * 4. among live rows sharing a slug the lowest id keeps it and the others gain -<id>.
     *
     * Pass 4 runs in id order and records what it has handed out as it goes, so a suffixed value
     * that happens to collide with a later row's slug pushes that row along too rather than
     * creating a fresh duplicate.
     *
     * @return int Number of rows whose menuname was rewritten
     */
    public static function normalise_all(): int {
        global $DB;

        $rows = $DB->get_records('local_page', null, 'id ASC', 'id, menuname, deleted');

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

        // Pass 4: uniqueness among the rows that are still live.
        $taken = [];
        foreach ($rows as $row) {
            if ((int) $row->deleted !== 0) {
                continue;
            }
            $id = (int) $row->id;
            if (isset($taken[$wanted[$id]])) {
                $wanted[$id] = self::suffixed($wanted[$id], $id);
            }
            $taken[$wanted[$id]] = true;
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
     * @param string $menuname Slug to test; compared trimmed and lower-cased, as it is stored
     * @param int $exceptid Page id to exclude, so a page keeps its own slug on re-save
     * @return bool
     */
    public static function is_taken(string $menuname, int $exceptid = 0): bool {
        global $DB;

        $menuname = \core_text::strtolower(trim($menuname));
        if ($menuname === '') {
            return false;
        }

        $select = 'menuname = :menuname AND deleted = 0';
        $params = ['menuname' => $menuname];

        if ($exceptid > 0) {
            $select .= ' AND id <> :exceptid';
            $params['exceptid'] = $exceptid;
        }

        return $DB->record_exists_select('local_page', $select, $params);
    }

    /**
     * The slug a duplicate is moved to: the original with -<id> appended.
     *
     * @param string $menuname Slug that was already taken
     * @param int $id Page id of the row being moved
     * @return string
     */
    private static function suffixed(string $menuname, int $id): string {
        $suffix = '-' . $id;

        if (self::ends_with($menuname, $suffix)) {
            return self::truncate($menuname);
        }

        return self::stem($menuname, $suffix) . $suffix;
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
