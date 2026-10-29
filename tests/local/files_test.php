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
 * Tests for the pruning of drafts and the collection of orphan file areas.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A page's areas keep the files the page uses and nothing else.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(files::class)]
#[CoversClass(\local_page\task\collect_orphan_files::class)]
final class files_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * Put files in a new draft area of the current user.
     *
     * @param array $paths Paths relative to the area, such as 'a.png' or 'sub/c.png'
     * @return int The draft item id
     */
    private function draft(array $paths): int {
        global $USER;

        $draftitemid = file_get_unused_draft_itemid();
        foreach ($paths as $path) {
            $dir = dirname($path);
            get_file_storage()->create_file_from_string([
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => $dir === '.' ? '/' : '/' . $dir . '/',
                'filename' => basename($path),
            ], 'bytes of ' . $path);
        }
        return $draftitemid;
    }

    /**
     * The paths left in a draft area.
     *
     * @param int $draftitemid Draft item id
     * @return string[] Sorted paths relative to the area
     */
    private function draft_paths(int $draftitemid): array {
        global $USER;

        $files = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'id',
            false
        );
        $paths = array_values(array_map(
            static fn (\stored_file $file): string => ltrim($file->get_filepath() . $file->get_filename(), '/'),
            $files
        ));
        sort($paths);
        return $paths;
    }

    /**
     * Store one file in a page area.
     *
     * @param int $contextid Context id
     * @param string $filearea File area
     * @param int $itemid Item id
     * @param string $filename File name
     * @return void
     */
    private function store(int $contextid, string $filearea, int $itemid, string $filename): void {
        get_file_storage()->create_file_from_string([
            'contextid' => $contextid,
            'component' => 'local_page',
            'filearea' => $filearea,
            'itemid' => $itemid,
            'filepath' => '/',
            'filename' => $filename,
        ], 'bytes of ' . $filename);
    }

    /**
     * Whether an area holds any file.
     *
     * @param int $contextid Context id
     * @param string $filearea File area
     * @param int $itemid Item id
     * @return bool
     */
    private function holds_files(int $contextid, string $filearea, int $itemid): bool {
        return !get_file_storage()->is_area_empty($contextid, 'local_page', $filearea, $itemid);
    }

    /**
     * A draft keeps every file either field names, in every spelling, and loses the rest.
     *
     * @return void
     */
    public function test_prune_draft_keeps_the_files_either_field_names(): void {
        global $CFG, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $draftitemid = $this->draft([
            'a.png', 'b c.png', 'sub/c.png', 'd.png', 'e.h5p', 'f.png', 'g.png', 'a.png2',
            'h i.png', 'café.png', 'h.png',
        ]);
        $base = $CFG->wwwroot . '/draftfile.php/' . \context_user::instance($USER->id)->id . '/user/draft/' . $draftitemid . '/';
        $pageid = 42;

        $text = '<p><img src="' . $base . 'a.png" alt=""><img src="' . $base . 'b%20c.png" alt=""></p>'
            . '<iframe src="' . $CFG->wwwroot . '/h5p/embed.php?url=' . rawurlencode($base . 'e.h5p')
            . '&amp;component=x"></iframe>';
        $contenthtml = '<img src="@@PLUGINFILE@@/sub/c.png">'
            . '<a href="' . $CFG->wwwroot . '/pluginfile.php/1/local_page/pagecontent/' . $pageid . '/d.png">d</a>'
            . '<a href="' . $CFG->wwwroot . '/pluginfile.php/1/local_page/pagecontent/7/g.png">another page</a>'
            // Hand-written names: a raw space, an HTML entity, and a name only the start of which is a file.
            . '<img src="@@PLUGINFILE@@/h i.png"><img src="@@PLUGINFILE@@/caf&eacute;.png"><img src="@@PLUGINFILE@@/h.png2">';

        $this->assertSame(4, files::prune_draft($draftitemid, $text, $contenthtml, $pageid));
        $this->assertSame(
            ['a.png', 'b c.png', 'café.png', 'd.png', 'e.h5p', 'h i.png', 'sub/c.png'],
            $this->draft_paths($draftitemid)
        );
    }

    /**
     * A new page has no id, so only the editor's text and the placeholder keep a file.
     *
     * @return void
     */
    public function test_prune_draft_of_a_new_page_reads_no_page_address(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $draftitemid = $this->draft(['kept.png', 'gone.png']);
        $contenthtml = '<img src="@@PLUGINFILE@@/kept.png">'
            . '<img src="' . $CFG->wwwroot . '/pluginfile.php/1/local_page/pagecontent/0/gone.png">';

        $this->assertSame(1, files::prune_draft($draftitemid, '', $contenthtml, 0));
        $this->assertSame(['kept.png'], $this->draft_paths($draftitemid));
    }

    /**
     * Areas that name no page of their context are orphans; a page's own areas are not, in the bin or not.
     *
     * The dry run lists the same areas the run deletes, and deletes nothing.
     *
     * @return void
     */
    public function test_collect_orphans_deletes_only_areas_that_belong_to_no_page(): void {
        global $DB;

        $this->resetAfterTest();
        $system = \context_system::instance();
        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \core\context\coursecat::instance((int) $category->id);

        $sitepage = (int) $this->pages()->create_page()->id;
        $binned = (int) $this->pages()->create_page(['deleted' => 1, 'timedeleted' => time()])->id;
        $categorypage = (int) $this->pages()->create_category_page((int) $category->id)->id;
        $gone = (int) $this->pages()->create_page()->id;
        $DB->delete_records('local_page', ['id' => $gone]);

        // Owned: a site page's areas, a page in the bin, a category page in its own context.
        $this->store($system->id, 'pagecontent', $sitepage, 'own.png');
        $this->store($system->id, 'ogimage', $sitepage, 'og.png');
        $this->store($system->id, 'pagecontent', $binned, 'binned.png');
        $this->store($categorycontext->id, 'pagecontent', $categorypage, 'cat.png');
        // Orphans: the old shared area, a page that is gone, a page id in a context it does not live in.
        $this->store($system->id, 'pagecontent', 0, 'legacy.png');
        $this->store($system->id, 'ogimage', $gone, 'gone.png');
        $this->store($categorycontext->id, 'pagecontent', $sitepage, 'misplaced.png');
        $this->store($system->id, 'pagecontent', $categorypage, 'misplaced.png');

        $expected = [
            [$system->id, 'ogimage', $gone],
            [$system->id, 'pagecontent', 0],
            [$system->id, 'pagecontent', $categorypage],
            [$categorycontext->id, 'pagecontent', $sitepage],
        ];
        sort($expected);
        $keys = static function (array $areas): array {
            $keys = array_map(static fn (\stdClass $area): array => [$area->contextid, $area->filearea, $area->itemid], $areas);
            sort($keys);
            return $keys;
        };

        $listed = files::collect_orphans(false);
        $this->assertSame($expected, $keys($listed));
        $this->assertSame([1, 1, 1, 1], array_column($listed, 'files'), 'directory entries are not counted');
        $this->assertTrue($this->holds_files($system->id, 'pagecontent', 0), 'a dry run deletes nothing');

        $this->assertSame($expected, $keys(files::collect_orphans(true)));

        foreach ($expected as [$contextid, $filearea, $itemid]) {
            $this->assertFalse($this->holds_files($contextid, $filearea, $itemid), "$contextid/$filearea/$itemid");
        }
        $this->assertTrue($this->holds_files($system->id, 'pagecontent', $sitepage));
        $this->assertTrue($this->holds_files($system->id, 'ogimage', $sitepage));
        $this->assertTrue($this->holds_files($system->id, 'pagecontent', $binned), 'the bin keeps its files');
        $this->assertTrue($this->holds_files($categorycontext->id, 'pagecontent', $categorypage));

        $this->assertSame([], files::collect_orphans(true), 'nothing is left to collect');
    }

    /**
     * The weekly task runs the collection for real.
     *
     * @return void
     */
    public function test_the_task_deletes_the_orphan_areas(): void {
        $this->resetAfterTest();
        $system = \context_system::instance();
        $this->store($system->id, 'pagecontent', 0, 'legacy.png');

        $this->expectOutputRegex('/1 orphan file area\(s\) deleted, 1 file\(s\)/');
        (new \local_page\task\collect_orphan_files())->execute();

        $this->assertFalse($this->holds_files($system->id, 'pagecontent', 0));
    }
}
