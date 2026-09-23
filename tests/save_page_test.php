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
 * Tests for the save path of the page editor.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/renderer.php');

/**
 * Tests for local_page_renderer::save_page().
 *
 * The form is submitted with moodleform::mock_submit() and saved through the renderer, the way edit.php
 * saves it. A successful save ends in redirect(), which a CLI process answers by throwing
 * 'redirecterrordetected'; the tests catch exactly that, which is also the proof the save reached its end.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_page_renderer::class)]
final class save_page_test extends \advanced_testcase {
    /**
     * The plugin's data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * Submit the edit form for a page and save it through the renderer.
     *
     * @param int $pageid Page id posted in the hidden field, or 0 for a new page
     * @param array $fields Submitted values overriding the defaults
     * @return void
     */
    private function save(int $pageid, array $fields = []): void {
        global $PAGE;

        $PAGE = new \moodle_page();
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/local/page/edit.php'));

        \pages_edit_product_form::mock_submit($fields + [
            'id' => $pageid,
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
        ]);

        $PAGE->get_renderer('local_page')->save_page(custompage::load($pageid, true));
    }

    /**
     * Save, and assert the save reached its closing redirect.
     *
     * @param int $pageid Page id posted in the hidden field, or 0 for a new page
     * @param array $fields Submitted values overriding the defaults
     * @return void
     */
    private function save_to_the_end(int $pageid, array $fields = []): void {
        try {
            $this->save($pageid, $fields);
            $this->fail('save_page() ends in a redirect to the editor.');
        } catch (\moodle_exception $e) {
            $this->assertSame('redirecterrordetected', $e->errorcode, $e->getMessage());
        }
    }

    /**
     * Put a PNG in the current user's draft area, the way the file manager leaves an upload.
     *
     * @return int The draft item id, which is what the form posts for the file manager
     */
    private function draft_png(): int {
        global $USER;

        $image = imagecreatetruecolor(4, 3);
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(
            [
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => 'og.png',
            ],
            $png
        );

        return $draftitemid;
    }

    /**
     * Saving an existing page writes to the row the posted id names.
     *
     * @return void
     */
    public function test_a_save_writes_to_the_page_it_names(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page(['pagename' => 'Old name', 'menuname' => 'handbook']);

        $this->save_to_the_end($page->id, ['pagename' => 'New name', 'ogimage_filemanager' => $this->draft_png()]);

        $this->assertSame('New name', $DB->get_field('local_page', 'pagename', ['id' => $page->id]));
        $context = \context_system::instance();
        $files = get_file_storage()->get_area_files($context->id, 'local_page', 'ogimage', $page->id, 'id', false);
        $this->assertCount(1, $files, 'The image is stored under the page id.');
    }

    /**
     * A posted id naming a deleted page, or no page, is refused before anything is written.
     *
     * Without the check the deleted row was updated in place, and an id naming no row still ran the
     * update (a no-op) and then stored the Open Graph image under that id, for whichever page is
     * created with it next. The previous test is the control: the same save reaches its end for a
     * live page.
     *
     * @return void
     */
    public function test_a_save_to_a_deleted_or_missing_page_is_refused(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $deleted = $this->pages()->create_page(['pagename' => 'Old name', 'deleted' => 1]);
        $missingid = $deleted->id + 1000;

        foreach (['deleted' => $deleted->id, 'missing' => $missingid] as $label => $id) {
            try {
                $this->save($id, ['pagename' => 'New name', 'ogimage_filemanager' => $this->draft_png()]);
                $this->fail("{$label}: expected an exception");
            } catch (\moodle_exception $e) {
                $this->assertSame('pagenotfound', $e->errorcode, $label);
            }

            $files = get_file_storage()->get_area_files(\context_system::instance()->id, 'local_page', 'ogimage', $id, 'id', false);
            $this->assertSame([], $files, "{$label}: no image stored under the id");
        }

        $this->assertSame('Old name', $DB->get_field('local_page', 'pagename', ['id' => $deleted->id]));
        $this->assertFalse($DB->record_exists('local_page', ['id' => $missingid]));
    }
}
