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
 * Tests for the save path's minting of a category page's public short address.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use local_page\local\links;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The save path mints a category page's /p/ code, once, and only while the router is configured.
 *
 * The form is submitted with moodleform::mock_submit() and saved through the renderer, the way
 * edit.php saves it. save_page() ends in redirect(), which under PHPUnit throws
 * 'redirecterrordetected' because a CLI script cannot redirect; the tests catch exactly that, which
 * is also the proof that the save reached its end.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_page_renderer::class)]
final class save_page_test extends \advanced_testcase {
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
     * Declare the site's router configured or not.
     *
     * @param bool $configured Whether the router is configured
     * @return void
     */
    private function set_router(bool $configured): void {
        global $CFG;

        $CFG->routerconfigured = $configured;
        \core\di::reset_container();
    }

    /**
     * Submit the editor for a page in a context and save it, the way edit.php does.
     *
     * @param \core\context $context Context the page belongs to
     * @param int $pageid Existing page id, or 0 for a new page
     * @param string $menuname Friendly URL to submit
     * @return int The saved page's id
     */
    private function save(\core\context $context, int $pageid, string $menuname): int {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/local/page/renderer.php');

        $PAGE = new \moodle_page();
        if ($context->contextlevel == CONTEXT_COURSECAT) {
            $PAGE->set_category_by_id((int) $context->instanceid);
        } else {
            $PAGE->set_context($context);
        }
        $PAGE->set_url(new \moodle_url('/local/page/edit.php'));

        \pages_edit_product_form::mock_submit([
            'id' => $pageid,
            'contextid' => \local_page\local\scope::stored_contextid($context),
            'pagename' => 'Handbook',
            'menuname' => $menuname,
            'status' => 'live',
            'onlyloggedin' => 0,
            'hidetitle' => 'no',
            'accesslevel' => '',
            'pagecontent' => ['text' => '<p>Body</p>', 'format' => FORMAT_HTML, 'itemid' => file_get_unused_draft_itemid()],
            'contenthtml' => '',
            'metadescription' => '',
            'metakeywords' => '',
            'metaauthor' => '',
            'metatitle' => '',
            'metarobots' => '',
        ]);

        try {
            $PAGE->get_renderer('local_page')->save_page(custompage::load($pageid, true), $context);
            $this->fail('save_page() ends in a redirect to the editor.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode, $exception->getMessage());
        }

        $params = ['menuname' => $menuname, 'contextid' => \local_page\local\scope::stored_contextid($context)];
        return (int) $DB->get_field('local_page', 'id', $params, MUST_EXIST);
    }

    /**
     * A category page is given its code at its first save, and keeps that one code when saved again.
     *
     * @return void
     */
    public function test_a_category_page_gets_its_code_at_the_first_save_and_keeps_it(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_router(true);
        $context = \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);

        $pageid = $this->save($context, 0, 'handbook');
        $code = links::existing_code($pageid);
        $this->assertNotNull($code, 'The first save minted a code.');

        $this->assertSame($pageid, $this->save($context, $pageid, 'handbook'), 'The second save edits the same page.');
        $this->assertSame($code, links::existing_code($pageid), 'The same code after the second save.');
        $this->assertSame(1, $DB->count_records('shortlink', ['component' => links::COMPONENT, 'identifier' => (string) $pageid]));
    }

    /**
     * Nothing is minted without the router, nor for a site-wide page with it.
     *
     * @return void
     */
    public function test_nothing_is_minted_without_the_router_or_for_a_site_page(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $context = \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);

        $this->set_router(false);
        $this->save($context, 0, 'handbook');

        $this->set_router(true);
        $this->save(\core\context\system::instance(), 0, 'welcome');

        $this->assertSame(0, $DB->count_records('shortlink', ['component' => links::COMPONENT]));
    }
}
