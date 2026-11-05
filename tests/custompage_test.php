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
 * Tests for the page object the viewer loads.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');

/**
 * Tests for \local_page\custompage, as index.php hands it to the access rule and the renderer.
 *
 * Every other access test hands local_page_user_can_view_page() a row from the table. index.php hands
 * it a custompage, whose properties come through __get(), and the two must be read alike.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(custompage::class)]
#[CoversFunction('local_page_user_can_view_page')]
final class custompage_test extends \advanced_testcase {
    /**
     * The plugin's data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * isset() and empty() read a loaded page's data, as they read the row it came from.
     *
     * @return void
     */
    public function test_isset_and_empty_read_the_page_data(): void {
        $this->resetAfterTest();

        $row = $this->pages()->create_page(['accesslevel' => 'moodle/course:create', 'metatitle' => '']);
        $page = custompage::load($row->id);

        $this->assertTrue(isset($page->accesslevel));
        $this->assertFalse(empty($page->accesslevel));
        $this->assertFalse(empty($page->menuname));

        // Controls: an empty field reads as empty and a field the table does not have as unset.
        $this->assertTrue(empty($page->metatitle));
        $this->assertFalse(isset($page->nosuchfield));
        $this->assertTrue(empty($page->nosuchfield));
    }

    /**
     * A page's required capability is applied to the page index.php loads, at ?id= and at its friendly URL.
     *
     * Until custompage had __isset(), empty() read every access level on it as unset, and a page kept
     * for holders of a capability was shown in full to an anonymous visitor. The control is a holder
     * of the capability, who reads the same page through the same calls.
     *
     * @return void
     */
    public function test_the_access_level_is_applied_to_a_loaded_page(): void {
        global $PAGE;

        $this->resetAfterTest();

        $row = $this->pages()->create_page([
            'menuname' => 'creators',
            'accesslevel' => 'moodle/course:create',
            'pagecontent' => '<p>Creators only</p>',
        ]);
        $loads = [
            'by id' => fn() => custompage::load($row->id),
            'by friendly URL' => fn() => custompage::load_by_menuname('creators'),
        ];
        $PAGE->set_context(\context_system::instance());
        $renderer = $PAGE->get_renderer('local_page');
        $noaccess = get_string('noaccess', 'local_page');

        $creator = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/course:create', CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $creator->id, \context_system::instance()->id);
        $readers = [
            'anonymous visitor' => [null, false],
            'plain user' => [$this->getDataGenerator()->create_user(), false],
            'holder of the capability' => [$creator, true],
        ];

        foreach ($readers as $who => [$user, $canview]) {
            $this->setUser($user);
            accesslib_clear_all_caches_for_unit_testing();
            foreach ($loads as $how => $load) {
                $page = $load();
                $this->assertSame((int) $row->id, (int) $page->id, "{$who}, {$how}: the page itself was loaded");
                $this->assertSame($canview, local_page_user_can_view_page($page), "{$who}, {$how}");

                $body = $renderer->showpage($page);
                if ($canview) {
                    $this->assertStringContainsString('Creators only', $body, "{$who}, {$how}: the body");
                } else {
                    $this->assertStringNotContainsString('Creators only', $body, "{$who}, {$how}: the body is withheld");
                    $this->assertStringContainsString($noaccess, $body, "{$who}, {$how}: the \"no access\" page");
                }
            }
        }
    }
}
