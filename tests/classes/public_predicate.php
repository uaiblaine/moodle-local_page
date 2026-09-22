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
 * A stand-in for local_unlistedcourses' public category predicate.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\tests;

/**
 * A stand-in for \local_unlistedcourses\category_discoverability, answering from a list and counting.
 *
 * The CI matrix does not install local_unlistedcourses, so every test that needs a category to BE
 * public — the control beside each refusal — hands this class to the code under test through its
 * $predicate parameter instead. It answers true for exactly the ids it was reset with, and counts
 * every question, so a test can also assert that a logged-in user or a site-wide page never asks.
 *
 * It lives in tests/classes because three test files use it, and Moodle autoloads the
 * local_page\tests namespace from here during a PHPUnit run: a copy defined inside each test file
 * would be declared twice the moment two of those files run together.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class public_predicate {
    /** @var array Category ids this double calls public, as keys. */
    private static array $public = [];

    /** @var int Questions asked since the last reset. */
    private static int $calls = 0;

    /**
     * Start over: these categories are public, every other one is not, and nothing has been asked.
     *
     * @param array $categoryids Category ids to answer true for
     * @return void
     */
    public static function reset(array $categoryids = []): void {
        self::$public = array_fill_keys(array_map('intval', $categoryids), true);
        self::$calls = 0;
    }

    /**
     * How many times is_public() was asked since the last reset.
     *
     * @return int
     */
    public static function calls(): int {
        return self::$calls;
    }

    /**
     * The predicate's own signature: whether the category may be served to a visitor.
     *
     * @param int $categoryid Course category id
     * @return bool
     */
    public static function is_public(int $categoryid): bool {
        self::$calls++;
        return isset(self::$public[$categoryid]);
    }
}
