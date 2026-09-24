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
 * Tests for the save path: the public short address it mints, and the og image it stores.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use local_page\local\links;
use local_page\local\ogimage;
use local_page\tests\ogimage_fixture;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The save path mints a category page's /p/ code, once, and only while the router is configured,
 * and stores an og image only when its content is the image its name says.
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
     * @param array $extra Further fields to submit
     * @return int The saved page's id; with an empty $menuname, the newest page's
     */
    private function save(\core\context $context, int $pageid, string $menuname, array $extra = []): int {
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
        ] + $extra);

        try {
            $PAGE->get_renderer('local_page')->save_page(custompage::load($pageid, true), $context);
            $this->fail('save_page() ends in a redirect to the editor.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode, $exception->getMessage());
        }

        if ($menuname === '') {
            // The save named the page itself, so it is found as the row it inserted.
            return (int) $DB->get_field_sql('SELECT MAX(id) FROM {local_page}');
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
     * A page saved with no slug is named page-<id>, and page-<id>-2 when a page of its context holds that name.
     *
     * Nothing stops an author typing page-<id> into another page before a new page gets that id, so
     * the name the save gives goes through the same uniqueness rule as a typed one. The first save is
     * the control: with nobody holding the name the page gets page-<id> itself. The blocker is then
     * named after the id the next page will get, one above its own, and the save is asserted to have
     * got exactly that id, so the suffix is the rule at work and not a different id.
     *
     * @return void
     */
    public function test_a_page_saved_without_a_slug_gets_a_name_no_other_page_holds(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_router(false);
        $category = (int) $this->getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($category);
        $slug = static fn (int $pageid): string => (string) $DB->get_field('local_page', 'menuname', ['id' => $pageid]);

        $plain = $this->save($context, 0, '');
        $this->assertSame("page-{$plain}", $slug($plain));

        $generator = $this->getDataGenerator()->get_plugin_generator('local_page');
        $blocker = (int) $generator->create_category_page($category, ['menuname' => 'blocker'])->id;
        $nextid = $blocker + 1;
        $DB->set_field('local_page', 'menuname', "page-{$nextid}", ['id' => $blocker]);

        $named = $this->save($context, 0, '');
        $this->assertSame($nextid, $named, 'Precondition: the save got the id the blocker is named after.');
        $this->assertSame("page-{$named}-2", $slug($named));
        $this->assertSame("page-{$named}", $slug($blocker), 'The page that held the name keeps it.');
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

    /**
     * A page saved with an og image finds its size already measured, so the first render states it.
     *
     * save_page() measures nothing itself. Two things measure the draft on the way in, into
     * core/file_imageinfo under the content hash the stored file then has: core's validation of a
     * file manager with restricted types, through file_get_all_files_in_draftarea(), which calls
     * stored_file::get_imageinfo() on every readable file; and the form's content check,
     * {@see \local_page\local\ogimage::is_image()}, through is_valid_image(). This test holds the
     * property whichever of the two provides it.
     *
     * The cache is purged first and asserted cold, so what the save leaves in it can only come from
     * the save; the expected size is the one the PNG was drawn with, never a second measurement.
     *
     * @return void
     */
    public function test_a_saved_og_image_is_already_measured(): void {
        global $USER;

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_router(false);
        $context = \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);

        $png = ogimage_fixture::png(24, 10);
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(
            [
                'contextid' => \core\context\user::instance((int) $USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => 'cover.png',
            ],
            $png
        );

        $cache = \cache::make('core', 'file_imageinfo');
        $cache->purge();
        $this->assertFalse($cache->get(sha1($png)), 'Precondition: nothing measured this image yet.');

        $pageid = $this->save($context, 0, 'handbook', ['ogimage_filemanager' => $draftitemid]);

        $file = ogimage::stored($context, $pageid);
        $this->assertNotNull($file, 'The image was saved into the page\'s own area.');
        $this->assertSame(sha1($png), $file->get_contenthash());
        $this->assertSame(['width' => 24, 'height' => 10, 'mimetype' => 'image/png'], $cache->get(sha1($png)));
    }

    /**
     * An og image whose content is not the picture its name says stops the save, and nothing is stored.
     *
     * This is the form's content check seen from edit.php's side: save_page() writes only what
     * moodleform::get_data() hands it, and get_data() hands over nothing while validation() reports an
     * error. So no page row is written and no file reaches any og image area — which is also why the
     * save never reaches its redirect. The control submits the same fields with a real PNG under the
     * same name, and that save does reach it, so what stopped the first one was the image.
     *
     * @return void
     */
    public function test_an_og_image_that_is_not_the_picture_its_name_says_is_not_saved(): void {
        global $CFG, $DB, $PAGE, $USER;
        require_once($CFG->dirroot . '/local/page/renderer.php');

        $this->resetAfterTest();
        $this->setAdminUser();
        $this->set_router(false);
        $context = \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
        $draftitemid = ogimage_fixture::draft((int) $USER->id, ogimage_fixture::svg());

        $PAGE = new \moodle_page();
        $PAGE->set_category_by_id((int) $context->instanceid);
        $PAGE->set_url(new \moodle_url('/local/page/edit.php'));

        \pages_edit_product_form::mock_submit([
            'id' => 0,
            'contextid' => \local_page\local\scope::stored_contextid($context),
            'pagename' => 'Handbook',
            'menuname' => 'handbook',
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
            'ogimage_filemanager' => $draftitemid,
        ]);

        // No redirect: save_page() returns having written nothing.
        $PAGE->get_renderer('local_page')->save_page(custompage::load(0, true), $context);

        $this->assertSame(0, $DB->count_records('local_page', ['menuname' => 'handbook']));
        $this->assertSame(0, $DB->count_records_select('files', "component = 'local_page' AND filearea = 'ogimage'"));

        // Control: the same submission with the picture itself is saved, image included.
        $png = ogimage_fixture::png(4, 3);
        $pageid = $this->save($context, 0, 'handbook', ['ogimage_filemanager' => ogimage_fixture::draft((int) $USER->id, $png)]);
        $this->assertSame(sha1($png), ogimage::stored($context, $pageid)->get_contenthash());
    }
}
