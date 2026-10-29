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
 * Tests for the recycle bin.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Deleting, restoring and permanently deleting a page, and the retention.
 *
 * Each refusal is paired with a control that the same call acts on a page it may act on, so a
 * method that had stopped doing anything would not pass the refusals.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(bin::class)]
#[CoversClass(\local_page\task\purge_deleted_pages::class)]
#[CoversClass(\local_page\event\page_deleted::class)]
#[CoversClass(\local_page\event\page_restored::class)]
#[CoversClass(\local_page\event\page_purged::class)]
final class bin_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A course category's context.
     *
     * @return \core\context\coursecat
     */
    private function category_context(): \core\context\coursecat {
        return \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
    }

    /**
     * A page in a context, with one file in each of its areas and a public short code.
     *
     * @param \core\context $context Context the page belongs to
     * @param array $record Fields to set
     * @return int Page id
     */
    private function page_with_files(\core\context $context, array $record = []): int {
        if ($context->contextlevel == CONTEXT_COURSECAT) {
            $page = $this->pages()->create_category_page((int) $context->instanceid, $record);
        } else {
            $page = $this->pages()->create_page($record);
        }
        $pageid = (int) $page->id;

        $fs = get_file_storage();
        foreach (['pagecontent', 'ogimage'] as $filearea) {
            $fs->create_file_from_string([
                'contextid' => $context->id,
                'component' => 'local_page',
                'filearea' => $filearea,
                'itemid' => $pageid,
                'filepath' => '/',
                'filename' => $filearea . '.png',
            ], 'bytes');
        }
        $this->seed_code($pageid);

        return $pageid;
    }

    /**
     * Give a page a public short code, as links::share() would, without building a router.
     *
     * @param int $pageid Page id
     * @return void
     */
    private function seed_code(int $pageid): void {
        global $DB;

        $DB->insert_record('shortlink', [
            'shortcode' => random_string(12),
            'userid' => 0,
            'component' => links::COMPONENT,
            'linktype' => links::LINKTYPE,
            'identifier' => (string) $pageid,
        ]);
    }

    /**
     * How many files a page holds across both areas.
     *
     * @param \core\context $context The page's context
     * @param int $pageid Page id
     * @return int
     */
    private function file_count(\core\context $context, int $pageid): int {
        $fs = get_file_storage();
        $count = 0;
        foreach (['pagecontent', 'ogimage'] as $filearea) {
            $count += count($fs->get_area_files($context->id, 'local_page', $filearea, $pageid, 'id', false));
        }
        return $count;
    }

    /**
     * The stored row of a page, or false once it is gone.
     *
     * @param int $pageid Page id
     * @return \stdClass|false
     */
    private function row(int $pageid) {
        global $DB;

        return $DB->get_record('local_page', ['id' => $pageid]);
    }

    /**
     * Deleting moves a page to the bin: flag, time, released slug; files and short code stay.
     *
     * @return void
     */
    public function test_delete_moves_a_page_to_the_bin_and_keeps_its_files_and_code(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $context = $this->category_context();
        $pageid = $this->page_with_files($context, ['menuname' => 'contact']);

        $sink = $this->redirectEvents();
        $before = time();
        $this->assertTrue(bin::delete($pageid, $context));
        $events = $sink->get_events();
        $sink->close();

        $row = $this->row($pageid);
        $this->assertSame(1, (int) $row->deleted);
        $this->assertGreaterThanOrEqual($before, (int) $row->timedeleted);
        $this->assertSame('contact-deleted-' . $pageid, $row->menuname);
        $this->assertSame(2, $this->file_count($context, $pageid));
        $this->assertTrue($DB->record_exists('shortlink', ['component' => 'local_page', 'identifier' => (string) $pageid]));
        $this->assertSame(1, bin::count($context));

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_page\event\page_deleted::class, $events[0]);
        $this->assertSame($pageid, (int) $events[0]->objectid);
        $this->assertSame((int) $context->id, (int) $events[0]->contextid);
    }

    /**
     * Deleting refuses a page of another context and a page already in the bin.
     *
     * @return void
     */
    public function test_delete_acts_only_on_a_live_page_of_its_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $system = \context_system::instance();
        $context = $this->category_context();
        $sitepage = $this->page_with_files($system);
        $categorypage = $this->page_with_files($context);

        $this->assertFalse(bin::delete($sitepage, $context), 'a site page through a category');
        $this->assertFalse(bin::delete($categorypage, $system), 'a category page through the site');
        $this->assertSame(0, (int) $this->row($sitepage)->deleted);
        $this->assertSame(0, (int) $this->row($categorypage)->deleted);

        // Control: each through its own context.
        $this->assertTrue(bin::delete($sitepage, $system));
        $this->assertTrue(bin::delete($categorypage, $context));

        $timedeleted = (int) $this->row($categorypage)->timedeleted;
        $this->assertFalse(bin::delete($categorypage, $context), 'already in the bin');
        $this->assertSame($timedeleted, (int) $this->row($categorypage)->timedeleted);
    }

    /**
     * Restoring gives a page back its slug, its files and its short code.
     *
     * @return void
     */
    public function test_restore_gives_back_the_old_slug(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $context = $this->category_context();
        $pageid = $this->page_with_files($context, ['menuname' => 'contact', 'pagename' => 'Contact']);
        bin::delete($pageid, $context);

        $sink = $this->redirectEvents();
        $restored = bin::restore($pageid, $context);
        $events = $sink->get_events();
        $sink->close();

        $this->assertSame('contact', $restored->menuname);
        $this->assertFalse($restored->renamed);
        $this->assertSame('Contact', $restored->name);

        $row = $this->row($pageid);
        $this->assertSame(0, (int) $row->deleted);
        $this->assertSame(0, (int) $row->timedeleted);
        $this->assertSame('contact', $row->menuname);
        $this->assertSame(2, $this->file_count($context, $pageid));
        $this->assertTrue($DB->record_exists('shortlink', ['component' => 'local_page', 'identifier' => (string) $pageid]));

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_page\event\page_restored::class, $events[0]);
        $this->assertSame($pageid, (int) $events[0]->objectid);
    }

    /**
     * A slug another live page took meanwhile stays with that page; the restored one gains its id.
     *
     * @return void
     */
    public function test_restore_leaves_a_taken_slug_to_the_page_that_took_it(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = $this->category_context();
        $pageid = $this->page_with_files($context, ['menuname' => 'contact']);
        bin::delete($pageid, $context);
        $successor = $this->page_with_files($context, ['menuname' => 'contact']);

        $restored = bin::restore($pageid, $context);

        $this->assertTrue($restored->renamed);
        $this->assertSame('contact-' . $pageid, $restored->menuname);
        $this->assertSame('contact-' . $pageid, $this->row($pageid)->menuname);
        $this->assertSame('contact', $this->row($successor)->menuname);
    }

    /**
     * Restoring refuses a page of another context and a live page.
     *
     * @return void
     */
    public function test_restore_acts_only_on_a_page_in_its_contexts_bin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $system = \context_system::instance();
        $context = $this->category_context();
        $sitepage = $this->page_with_files($system);
        $categorypage = $this->page_with_files($context);
        bin::delete($sitepage, $system);

        $refused = [
            [$sitepage, $context, 'through another context'],
            [$categorypage, $context, 'a live page'],
        ];
        foreach ($refused as [$id, $ctx, $why]) {
            try {
                bin::restore($id, $ctx);
                $this->fail('Restored ' . $why);
            } catch (\moodle_exception $exception) {
                $this->assertSame('pagenotfound', $exception->errorcode, $why);
            }
        }
        $this->assertSame(1, (int) $this->row($sitepage)->deleted);

        // Control: through its own context.
        bin::restore($sitepage, $system);
        $this->assertSame(0, (int) $this->row($sitepage)->deleted);
    }

    /**
     * Deleting permanently removes the row, both file areas and the short code, and only from the bin.
     *
     * @return void
     */
    public function test_purge_removes_the_row_its_files_and_its_code(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $system = \context_system::instance();
        $context = $this->category_context();
        $pageid = $this->page_with_files($context);
        $live = $this->page_with_files($context);
        $sitepage = $this->page_with_files($system);
        bin::delete($pageid, $context);
        bin::delete($sitepage, $system);

        $this->assertFalse(bin::purge($live, $context), 'a live page');
        $this->assertFalse(bin::purge($sitepage, $context), 'a page of another context');
        $this->assertNotFalse($this->row($live));
        $this->assertNotFalse($this->row($sitepage));
        $this->assertSame(2, $this->file_count($system, $sitepage));

        $sink = $this->redirectEvents();
        $this->assertTrue(bin::purge($pageid, $context));
        $events = $sink->get_events();
        $sink->close();

        $this->assertFalse($this->row($pageid));
        $this->assertSame(0, $this->file_count($context, $pageid));
        $this->assertFalse($DB->record_exists('shortlink', ['component' => 'local_page', 'identifier' => (string) $pageid]));
        $this->assertSame(2, $this->file_count($context, $live), "the live page's files stay");

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\local_page\event\page_purged::class, $events[0]);
        $this->assertSame($pageid, (int) $events[0]->objectid);
        $this->assertSame((int) $context->id, (int) $events[0]->contextid);
    }

    /**
     * Emptying a bin deletes that bin's pages and nothing else.
     *
     * @return void
     */
    public function test_empty_deletes_only_the_pages_in_that_contexts_bin(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $system = \context_system::instance();
        $context = $this->category_context();
        $first = $this->page_with_files($context);
        $second = $this->page_with_files($context);
        $live = $this->page_with_files($context);
        $sitepage = $this->page_with_files($system);
        bin::delete($first, $context);
        bin::delete($second, $context);
        bin::delete($sitepage, $system);

        $this->assertSame([$second, $first], array_map('intval', array_keys(bin::items($context))));

        $this->assertSame(2, bin::empty($context));

        $this->assertFalse($this->row($first));
        $this->assertFalse($this->row($second));
        $this->assertNotFalse($this->row($live));
        $this->assertNotFalse($this->row($sitepage), "the site's bin is another bin");
        $this->assertSame(0, bin::count($context));
        $this->assertSame(1, bin::count($system));
    }

    /**
     * The retention deletes a page once its time is over, and a retention of 0 keeps every page.
     *
     * @return void
     */
    public function test_purge_expired_follows_the_retention(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $context = $this->category_context();
        $now = time();
        $old = $this->page_with_files($context);
        $recent = $this->page_with_files($context);
        $live = $this->page_with_files($context);
        bin::delete($old, $context);
        bin::delete($recent, $context);
        $DB->set_field('local_page', 'timedeleted', $now - 8 * DAYSECS, ['id' => $old]);
        $DB->set_field('local_page', 'timedeleted', $now - 6 * DAYSECS, ['id' => $recent]);

        set_config('binexpiry', 0, 'local_page');
        $this->assertSame(0, bin::purge_expired($now), '0 keeps every page');
        $this->assertNotFalse($this->row($old));

        set_config('binexpiry', 7 * DAYSECS, 'local_page');
        $this->assertSame(1, bin::purge_expired($now));
        $this->assertFalse($this->row($old));
        $this->assertSame(0, $this->file_count($context, $old));
        $this->assertNotFalse($this->row($recent), 'six days is inside seven');
        $this->assertNotFalse($this->row($live), 'a live page is never in the bin');

        $this->assertSame(7 * DAYSECS + $now - 6 * DAYSECS, bin::purge_time($now - 6 * DAYSECS));
    }

    /**
     * Before the setting is ever saved, the retention is seven days.
     *
     * @return void
     */
    public function test_the_retention_defaults_to_seven_days(): void {
        $this->resetAfterTest();

        unset_config('binexpiry', 'local_page');
        $this->assertSame(7 * DAYSECS, bin::expiry());

        set_config('binexpiry', 0, 'local_page');
        $this->assertSame(0, bin::expiry());
        $this->assertNull(bin::purge_time(time()), 'nothing removes a page');
    }

    /**
     * A deleted page whose context is gone is deleted at once, whatever the retention.
     *
     * Its category was deleted with the context, so it can never be restored. A live row under a
     * missing context is left alone: it is not in any bin.
     *
     * @return void
     */
    public function test_purge_expired_removes_pages_whose_context_is_gone(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $context = $this->category_context();
        $orphan = $this->page_with_files($context);
        $control = $this->page_with_files($context);
        $DB->set_field('local_page', 'deleted', 1, ['id' => $orphan]);
        $DB->set_field('local_page', 'timedeleted', time(), ['id' => $orphan]);
        $DB->set_field('local_page', 'contextid', 999999999, ['id' => $orphan]);
        $DB->set_field('local_page', 'contextid', 999999998, ['id' => $control]);

        set_config('binexpiry', 0, 'local_page');
        $this->assertSame(1, bin::purge_expired(time()));

        $this->assertFalse($this->row($orphan));
        $this->assertFalse($DB->record_exists('shortlink', ['component' => 'local_page', 'identifier' => (string) $orphan]));
        $this->assertNotFalse($this->row($control), 'a live row is not in the bin');
    }

    /**
     * The scheduled task runs the retention against the current time.
     *
     * @return void
     */
    public function test_the_task_deletes_the_pages_whose_time_is_over(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $context = $this->category_context();
        $old = $this->page_with_files($context);
        $recent = $this->page_with_files($context);
        bin::delete($old, $context);
        bin::delete($recent, $context);
        $DB->set_field('local_page', 'timedeleted', time() - 8 * DAYSECS, ['id' => $old]);
        set_config('binexpiry', 7 * DAYSECS, 'local_page');

        $this->expectOutputRegex('/1 page\(s\) deleted permanently/');
        (new \local_page\task\purge_deleted_pages())->execute();

        $this->assertFalse($this->row($old));
        $this->assertNotFalse($this->row($recent));
    }
}
