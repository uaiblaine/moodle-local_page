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
 * Tests for where a page's embedded files are stored, read and served.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');

/**
 * Every page keeps its embedded files in an area of its own, item id = page id, in both contexts.
 *
 * The editor is opened and saved the way edit.php does it: the form's set_data() prepares the draft
 * area, moodleform::mock_submit() posts it back, and the renderer's save_page() stores it. save_page()
 * ends in redirect(), which under PHPUnit throws 'redirecterrordetected'; catching exactly that is
 * the proof the save reached its end.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\local_page_renderer::class)]
#[CoversClass(\pages_edit_product_form::class)]
#[CoversClass(custompage::class)]
#[CoversFunction('local_page_pluginfile')]
final class file_areas_test extends \advanced_testcase {
    /**
     * Point $PAGE at a context the way edit.php does.
     *
     * @param \core\context $context Context the editor works in
     * @return void
     */
    private function set_page(\core\context $context): void {
        global $PAGE;

        $PAGE = new \moodle_page();
        if ($context->contextlevel == CONTEXT_COURSECAT) {
            $PAGE->set_category_by_id((int) $context->instanceid);
        } else {
            $PAGE->set_context($context);
        }
        $PAGE->set_url(new \moodle_url('/local/page/edit.php'));
    }

    /**
     * Open the editor on a page, or on a new one, and return the draft area it prepared.
     *
     * @param \core\context $context Context the page belongs to
     * @param int $pageid Existing page id, or 0 for a new page
     * @return int The draft item id the form put in the editor
     */
    private function open_editor(\core\context $context, int $pageid): int {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/forms/edit.php');

        $this->set_page($context);
        $page = custompage::load($pageid, true);
        $form = new \pages_edit_product_form($pageid > 0 ? $page : false, $context);

        $defaults = new \stdClass();
        $defaults->id = $pageid;
        $defaults->pagecontent = ['text' => $pageid > 0 ? (string) $page->pagecontent : ''];
        $form->set_data($defaults);

        return (int) $defaults->pagecontent['itemid'];
    }

    /**
     * Add one file to a draft area, as an upload into the editor would.
     *
     * @param int $draftitemid Draft area
     * @param string $filename File name
     * @param string $content File content
     * @return void
     */
    private function upload(int $draftitemid, string $filename, string $content): void {
        global $USER;

        get_file_storage()->create_file_from_string(
            [
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => $filename,
            ],
            $content
        );
    }

    /**
     * The names of the files in a draft area.
     *
     * @param int $draftitemid Draft area
     * @return string[] Sorted file names
     */
    private function draft_names(int $draftitemid): array {
        global $USER;

        $files = get_file_storage()->get_area_files(
            \context_user::instance($USER->id)->id,
            'user',
            'draft',
            $draftitemid,
            'filename',
            false
        );
        $names = array_values(array_map(static fn (\stored_file $file): string => $file->get_filename(), $files));
        sort($names);
        return $names;
    }

    /**
     * Post the editor back with a draft area and a body, and save it the way edit.php does.
     *
     * @param \core\context $context Context the page belongs to
     * @param int $pageid Existing page id, or 0 for a new page
     * @param string $menuname Friendly URL
     * @param int $draftitemid Draft area the editor posts
     * @param string $text Body the editor posts
     * @return int The saved page's id
     */
    private function save(\core\context $context, int $pageid, string $menuname, int $draftitemid, string $text): int {
        global $CFG, $DB, $PAGE;
        require_once($CFG->dirroot . '/local/page/renderer.php');

        $this->set_page($context);
        \pages_edit_product_form::mock_submit([
            'id' => $pageid,
            'contextid' => \local_page\local\scope::stored_contextid($context),
            'pagename' => 'Handbook',
            'menuname' => $menuname,
            'status' => 'live',
            'onlyloggedin' => 0,
            'hidetitle' => 'no',
            'accesslevel' => '',
            'pagecontent' => ['text' => $text, 'format' => FORMAT_HTML, 'itemid' => $draftitemid],
            'contenthtml' => '',
            'metadescription' => '',
            'metakeywords' => '',
            'metaauthor' => '',
            'metatitle' => '',
            'metarobots' => '',
        ]);

        /*
         * The save reads the draft item id through file_get_submitted_draft_itemid(), which looks in
         * $_REQUEST, while mock_submit() fills $_POST only. It is removed again afterwards, because
         * set_data() reads the same key, and the next editor opened would reuse this draft.
         */
        $_REQUEST['pagecontent'] = $_POST['pagecontent'];
        try {
            $PAGE->get_renderer('local_page')->save_page(custompage::load($pageid, true), $context);
            $this->fail('save_page() ends in a redirect.');
        } catch (\moodle_exception $exception) {
            $this->assertSame('redirecterrordetected', $exception->errorcode, $exception->getMessage());
        } finally {
            unset($_REQUEST['pagecontent']);
        }

        $params = ['menuname' => $menuname, 'contextid' => \local_page\local\scope::stored_contextid($context)];
        return (int) $DB->get_field('local_page', 'id', $params, MUST_EXIST);
    }

    /**
     * Create a page through the editor, with one embedded file.
     *
     * @param \core\context $context Context the page belongs to
     * @param string $menuname Friendly URL
     * @param string $filename Embedded file's name
     * @return int The new page's id
     */
    private function create_with_file(\core\context $context, string $menuname, string $filename): int {
        $draft = $this->open_editor($context, 0);
        $this->upload($draft, $filename, 'bytes of ' . $filename);
        return $this->save($context, 0, $menuname, $draft, '<p><img src="@@PLUGINFILE@@/' . $filename . '"></p>');
    }

    /**
     * The names of the files stored in one page's own pagecontent area.
     *
     * @param \core\context $context Context the page belongs to
     * @param int $pageid Page id
     * @return string[] Sorted file names
     */
    private function area_names(\core\context $context, int $pageid): array {
        $files = get_file_storage()->get_area_files($context->id, 'local_page', 'pagecontent', $pageid, 'filename', false);
        $names = array_values(array_map(static fn (\stored_file $file): string => $file->get_filename(), $files));
        sort($names);
        return $names;
    }

    /**
     * Whether a file of that name is stored in the context's pagecontent area under any item id.
     *
     * @param \core\context $context Context to look in
     * @param string $filename File name
     * @return bool
     */
    private function stored_anywhere(\core\context $context, string $filename): bool {
        global $DB;

        return $DB->record_exists('files', [
            'contextid' => $context->id,
            'component' => 'local_page',
            'filearea' => 'pagecontent',
            'filename' => $filename,
        ]);
    }

    /**
     * Ask the file route for a pagecontent file without sending a byte.
     *
     * The If-None-Match header carries the content hash of the file the route would send, so a
     * file that IS sent answers 304 inside readfile_accel() and the call returns null; a refusal
     * returns false. See lib_test::ask_file_route(), which this mirrors.
     *
     * @param \core\context $context Context the file is asked through
     * @param array $args Item id, then the file name
     * @param string $content The bytes the route would send
     * @return bool|null False for a refusal, null for a file sent
     */
    private function ask_route(\core\context $context, array $args, string $content): ?bool {
        $_SERVER['HTTP_IF_NONE_MATCH'] = '"' . sha1($content) . '"';
        set_error_handler(
            static fn (int $errno, string $errstr): bool => str_starts_with($errstr, 'Cannot modify header information'),
            E_WARNING
        );
        try {
            return \local_page_pluginfile(null, null, $context, 'pagecontent', $args, false, ['dontdie' => true]);
        } finally {
            restore_error_handler();
            unset($_SERVER['HTTP_IF_NONE_MATCH']);
        }
    }

    /**
     * Two site pages edited at the same time keep each other's files.
     *
     * The editor of page A is opened before page B gains a file and is saved after. When every
     * site page shared item id 0, A's draft held the whole area as it was when A was opened, and
     * saving it made the area equal to that draft, deleting B's new file. The control is B's file
     * on record between the two saves, so the first assertion after A's save is about that save and
     * nothing else; both are asked of the whole pagecontent area of the context, whatever the item
     * id, so they read the same on either file layout. The last two pin the layout itself.
     *
     * @return void
     */
    public function test_saving_one_site_page_keeps_the_files_another_editor_just_added(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $system = \context_system::instance();

        $pagea = $this->create_with_file($system, 'page-a', 'a.png');
        $pageb = $this->create_with_file($system, 'page-b', 'b.png');

        // Editor 1 opens page A.
        $drafta = $this->open_editor($system, $pagea);

        // Editor 2 opens page B, adds a file and saves.
        $draftb = $this->open_editor($system, $pageb);
        $this->upload($draftb, 'c.png', 'bytes of c.png');
        $this->save($system, $pageb, 'page-b', $draftb, '<p><img src="@@PLUGINFILE@@/b.png"><img src="@@PLUGINFILE@@/c.png"></p>');
        $this->assertTrue($this->stored_anywhere($system, 'c.png'), 'control: B saved its new file');

        // Editor 1 saves page A from the draft opened before B's save.
        $this->save($system, $pagea, 'page-a', $drafta, '<p><img src="@@PLUGINFILE@@/a.png"></p>');

        $this->assertTrue($this->stored_anywhere($system, 'c.png'), "A's save kept B's new file");
        $this->assertSame(['a.png'], $this->area_names($system, $pagea));
        $this->assertSame(['b.png', 'c.png'], $this->area_names($system, $pageb), "A's save kept B's files");
    }

    /**
     * The editor of a page is handed that page's files and no other page's.
     *
     * A legacy file left under item id 0 stands for what the upgrade leaves behind: the editor of a
     * new page must not load it either, or every new page would carry those files into its own area.
     *
     * @param string $level 'system' or 'category'
     * @return void
     */
    #[DataProvider('levels')]
    public function test_the_editor_loads_only_the_pages_own_files(string $level): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $context = $level === 'system'
            ? \context_system::instance()
            : \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);

        get_file_storage()->create_file_from_string(
            [
                'contextid' => $context->id,
                'component' => 'local_page',
                'filearea' => 'pagecontent',
                'itemid' => 0,
                'filepath' => '/',
                'filename' => 'legacy.png',
            ],
            'legacy bytes'
        );

        $first = $this->create_with_file($context, 'first', 'first.png');
        $second = $this->create_with_file($context, 'second', 'second.png');

        $this->assertSame(['first.png'], $this->area_names($context, $first));
        $this->assertSame(['second.png'], $this->area_names($context, $second));
        $this->assertSame(['first.png'], $this->draft_names($this->open_editor($context, $first)));
        $this->assertSame([], $this->draft_names($this->open_editor($context, 0)), 'a new page starts empty');
    }

    /**
     * The two context levels a page lives at.
     *
     * @return array
     */
    public static function levels(): array {
        return [
            'site page' => ['system'],
            'category page' => ['category'],
        ];
    }

    /**
     * The viewer spells a site page's file URLs under the page's own id.
     *
     * @return void
     */
    public function test_the_viewer_spells_a_site_pages_files_under_its_own_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $system = \context_system::instance();

        $pageid = $this->create_with_file($system, 'handbook', 'body.png');
        $loaded = custompage::load($pageid);

        $this->assertStringContainsString(
            '/pluginfile.php/' . $system->id . '/local_page/pagecontent/' . $pageid . '/body.png',
            (string) $loaded->pagecontent
        );
    }

    /**
     * A site page's file is served through its own row, and only while the row may be read.
     *
     * Item id 0 is refused outright: no page has that id, and the shared area it named is gone.
     * Each refusal has the file really present where it was asked for, so none of them is a missing
     * file, and the first call is the control that the route does serve it.
     *
     * @return void
     */
    public function test_a_site_pages_file_is_served_through_its_own_row(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $system = \context_system::instance();
        $fs = get_file_storage();

        $pageid = $this->create_with_file($system, 'handbook', 'body.png');
        $fs->create_file_from_storedfile(
            ['itemid' => 0],
            $fs->get_file($system->id, 'local_page', 'pagecontent', $pageid, '/', 'body.png')
        );
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertNull($this->ask_route($system, [$pageid, 'body.png'], 'bytes of body.png'), 'control: served');
        $this->assertFalse($this->ask_route($system, [0, 'body.png'], 'bytes of body.png'), 'the legacy item id');

        $DB->set_field('local_page', 'status', 'draft', ['id' => $pageid]);
        $this->assertFalse($this->ask_route($system, [$pageid, 'body.png'], 'bytes of body.png'), 'a draft');

        $DB->set_field('local_page', 'status', 'live', ['id' => $pageid]);
        $DB->set_field('local_page', 'deleted', 1, ['id' => $pageid]);
        $this->setAdminUser();
        $this->assertFalse($this->ask_route($system, [$pageid, 'body.png'], 'bytes of body.png'), 'deleted, even for an admin');
    }

    /**
     * A category page's id does not open a site page's file, nor the other way round.
     *
     * Page ids are unique across the table, so the row lookup has to compare the context the file
     * is asked through with the page's own, in both directions.
     *
     * @return void
     */
    public function test_a_file_is_served_only_through_its_pages_own_context(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $system = \context_system::instance();
        $categorycontext = \core\context\coursecat::instance((int) $this->getDataGenerator()->create_category()->id);
        $fs = get_file_storage();

        $sitepage = $this->create_with_file($system, 'site', 'site.png');
        $categorypage = $this->create_with_file($categorycontext, 'cat', 'cat.png');

        // Each file also sits under the other page's id in the other context, so a refusal is never a missing file.
        $fs->create_file_from_storedfile(
            ['contextid' => $categorycontext->id],
            $fs->get_file($system->id, 'local_page', 'pagecontent', $sitepage, '/', 'site.png')
        );
        $fs->create_file_from_storedfile(
            ['contextid' => $system->id],
            $fs->get_file($categorycontext->id, 'local_page', 'pagecontent', $categorypage, '/', 'cat.png')
        );

        $this->assertNull($this->ask_route($system, [$sitepage, 'site.png'], 'bytes of site.png'), 'control: site');
        $this->assertNull($this->ask_route($categorycontext, [$categorypage, 'cat.png'], 'bytes of cat.png'), 'control: cat');
        $this->assertFalse($this->ask_route($categorycontext, [$sitepage, 'site.png'], 'bytes of site.png'));
        $this->assertFalse($this->ask_route($system, [$categorypage, 'cat.png'], 'bytes of cat.png'));
    }
}
