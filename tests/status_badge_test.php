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
 * Tests for the status badge a page's editors see beside its heading.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use local_page\local\request;
use local_page\output\opengraph;
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');

/**
 * Tests for local_page_status_badge() and the heading local_page_render_view() builds with it.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_page_status_badge')]
#[CoversFunction('local_page_render_view')]
final class status_badge_test extends \advanced_testcase {
    /**
     * Leave no head tags registered for the next test: local_page_render_view() registers them and
     * the registry is static.
     *
     * @return void
     */
    protected function tearDown(): void {
        opengraph::set(null);
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
     * The badge markup for one label and one pair of colour classes.
     *
     * @param string $label The status text, plain
     * @param string $classes The background and text colour utilities
     * @return string
     */
    private function badge(string $label, string $classes): string {
        return '<span class="badge ' . $classes . ' ms-1 align-middle local-page-status-badge">' . s($label) . '</span>';
    }

    /**
     * Render a page through the viewer's own path, as the current user, on a fresh $PAGE.
     *
     * @param int $pageid The page's id
     * @return \moodle_page The page the heading and the body classes were set on
     */
    private function render(int $pageid): \moodle_page {
        global $PAGE;

        $PAGE = new \moodle_page();
        local_page_render_view(request::legacy($pageid, ''));

        return $PAGE;
    }

    /**
     * Somebody who may edit the page sees its status beside its name; a reader sees the name alone.
     *
     * The name carries a bare ampersand, the fixture that shows it is escaped exactly once: the
     * heading is no longer formatted by set_heading(), so the name is formatted before the badge is
     * added.
     *
     * @return void
     */
    public function test_the_heading_carries_the_status_for_an_editor_only(): void {
        $this->resetAfterTest();
        $page = $this->pages()->create_page(['pagename' => 'Fire & Rescue']);

        // A logged-in reader who may not edit the page reads its name alone.
        $this->setUser($this->getDataGenerator()->create_user());
        $reader = $this->render((int) $page->id);
        $this->assertSame('Fire &amp; Rescue', $reader->heading);
        $this->assertStringNotContainsString('local-page-status-live', $reader->bodyclasses);
        $this->assertStringContainsString('local-page-id-' . $page->id, $reader->bodyclasses);

        // The administrator sees the badge, in the language's own wording.
        $this->setAdminUser();
        $editor = $this->render((int) $page->id);
        $this->assertSame(
            'Fire &amp; Rescue ' . $this->badge(get_string('status_live', 'local_page'), 'bg-success-subtle text-success-emphasis'),
            $editor->heading
        );
        $this->assertStringContainsString('local-page-status-live', $editor->bodyclasses);
        $this->assertStringContainsString('local-page-id-' . $page->id, $editor->bodyclasses);
    }

    /**
     * A page whose title is hidden gets no heading, and so no badge, whoever reads it.
     *
     * @return void
     */
    public function test_a_hidden_title_carries_no_badge(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $page = $this->pages()->create_page(['hidetitle' => 'yes']);

        $this->assertSame('', $this->render((int) $page->id)->heading);
    }

    /**
     * Each status has its own string and colour pair, and a status the plugin does not know has no badge.
     *
     * @return void
     */
    public function test_each_status_has_its_own_badge(): void {
        $this->resetAfterTest();

        $this->assertSame(
            $this->badge(get_string('status_live', 'local_page'), 'bg-success-subtle text-success-emphasis'),
            local_page_status_badge('live')
        );
        $this->assertSame(
            $this->badge(get_string('status_draft', 'local_page'), 'bg-warning-subtle text-warning-emphasis'),
            local_page_status_badge('draft')
        );
        $this->assertSame(
            $this->badge(get_string('status_archived', 'local_page'), 'bg-danger-subtle text-danger-emphasis'),
            local_page_status_badge('archived')
        );
        $this->assertSame('', local_page_status_badge('published'));
    }

    /**
     * The badge reads the site's own wording of the status strings, not a fixed English word.
     *
     * The strings are customised the way the language customisation tool stores them, in the
     * en_local pack of the test site's data directory. Under the stock English strings a badge that
     * hard-coded "Live" would read the same as one that asked for the string; the customised wording
     * tells them apart.
     *
     * @return void
     */
    public function test_the_badge_reads_the_sites_own_wording(): void {
        global $CFG;

        $this->resetAfterTest();
        if (strpos($CFG->langlocalroot . '/', $CFG->dataroot . '/') !== 0) {
            $this->markTestSkipped('The site keeps its language customisations outside the test data directory.');
        }

        $dir = $CFG->langlocalroot . '/en_local';
        check_dir_exists($dir);
        $file = $dir . '/local_page.php';
        file_put_contents(
            $file,
            "<?php\n\$string['status_archived'] = 'Put away';\n\$string['status_draft'] = 'Being written';\n" .
                "\$string['status_live'] = 'On air';\n"
        );
        get_string_manager()->reset_caches(true);

        try {
            // Precondition: the customisation is what get_string() answers.
            $this->assertSame('On air', get_string('status_live', 'local_page'));

            $this->assertSame(
                $this->badge('On air', 'bg-success-subtle text-success-emphasis'),
                local_page_status_badge('live')
            );
            $this->assertSame(
                $this->badge('Being written', 'bg-warning-subtle text-warning-emphasis'),
                local_page_status_badge('draft')
            );
            $this->assertSame(
                $this->badge('Put away', 'bg-danger-subtle text-danger-emphasis'),
                local_page_status_badge('archived')
            );
        } finally {
            unlink($file);
            get_string_manager()->reset_caches(true);
        }
    }
}
