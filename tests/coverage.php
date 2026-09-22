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
 * Without this file the default include list would measure `classes/` and the
 * top-level `lib.php` and `renderer.php`, and nothing else. That default is
 * shaped for activity modules: it silently leaves out `forms/edit.php`, which
 * holds the whole edit form for this plugin, so the headline percentage would
 * be computed over a code base missing one of its largest files. Naming the
 * paths here fixes the denominator; it does not make the number look better.
 *
 * Extends the namespaced class rather than the phpunit_coverage_info alias:
 * lib/phpunit/classes/coverage_info.php is deprecated on 5.2 and all ten of
 * core's own tests/coverage.php files use \core\test\phpunit\coverage_info.
 * This plugin is 5.2-only, so there is no older branch to stay compatible with.
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
     * only the form file has to be named here.
     */
    protected $includelistfiles = [
        'forms/edit.php',
    ];
}

return new local_page_coverage();
