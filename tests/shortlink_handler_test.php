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
 * Tests for the shortlink handler.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use local_page\local\links;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \local_page\shortlink_handler, the class core's /p/ route asks about this plugin's codes.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(shortlink_handler::class)]
final class shortlink_handler_test extends \advanced_testcase {
    /**
     * Leave no router built under this test's setting behind.
     *
     * @return void
     */
    protected function tearDown(): void {
        \core\di::reset_container();
        parent::tearDown();
    }

    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * Core finds the handler by name, and it answers the one link type the plugin mints.
     *
     * @return void
     */
    public function test_core_finds_the_handler_and_its_link_type(): void {
        $handler = \core\di::get('local_page\shortlink_handler');

        $this->assertInstanceOf(\core\shortlink_handler_interface::class, $handler);
        $this->assertSame([links::LINKTYPE], $handler->get_valid_linktypes());
    }

    /**
     * A page's id answers its canonical address under the router setting of the moment.
     *
     * @return void
     */
    public function test_a_pages_id_answers_its_canonical_address(): void {
        global $CFG;

        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $handler = new shortlink_handler();

        foreach ([false, true] as $configured) {
            $CFG->routerconfigured = $configured;
            \core\di::reset_container();
            $this->assertSame(
                links::page($page)->out(false),
                $handler->process_shortlink(links::LINKTYPE, (string) $page->id)->out(false),
                'The row holds an id, never an address: the answer follows the router setting.'
            );
        }
    }

    /**
     * A foreign link type, a malformed id, a deleted page or a missing one answers null, core's "not found".
     *
     * @return void
     */
    public function test_anything_but_a_live_page_answers_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $deleted = $this->pages()->create_category_page($categoryid, ['menuname' => 'gone', 'deleted' => 1]);
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_page}') + 1000;
        $handler = new shortlink_handler();

        $this->assertNull($handler->process_shortlink('course', (string) $page->id), 'A foreign link type.');
        $this->assertNull($handler->process_shortlink(links::LINKTYPE, (string) $deleted->id), 'A deleted page.');
        $this->assertNull($handler->process_shortlink(links::LINKTYPE, (string) $missing), 'A page that is not there.');
        foreach (['', '0', '-1', '1.5', 'abc', ' ' . $page->id] as $identifier) {
            $this->assertNull($handler->process_shortlink(links::LINKTYPE, $identifier), "Identifier '{$identifier}'.");
        }

        // Control: the page of the same category that is not deleted answers.
        $this->assertNotNull($handler->process_shortlink(links::LINKTYPE, (string) $page->id));
    }

    /**
     * A page whose stored context no longer exists answers null, not an error.
     *
     * links::page() resolves the stored context with MUST_EXIST, so a row left naming a context that
     * is gone makes it throw; the handler answers that as core's "not found".
     *
     * @return void
     */
    public function test_a_page_whose_context_is_gone_answers_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $handler = new shortlink_handler();

        // Control: with its own context the page answers its address.
        $this->assertSame(
            links::page($page)->out(false),
            $handler->process_shortlink(links::LINKTYPE, (string) $page->id)->out(false)
        );

        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {context}') + 1000;
        $DB->set_field('local_page', 'contextid', $missing, ['id' => $page->id]);

        $this->assertNull($handler->process_shortlink(links::LINKTYPE, (string) $page->id));
    }
}
