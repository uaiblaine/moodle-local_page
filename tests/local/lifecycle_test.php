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
 * Tests for what becomes of a category's pages when core deletes the category.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use local_page\tests\ogimage_fixture;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \local_page\local\lifecycle, called directly.
 *
 * These call the class the way the lib.php callbacks do, so each can assert what the class does
 * on its own — above all what it leaves alone. What core does around it (deleting the context,
 * recursing into the children) is asserted in tests/lib_test.php, which goes through
 * core_course_category::delete_full() and delete_move() for real.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(lifecycle::class)]
final class lifecycle_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * The stored row of one page.
     *
     * @param int $pageid Page id
     * @return \stdClass
     */
    private function row(int $pageid): \stdClass {
        global $DB;

        return $DB->get_record('local_page', ['id' => $pageid], '*', MUST_EXIST);
    }

    /**
     * The context id of a course category.
     *
     * @param int $categoryid Course category id
     * @return int
     */
    private function contextid(int $categoryid): int {
        return (int) \core\context\coursecat::instance($categoryid)->id;
    }

    /**
     * A public short code for a page, written straight into core's table.
     *
     * @param int $pageid Page id
     * @return void
     */
    private function seed_code(int $pageid): void {
        global $DB;

        $DB->insert_record('shortlink', (object) [
            'shortcode' => 'code' . $pageid,
            'userid' => 0,
            'component' => links::COMPONENT,
            'linktype' => links::LINKTYPE,
            'identifier' => (string) $pageid,
        ]);
    }

    /**
     * How many public short codes a page has.
     *
     * @param int $pageid Page id
     * @return int
     */
    private function codes(int $pageid): int {
        global $DB;

        return $DB->count_records('shortlink', ['component' => links::COMPONENT, 'identifier' => (string) $pageid]);
    }

    /**
     * How many files of one area a page holds in a context.
     *
     * @param int $contextid Context id
     * @param string $filearea File area
     * @param int $pageid Page id, the area's item id
     * @return int
     */
    private function files(int $contextid, string $filearea, int $pageid): int {
        return count(get_file_storage()->get_area_files($contextid, 'local_page', $filearea, $pageid, 'id', false));
    }

    /**
     * Store one file in a page's pagecontent area.
     *
     * @param int $contextid Context id
     * @param int $pageid Page id, the area's item id
     * @return void
     */
    private function store_body_file(int $contextid, int $pageid): void {
        get_file_storage()->create_file_from_string(
            [
                'contextid' => $contextid,
                'component' => 'local_page',
                'filearea' => 'pagecontent',
                'itemid' => $pageid,
                'filepath' => '/',
                'filename' => 'body.png',
            ],
            ogimage_fixture::png(2, 2)
        );
    }

    /**
     * A deleted category's own pages are soft-deleted and let go of their addresses; no one else's are.
     *
     * The page of the CHILD category stays live here, and that is the point: this class touches the
     * one context it is handed, and the children reach it through core's own recursion, which
     * tests/lib_test.php shows by deleting the parent through core.
     *
     * @return void
     */
    public function test_category_deleted_soft_deletes_the_categorys_own_pages_only(): void {
        $this->resetAfterTest();

        $category = (int) $this->getDataGenerator()->create_category()->id;
        $child = (int) $this->getDataGenerator()->create_category(['parent' => $category])->id;
        $sibling = (int) $this->getDataGenerator()->create_category()->id;

        $page = $this->pages()->create_category_page($category, ['menuname' => 'handbook']);
        $gone = $this->pages()->create_category_page($category, [
            'menuname' => 'old-deleted-1',
            'deleted' => 1,
        ]);
        $childpage = $this->pages()->create_category_page($child, ['menuname' => 'handbook']);
        $siblingpage = $this->pages()->create_category_page($sibling, ['menuname' => 'handbook']);
        $sitepage = $this->pages()->create_page(['menuname' => 'handbook']);
        $this->seed_code((int) $page->id);
        $this->seed_code((int) $siblingpage->id);

        // Precondition: the slug is held in the category before the deletion.
        $this->assertTrue(slug::is_taken('handbook', 0, $this->contextid($category)));

        lifecycle::category_deleted($category);

        $row = $this->row((int) $page->id);
        $this->assertSame(1, (int) $row->deleted, 'The page is soft-deleted.');
        $this->assertSame(slug::deleted_name('handbook', (int) $page->id), $row->menuname, 'Its slug is released.');
        $this->assertFalse(slug::is_taken('handbook', 0, $this->contextid($category)));
        $this->assertSame(0, $this->codes((int) $page->id), 'Its short code is gone.');
        $this->assertSame('old-deleted-1', $this->row((int) $gone->id)->menuname, 'A deleted row keeps the name it had.');

        // Controls: the child's, the sibling's and the site's pages are live, with their slugs and codes.
        foreach (['child' => $childpage, 'sibling' => $siblingpage, 'site' => $sitepage] as $label => $control) {
            $row = $this->row((int) $control->id);
            $this->assertSame(0, (int) $row->deleted, "{$label}: still live");
            $this->assertSame('handbook', $row->menuname, "{$label}: slug kept");
        }
        $this->assertSame(1, $this->codes((int) $siblingpage->id), 'The sibling keeps its short code.');
    }

    /**
     * A moved category's pages take their rows and both file areas to the new parent; no one else's move.
     *
     * The class is called directly here, so the old context still exists afterwards — core deletes it
     * once the callback returns — and the assertion that nothing is left behind in it is meaningful.
     *
     * @return void
     */
    public function test_category_moved_carries_rows_and_both_file_areas(): void {
        $this->resetAfterTest();

        $category = (int) $this->getDataGenerator()->create_category()->id;
        $target = (int) $this->getDataGenerator()->create_category()->id;
        $sibling = (int) $this->getDataGenerator()->create_category()->id;
        $old = $this->contextid($category);
        $new = $this->contextid($target);
        $siblingcontext = $this->contextid($sibling);

        $page = $this->pages()->create_category_page($category, ['menuname' => 'handbook']);
        $gone = $this->pages()->create_category_page($category, ['menuname' => 'old-deleted-9', 'deleted' => 1]);
        $siblingpage = $this->pages()->create_category_page($sibling, ['menuname' => 'handbook']);
        foreach ([[$old, $page], [$old, $gone], [$siblingcontext, $siblingpage]] as [$contextid, $row]) {
            ogimage_fixture::store(\core\context::instance_by_id($contextid), (int) $row->id, ogimage_fixture::png(4, 3));
            $this->store_body_file($contextid, (int) $row->id);
        }

        lifecycle::category_moved($category, $target);

        $row = $this->row((int) $page->id);
        $this->assertSame($new, (int) $row->contextid, 'The row names the new context.');
        $this->assertSame($target, (int) $row->categoryid, 'And the new category.');
        $this->assertSame('handbook', $row->menuname, 'A free slug is kept.');
        $this->assertSame(0, (int) $row->deleted);
        foreach (['pagecontent', 'ogimage'] as $filearea) {
            $this->assertSame(1, $this->files($new, $filearea, (int) $page->id), "{$filearea}: in the new context");
            $this->assertSame(0, $this->files($old, $filearea, (int) $page->id), "{$filearea}: nothing left behind");
        }

        // A deleted row moves with its files, so a restore by hand still finds them, and keeps its name.
        $row = $this->row((int) $gone->id);
        $this->assertSame($new, (int) $row->contextid);
        $this->assertSame(1, (int) $row->deleted);
        $this->assertSame('old-deleted-9', $row->menuname);
        $this->assertSame(1, $this->files($new, 'ogimage', (int) $gone->id));

        // Control: the sibling's page and files did not move.
        $row = $this->row((int) $siblingpage->id);
        $this->assertSame($siblingcontext, (int) $row->contextid);
        $this->assertSame($sibling, (int) $row->categoryid);
        foreach (['pagecontent', 'ogimage'] as $filearea) {
            $this->assertSame(1, $this->files($siblingcontext, $filearea, (int) $siblingpage->id), "sibling {$filearea}");
        }
    }

    /**
     * A page whose slug the new parent's live pages hold gains its id; every other page keeps its own.
     *
     * The page already there keeps its address. A slug the new parent holds only on a DELETED row is
     * free, because uniqueness binds live pages alone.
     *
     * @return void
     */
    public function test_a_moved_slug_the_new_parent_holds_gains_the_page_id(): void {
        global $DB;

        $this->resetAfterTest();

        $category = (int) $this->getDataGenerator()->create_category()->id;
        $target = (int) $this->getDataGenerator()->create_category()->id;
        $new = $this->contextid($target);

        $resident = $this->pages()->create_category_page($target, ['menuname' => 'contato']);
        $this->pages()->create_category_page($target, ['menuname' => 'faq', 'deleted' => 1]);
        $arriving = $this->pages()->create_category_page($category, ['menuname' => 'contato']);
        $free = $this->pages()->create_category_page($category, ['menuname' => 'sobre']);
        $revived = $this->pages()->create_category_page($category, ['menuname' => 'faq']);

        lifecycle::category_moved($category, $target);

        $this->assertSame('contato', $this->row((int) $resident->id)->menuname, 'The resident keeps its address.');
        $this->assertSame('contato-' . $arriving->id, $this->row((int) $arriving->id)->menuname, 'The arrival gains its id.');
        $this->assertSame('sobre', $this->row((int) $free->id)->menuname, 'A free slug is kept.');
        $this->assertSame('faq', $this->row((int) $revived->id)->menuname, 'A slug held only by a deleted row is free.');

        // Every live slug of the new context is held by exactly one page.
        foreach (['contato', 'contato-' . $arriving->id, 'sobre', 'faq'] as $slug) {
            $this->assertSame(
                1,
                $DB->count_records('local_page', ['contextid' => $new, 'menuname' => $slug, 'deleted' => 0]),
                "{$slug}: held by one live page"
            );
        }
    }

    /**
     * A move to the root is refused while the category holds a live page, before anything moves.
     *
     * The error is the one core's own web service gives for that move. The controls are a category
     * holding only a deleted page and an empty one, which are not this plugin's to refuse.
     *
     * @return void
     */
    public function test_a_move_to_the_root_is_refused_while_the_category_holds_a_live_page(): void {
        global $DB;

        $this->resetAfterTest();

        $category = (int) $this->getDataGenerator()->create_category()->id;
        $context = $this->contextid($category);
        $page = $this->pages()->create_category_page($category, ['menuname' => 'handbook']);
        ogimage_fixture::store(\core\context::instance_by_id($context), (int) $page->id, ogimage_fixture::png(4, 3));

        try {
            lifecycle::category_moved($category, 0);
            $this->fail('A move to the root must be refused.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('movecatcontentstoroot', $exception->errorcode);
            $this->assertSame('error', $exception->module);
        }

        $row = $this->row((int) $page->id);
        $this->assertSame($context, (int) $row->contextid, 'The page stays where it was.');
        $this->assertSame(0, (int) $row->deleted);
        $this->assertSame('handbook', $row->menuname);
        $this->assertSame(1, $this->files($context, 'ogimage', (int) $page->id), 'And so do its files.');

        // Controls: nothing to carry, nothing to refuse.
        $onlydeleted = (int) $this->getDataGenerator()->create_category()->id;
        $gone = $this->pages()->create_category_page($onlydeleted, ['menuname' => 'old-deleted-3', 'deleted' => 1]);
        lifecycle::category_moved($onlydeleted, 0);
        $this->assertSame($this->contextid($onlydeleted), (int) $this->row((int) $gone->id)->contextid);

        $empty = (int) $this->getDataGenerator()->create_category()->id;
        lifecycle::category_moved($empty, 0);
        $this->assertSame(0, $DB->count_records('local_page', ['contextid' => $this->contextid($empty)]));
    }
}
