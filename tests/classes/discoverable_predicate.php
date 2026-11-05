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
 * A stand-in for local_unlistedcourses' per-viewer category discoverability.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\tests;

/**
 * A stand-in for \local_unlistedcourses\category_access, answering from a list and counting.
 *
 * It answers false for exactly the ids it was reset with (the categories unlisted for the viewer) and
 * true for every other one, which is the real predicate's default for a category it knows nothing
 * against. local_unlistedcourses is not a dependency, so \local_page\local\categoryaccess takes this
 * class through its $predicate parameter wherever a test needs the unlisted answer on every site.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class discoverable_predicate {
    /** @var array Category ids this double withholds, as keys. */
    private static array $withheld = [];

    /** @var int Questions asked since the last reset. */
    private static int $calls = 0;

    /**
     * Start over: these categories are withheld from the viewer, every other one is discoverable.
     *
     * @param array $categoryids Category ids to answer false for
     * @return void
     */
    public static function reset(array $categoryids = []): void {
        self::$withheld = array_fill_keys(array_map('intval', $categoryids), true);
        self::$calls = 0;
    }

    /**
     * How many times is_category_discoverable() was asked since the last reset.
     *
     * @return int
     */
    public static function calls(): int {
        return self::$calls;
    }

    /**
     * The predicate's own signature: whether the current user may discover the category.
     *
     * @param int $categoryid Course category id
     * @return bool
     */
    public static function is_category_discoverable(int $categoryid): bool {
        self::$calls++;
        return !isset(self::$withheld[$categoryid]);
    }
}
