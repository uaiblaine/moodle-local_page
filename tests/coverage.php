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
 * Coverage information for local_page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Selects the plugin files that PHPUnit measures when generating coverage reports.
 *
 * Without this file the default include list would measure `classes/`,
 * `tests/generator/` and the top-level `lib.php` and `renderer.php`. That
 * default is shaped for activity modules: it leaves out `forms/edit.php`, which
 * holds the whole edit form for this plugin, so the percentage would be
 * computed over a code base missing one of its largest files.
 *
 * Extends the namespaced class rather than the phpunit_coverage_info alias,
 * whose file lib/phpunit/classes/coverage_info.php is deprecated on 5.2; core's
 * own tests/coverage.php files use \core\test\phpunit\coverage_info too.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_page_coverage extends \core\test\phpunit\coverage_info {
    /**
     * @var array Individual plugin files measured for coverage.
     *
     * lib.php and renderer.php are already in core's default include list;
     * the form file and the code the upgrade steps call have to be named here.
     */
    protected $includelistfiles = [
        'db/upgradelib.php',
        'forms/edit.php',
    ];
}

return new local_page_coverage();
