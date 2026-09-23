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
 * Tests for the head tags the hook callback writes.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local\hook\output;

use local_page\local\links;
use local_page\local\request;
use local_page\output\opengraph;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');

/**
 * Tests for before_standard_head_html_generation, and for the registration that feeds it.
 *
 * Each test builds a real core hook around the core renderer and reads back what the callback
 * added, which is exactly what core_renderer::standard_head_html() does with it.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(before_standard_head_html_generation::class)]
#[CoversFunction('local_page_render_view')]
final class before_standard_head_html_generation_test extends \advanced_testcase {
    /**
     * Start with no tags registered.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        opengraph::set(null);
    }

    /**
     * Leave no tags registered for the next test: the registry is static.
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
     * Run the callback the way core does and return what it added.
     *
     * @return string The HTML added to the head
     */
    private function head(): string {
        global $PAGE;

        $PAGE->set_url('/local/page/index.php');
        $hook = new \core\hook\output\before_standard_head_html_generation($PAGE->get_renderer('core'));
        before_standard_head_html_generation::callback($hook);

        return $hook->get_output();
    }

    /**
     * Register the tags of a page at its own canonical address.
     *
     * @param object $page Row from {local_page}
     * @return void
     */
    private function register(object $page): void {
        opengraph::set(opengraph::for_page($page, links::page($page)));
    }

    /**
     * The name meta of one name, as written in the markup.
     *
     * @param string $html The head HTML
     * @param string $name The meta name
     * @return array The content attributes, still escaped
     */
    private function named(string $html, string $name): array {
        preg_match_all('/<meta name="' . preg_quote($name, '/') . '" content="([^"]*)">/', $html, $matches);

        return $matches[1];
    }

    /**
     * Nothing is added to the head of a page that registered no tags.
     *
     * @return void
     */
    public function test_nothing_is_added_unless_tags_were_registered(): void {
        $this->resetAfterTest();

        $this->assertSame('', $this->head());

        // Control: with a page's tags registered the same call writes them.
        $this->register($this->pages()->create_page(['pagename' => 'Handbook']));
        $this->assertStringContainsString('<meta property="og:title" content="Handbook">', $this->head());
    }

    /**
     * The callback is what db/hooks.php registers for the hook core dispatches.
     *
     * @return void
     */
    public function test_the_callback_is_registered_for_the_head_hook(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest();
        $this->register($this->pages()->create_page(['pagename' => 'Handbook']));

        $manager = \core\hook\manager::phpunit_get_instance(['local_page' => $CFG->dirroot . '/local/page/db/hooks.php']);
        $hook = new \core\hook\output\before_standard_head_html_generation($PAGE->get_renderer('core'));
        $manager->dispatch($hook);

        $this->assertStringContainsString('<meta property="og:title" content="Handbook">', $hook->get_output());
    }

    /**
     * The canonical link is written once, its address escaped once.
     *
     * @return void
     */
    public function test_the_canonical_link_is_an_escaped_literal(): void {
        $this->resetAfterTest();

        $page = $this->pages()->create_page();
        $canonical = new \moodle_url('/local/page/index.php', ['category' => 3, 'page' => 'handbook']);
        opengraph::set(opengraph::for_page($page, $canonical));

        $html = $this->head();
        $expected = '<link rel="canonical" href="' . s($canonical->out(false)) . '">';
        $this->assertStringContainsString('?category=3&amp;page=handbook', $expected, 'Precondition: it carries an ampersand.');
        $this->assertSame(1, substr_count($html, $expected));
        $this->assertSame(1, substr_count($html, 'rel="canonical"'));
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringContainsString('<meta property="og:url" content="' . s($canonical->out(false)) . '">', $html);
    }

    /**
     * The robots cases.
     *
     * @return array[] page kind, the page's own directive, $CFG->opentowebcrawlers, expected directive or null
     */
    public static function robots_provider(): array {
        return [
            'a site page with its own directive' => ['site', 'noindex, nofollow', 0, 'noindex, nofollow'],
            'a site page without one' => ['site', '', 0, null],
            'a category page without one, closed site' => ['category', '', 0, 'noindex'],
            'a category page without one, open site' => ['category', '', 1, null],
            'a category page with its own directive, closed site' => ['category', 'index, follow', 0, 'index, follow'],
        ];
    }

    /**
     * A page's robots directive is its own, else noindex for a category page on a site closed to
     * search engines, else none.
     *
     * @param string $kind 'site' or 'category'
     * @param string $metarobots The page's own directive
     * @param int $crawlers $CFG->opentowebcrawlers
     * @param string|null $expected The directive written, or null for no robots meta
     * @return void
     */
    #[DataProvider('robots_provider')]
    public function test_the_robots_directive(string $kind, string $metarobots, int $crawlers, ?string $expected): void {
        $this->resetAfterTest();
        set_config('opentowebcrawlers', $crawlers);

        $record = ['metarobots' => $metarobots];
        $page = $kind === 'category'
            ? $this->pages()->create_category_page((int) $this->getDataGenerator()->create_category()->id, $record)
            : $this->pages()->create_page($record);
        $this->register($page);

        $html = $this->head();
        $this->assertStringContainsString('rel="canonical"', $html, 'Control: the callback wrote the page\'s tags.');
        $this->assertSame($expected === null ? [] : [s($expected)], $this->named($html, 'robots'));
    }

    /**
     * The whole head carries one og:title, whether it comes from the meta title or the page name.
     *
     * Upstream wrote the meta title as a name meta called og:title and the page name again as the
     * real property: two titles, and the one a scraper reads was never the one written for it.
     *
     * @return void
     */
    public function test_the_head_carries_one_og_title(): void {
        $this->resetAfterTest();

        $this->register($this->pages()->create_page(['pagename' => 'Handbook', 'metatitle' => 'Volunteer handbook']));
        $html = $this->head();
        $this->assertSame(1, substr_count($html, 'og:title'));
        $this->assertStringContainsString('<meta property="og:title" content="Volunteer handbook">', $html);

        // Control: the page name stands in when there is no meta title, and still once.
        $this->register($this->pages()->create_page(['pagename' => 'Handbook']));
        $html = $this->head();
        $this->assertSame(1, substr_count($html, 'og:title'));
        $this->assertStringContainsString('<meta property="og:title" content="Handbook">', $html);
    }

    /**
     * The page's SEO metas are written once each, escaped once, and only when the page has them.
     *
     * @return void
     */
    public function test_the_seo_metas_are_written_once_and_escaped(): void {
        $this->resetAfterTest();

        $this->register($this->pages()->create_page([
            'metadescription' => 'Fees & dates',
            'metakeywords' => 'fire, rescue',
            'metaauthor' => "O'Brien & Co",
        ]));
        $html = $this->head();
        $this->assertSame(['Fees &amp; dates'], $this->named($html, 'description'));
        $this->assertSame(['fire, rescue'], $this->named($html, 'keywords'));
        $this->assertSame(['O&#039;Brien &amp; Co'], $this->named($html, 'author'));
        $this->assertStringNotContainsString('&amp;amp;', $html);

        // Control: a page without them writes none of them, while its other tags are there.
        $this->register($this->pages()->create_page());
        $bare = $this->head();
        $this->assertStringContainsString('rel="canonical"', $bare);
        $this->assertStringNotContainsString('<meta name=', $bare);
    }

    /**
     * A site page's own head HTML is appended as it was stored; a category page's never is.
     *
     * @return void
     */
    public function test_the_page_head_html_is_appended_raw_for_a_site_page_only(): void {
        $this->resetAfterTest();
        set_config('additionalhead', 1, 'local_page');

        $raw = '<script>window.localpage = "a & b";</script>';

        $this->register($this->pages()->create_page(['meta' => $raw]));
        $this->assertSame(1, substr_count($this->head(), $raw));

        $category = $this->getDataGenerator()->create_category();
        $this->register($this->pages()->create_category_page((int) $category->id, ['meta' => $raw]));
        $html = $this->head();
        $this->assertStringContainsString('rel="canonical"', $html, 'Control: the category page wrote its tags.');
        $this->assertStringNotContainsString('window.localpage', $html);
    }

    /**
     * A page withheld from the viewer puts nothing in the head; the same page published does.
     *
     * This goes through the viewer's own path: the request class decides, local_page_render_view()
     * renders, and the head is what the hook then writes. The site's own additional head HTML is left
     * exactly as it was either way — the plugin no longer writes to it.
     *
     * @return void
     */
    public function test_a_page_withheld_from_the_viewer_puts_nothing_in_the_head(): void {
        global $CFG, $DB, $PAGE;

        $this->resetAfterTest();
        $CFG->additionalhtmlhead = '<!-- the site\'s own -->';

        $page = $this->pages()->create_page([
            'status' => 'draft',
            'pagename' => 'Secret plans',
            'metadescription' => 'Not yet',
        ]);
        $this->setUser(null);

        $answer = request::legacy((int) $page->id, '');
        $this->assertFalse($answer->canview, 'Precondition: a visitor may not read a draft.');
        $this->assertSame((int) $page->id, (int) $answer->page->id, 'Precondition: the answer carries the row.');
        local_page_render_view($answer);
        $this->assertNull(opengraph::current());
        $this->assertSame('', $this->head());
        $this->assertSame('<!-- the site\'s own -->', $CFG->additionalhtmlhead);

        // Control: published, the very same page and path write its tags.
        $DB->set_field('local_page', 'status', 'live', ['id' => $page->id]);
        $PAGE = new \moodle_page();
        local_page_render_view(request::legacy((int) $page->id, ''));
        $this->assertStringContainsString('<meta property="og:title" content="Secret plans">', $this->head());
        $this->assertSame('<!-- the site\'s own -->', $CFG->additionalhtmlhead);
    }
}
