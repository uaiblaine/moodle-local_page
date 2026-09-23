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
 * Tests for the access, publication and write-path rules in local/page/lib.php.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');

/**
 * Tests for the access, publication and write-path rules in local/page/lib.php.
 *
 * Test cases are looped inside the methods rather than fed by data providers, so the file runs
 * unchanged under the PHPUnit of every Moodle version the plugin supports.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_page_publish_window_is_open')]
#[CoversFunction('local_page_ogimage_is_servable')]
#[CoversFunction('local_page_pluginfile')]
#[CoversFunction('local_page_require_editable_page')]
final class lib_test extends \advanced_testcase {
    /**
     * The plugin's data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A new user holding one capability at the system context.
     *
     * @param string $capability Capability name
     * @return \stdClass The user
     */
    private function user_holding(string $capability): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability($capability, CAP_ALLOW, $roleid, \context_system::instance()->id);
        role_assign($roleid, $user->id, \context_system::instance()->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $user;
    }

    /**
     * The bytes of a small, real PNG.
     *
     * @return string
     */
    private function png(): string {
        $image = imagecreatetruecolor(4, 3);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * Store a file in the ogimage area of the system context, under a page's id.
     *
     * @param int $pageid Page id, which is the area's item id
     * @param string $filename File name
     * @param string $content File content
     * @return \stored_file
     */
    private function store_ogimage(int $pageid, string $filename, string $content): \stored_file {
        return get_file_storage()->create_file_from_string(
            [
                'contextid' => \context_system::instance()->id,
                'component' => 'local_page',
                'filearea' => 'ogimage',
                'itemid' => $pageid,
                'filepath' => '/',
                'filename' => $filename,
            ],
            $content
        );
    }

    /**
     * Ask local_page_pluginfile() for an Open Graph image, the way a client already holding that file asks.
     *
     * The request carries the file's ETag in If-None-Match, so a file that IS sent is answered
     * "304 Not Modified" by readfile_accel() before any byte is written, and with 'dontdie' the call
     * returns null, where every refusal returns false. That is what lets a test assert that a file was
     * served without writing binary data into the PHPUnit output. The send still calls header(), which
     * warns once PHPUnit has printed anything; exactly that warning is swallowed while the call runs.
     *
     * @param array $args The path after the file area: item id, then file name
     * @param string $content The bytes of the file expected to be sent
     * @return bool|null False for a refusal, null for a file sent
     */
    private function ask_ogimage_route(array $args, string $content): ?bool {
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"' . sha1($content) . '"';
        set_error_handler(
            static fn (int $errno, string $errstr): bool => str_starts_with($errstr, 'Cannot modify header information'),
            E_WARNING
        );
        try {
            return local_page_pluginfile(null, null, \context_system::instance(), 'ogimage', $args, false, ['dontdie' => true]);
        } finally {
            restore_error_handler();
            unset($_SERVER['HTTP_IF_NONE_MATCH']);
        }
    }

    /**
     * Publication states, each with whether the page counts as published.
     *
     * @return array Label => [record overrides, published]
     */
    private function publication_cases(): array {
        $now = time();

        return [
            'live, no window' => [['status' => 'live'], true],
            'live, inside its window' => [['pagedate' => $now - HOURSECS, 'enddate' => $now + HOURSECS], true],
            'draft' => [['status' => 'draft'], false],
            'archived' => [['status' => 'archived'], false],
            'deleted' => [['deleted' => 1], false],
            'not yet started' => [['pagedate' => $now + HOURSECS], false],
            'expired' => [['enddate' => $now - HOURSECS], false],
            'draft inside its window' => [
                ['status' => 'draft', 'pagedate' => $now - HOURSECS, 'enddate' => $now + HOURSECS],
                false,
            ],
        ];
    }

    /**
     * The Open Graph image of a page is servable exactly while the page is published.
     *
     * The gate answers the same to an anonymous scraper on a site forcing login and to an administrator,
     * because it reads no capability. The viewer predicate, which reads the publish window through the
     * same function, is asserted beside it for a plain anonymous visitor: the two agree on every case.
     *
     * @return void
     */
    public function test_ogimage_is_servable_only_for_a_published_page(): void {
        global $CFG;

        $this->resetAfterTest();

        foreach ($this->publication_cases() as $label => [$record, $published]) {
            $page = $this->pages()->create_page($record);

            $CFG->forcelogin = 1;
            $this->setUser(null);
            $this->assertSame($published, local_page_ogimage_is_servable($page), "{$label}: anonymous, forcelogin");

            $CFG->forcelogin = 0;
            $this->assertSame($published, local_page_user_can_view_page($page), "{$label}: viewer predicate");

            $this->setAdminUser();
            $this->assertSame($published, local_page_ogimage_is_servable($page), "{$label}: administrator");
        }

        // A row that was never saved has no image to serve; the same row with its id is the control.
        $page = $this->pages()->create_page();
        $this->assertTrue(local_page_ogimage_is_servable($page));
        $page->id = 0;
        $this->assertFalse(local_page_ogimage_is_servable($page));
    }

    /**
     * Restrictions on the reader leave the image servable; the viewer predicate is the control.
     *
     * A link preview is fetched anonymously, so gating the image on "only logged in" or on a capability
     * would break every preview; index.php only hands the URL to viewers the predicate admits.
     *
     * @return void
     */
    public function test_ogimage_gate_ignores_restrictions_on_the_reader(): void {
        global $CFG;

        $this->resetAfterTest();

        $restricted = [
            'only logged in' => $this->pages()->create_page(['onlyloggedin' => 1]),
            'capability restricted' => $this->pages()->create_page(['accesslevel' => 'moodle/site:config']),
        ];

        $CFG->forcelogin = 1;
        $this->setUser(null);

        foreach ($restricted as $label => $page) {
            $this->assertTrue(local_page_ogimage_is_servable($page), "{$label}: image");
            $this->assertFalse(local_page_user_can_view_page($page), "{$label}: page");
        }
    }

    /**
     * pluginfile.php serves the Open Graph image of a published page and of no other.
     *
     * @return void
     */
    public function test_ogimage_route_serves_the_image_of_a_published_page_only(): void {
        $this->resetAfterTest();
        $this->setUser(null);

        $png = $this->png();

        foreach ($this->publication_cases() as $label => [$record, $published]) {
            $page = $this->pages()->create_page($record);
            $file = $this->store_ogimage($page->id, 'og.png', $png);

            // Control: the file is in the area, so a refusal is the gate's and not a missing file.
            $this->assertSame($page->id, (int) $file->get_itemid(), $label);

            $expected = $published ? null : false;
            $this->assertSame($expected, $this->ask_ogimage_route([$page->id, 'og.png'], $png), $label);
        }

        // An item id that names no page is refused as well.
        $this->assertFalse($this->ask_ogimage_route([$page->id + 1000, 'og.png'], $png));
    }

    /**
     * The write-path check hands back the stored row, and null for a new page.
     *
     * @return void
     */
    public function test_require_editable_page_returns_the_stored_row(): void {
        $this->resetAfterTest();
        $this->setUser($this->user_holding('local/page:addpages'));

        $page = $this->pages()->create_page();

        $row = local_page_require_editable_page($page->id);
        $this->assertSame($page->id, (int) $row->id);
        $this->assertSame($page->pagename, $row->pagename);

        $this->assertNull(local_page_require_editable_page(0));
    }

    /**
     * An id naming no page, or a deleted one, is refused.
     *
     * @return void
     */
    public function test_require_editable_page_refuses_a_missing_or_deleted_page(): void {
        $this->resetAfterTest();
        $this->setUser($this->user_holding('local/page:addpages'));

        $live = $this->pages()->create_page();
        $deleted = $this->pages()->create_page(['deleted' => 1]);

        // Control: the live row is returned to the same user, so the refusals below are the row lookup.
        $this->assertNotNull(local_page_require_editable_page($live->id));

        foreach (['missing' => $live->id + 1000, 'deleted' => $deleted->id] as $label => $id) {
            try {
                local_page_require_editable_page($id);
                $this->fail("{$label}: expected an exception");
            } catch (\moodle_exception $e) {
                $this->assertSame('pagenotfound', $e->errorcode, $label);
            }
        }
    }

    /**
     * The write-path check demands local/page:addpages, for an existing page and a new one alike.
     *
     * @return void
     */
    public function test_require_editable_page_demands_the_editing_capability(): void {
        $this->resetAfterTest();

        $page = $this->pages()->create_page();
        $this->setUser($this->getDataGenerator()->create_user());

        foreach (['existing page' => $page->id, 'new page' => 0] as $label => $id) {
            try {
                local_page_require_editable_page($id);
                $this->fail("{$label}: expected an exception");
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode, $label);
            }
        }

        // Control: the same two calls pass for a holder of the capability.
        $this->setUser($this->user_holding('local/page:addpages'));
        $this->assertNotNull(local_page_require_editable_page($page->id));
        $this->assertNull(local_page_require_editable_page(0));
    }
}
