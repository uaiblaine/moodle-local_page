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
 * Tests for the Open Graph tags of a page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\output;

use local_page\local\links;
use local_page\local\ogimage;
use local_page\tests\ogimage_fixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for opengraph and the image it advertises.
 *
 * Every assertion is made on the RENDERED markup, and on the property attribute exactly: a tag
 * spelled name="og:title" is not an Open Graph tag, and a scraper ignores it, so a test reading the
 * exported values alone would pass over the very defect upstream shipped.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(opengraph::class)]
#[CoversClass(ogimage::class)]
final class opengraph_test extends \advanced_testcase {
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
     * The tags of a page, rendered the way the hook callback renders them.
     *
     * @param object $page Row from {local_page}
     * @return string The HTML
     */
    private function render(object $page): string {
        global $PAGE;

        $PAGE->set_url('/local/page/index.php');

        return $PAGE->get_renderer('core')->render(opengraph::for_page($page, links::page($page)));
    }

    /**
     * The contents of every Open Graph meta of one property, as written in the markup.
     *
     * @param string $html The rendered tags
     * @param string $property The property, e.g. og:title
     * @return array The content attributes, still escaped
     */
    private function contents(string $html, string $property): array {
        preg_match_all('/<meta property="' . preg_quote($property, '/') . '" content="([^"]*)">/', $html, $matches);

        return $matches[1];
    }

    /**
     * A bare ampersand in a page name is escaped exactly once.
     *
     * @return void
     */
    public function test_a_bare_ampersand_is_escaped_exactly_once(): void {
        $this->resetAfterTest();

        $html = $this->render($this->pages()->create_page(['pagename' => 'Fire & Rescue']));

        $this->assertSame(['Fire &amp; Rescue'], $this->contents($html, 'og:title'));
        $this->assertStringNotContainsString('&amp;amp;', $html);
        $this->assertStringNotContainsString('Fire & Rescue', $html, 'Never unescaped either.');
    }

    /**
     * A name typed with an entity in it is shown as it was typed.
     *
     * format_string() with escape off leaves an existing entity alone, so the plain spelling IS what
     * the author typed and the template escapes it once, exactly as upstream's html_writer did: a
     * reader of the preview sees the characters that were typed, entity and all.
     *
     * @return void
     */
    public function test_a_name_typed_with_an_entity_is_shown_as_typed(): void {
        global $PAGE;

        $this->resetAfterTest();

        $page = $this->pages()->create_page(['pagename' => 'Fire &amp; Rescue']);

        $exported = opengraph::for_page($page, links::page($page))->export_for_template($PAGE->get_renderer('core'));
        $this->assertSame('Fire &amp; Rescue', $exported->title, 'The plain spelling is what was typed.');
        $this->assertSame(['Fire &amp;amp; Rescue'], $this->contents($this->render($page), 'og:title'));
    }

    /**
     * There is one og:title: the meta title when the page has one, its name otherwise.
     *
     * @return void
     */
    public function test_there_is_one_og_title_and_the_meta_title_comes_first(): void {
        $this->resetAfterTest();

        $titled = $this->render($this->pages()->create_page(['pagename' => 'Handbook', 'metatitle' => 'Volunteer handbook']));
        $this->assertSame(['Volunteer handbook'], $this->contents($titled, 'og:title'));
        $this->assertSame(1, substr_count($titled, 'og:title'), 'One og:title, and never a name meta of that name.');
        $this->assertStringNotContainsString('"Handbook"', $titled);

        // Control: without a meta title the page name is the title, still once.
        $untitled = $this->render($this->pages()->create_page(['pagename' => 'Handbook', 'metatitle' => '  ']));
        $this->assertSame(['Handbook'], $this->contents($untitled, 'og:title'));
        $this->assertSame(1, substr_count($untitled, 'og:title'));
    }

    /**
     * og:description is written only when the page has a meta description.
     *
     * @return void
     */
    public function test_the_description_is_written_only_when_there_is_one(): void {
        $this->resetAfterTest();

        $without = $this->render($this->pages()->create_page(['metadescription' => '   ']));
        $this->assertStringNotContainsString('og:description', $without);

        // Control: the same page shape with a description writes it, escaped once.
        $with = $this->render($this->pages()->create_page(['metadescription' => ' Fees & dates ']));
        $this->assertSame(['Fees &amp; dates'], $this->contents($with, 'og:description'));
    }

    /**
     * Moodle language codes and their Open Graph spelling.
     *
     * @return array[] language, expected locale
     */
    public static function locale_provider(): array {
        return [
            'territory upper-cased' => ['pt_br', 'pt_BR'],
            'English stays as it is' => ['en', 'en'],
            'another territory' => ['en_us', 'en_US'],
            'three-letter language' => ['ast_es', 'ast_ES'],
            'a variant is passed through' => ['ca_valencia', 'ca_valencia'],
        ];
    }

    /**
     * The Open Graph locale is the language with its territory upper-cased.
     *
     * @param string $language Moodle language code
     * @param string $expected Open Graph locale
     * @return void
     */
    #[DataProvider('locale_provider')]
    public function test_locale(string $language, string $expected): void {
        $this->assertSame($expected, opengraph::locale($language));
    }

    /**
     * The og:locale follows the language the page is being rendered in.
     *
     * $SESSION->forcelang is what current_language() reads first; force_current_language() would
     * refuse pt_br on a test site without that language pack, which is every CI leg.
     *
     * @return void
     */
    public function test_og_locale_follows_the_current_language(): void {
        global $SESSION;

        $this->resetAfterTest();
        $page = $this->pages()->create_page();

        $SESSION->forcelang = 'pt_br';
        $this->assertSame(['pt_BR'], $this->contents($this->render($page), 'og:locale'));

        $SESSION->forcelang = 'en';
        $this->assertSame(['en'], $this->contents($this->render($page), 'og:locale'));
    }

    /**
     * A published page advertises its image at the hashed address, with its type and size; a draft
     * page with the very same kind of file advertises nothing.
     *
     * @return void
     */
    public function test_only_a_published_page_advertises_its_image_at_the_hashed_address(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance((int) $category->id);
        $png = ogimage_fixture::png(12, 7);

        $draft = $this->pages()->create_category_page((int) $category->id, ['status' => 'draft']);
        ogimage_fixture::store($context, (int) $draft->id, $png);
        $this->assertNotNull(ogimage::stored($context, (int) $draft->id), 'Control: the draft has its file.');
        $this->assertStringNotContainsString('og:image', $this->render($draft));

        $live = $this->pages()->create_category_page((int) $category->id, ['pagename' => 'Handbook']);
        ogimage_fixture::store($context, (int) $live->id, $png);
        $html = $this->render($live);

        $expected = \moodle_url::make_pluginfile_url(
            $context->id,
            'local_page',
            'ogimage',
            (int) $live->id,
            '/' . sha1($png) . '/',
            'cover.png',
            false
        )->out(false);
        $this->assertSame([s($expected)], $this->contents($html, 'og:image'));
        $this->assertStringContainsString('/' . sha1($png) . '/cover.png', $expected);
        $this->assertSame(['image/png'], $this->contents($html, 'og:image:type'));
        $this->assertSame(['12'], $this->contents($html, 'og:image:width'));
        $this->assertSame(['7'], $this->contents($html, 'og:image:height'));
        $this->assertSame(['Handbook'], $this->contents($html, 'og:image:alt'));
    }

    /**
     * A file whose content is not the image its name says is not advertised at all.
     *
     * The first two are typed image/png by their name, and the SVG even reports a size through core's
     * SVG branch of get_imageinfo(), so neither "is it typed as an image" nor "can it be measured"
     * would refuse it: only the type read from the content does. The same SVG under its own name is
     * refused by its name, since core counts an SVG as a web image. Advertising any of them would hand
     * scrapers an address the file route refuses.
     *
     * @return void
     */
    public function test_a_file_that_is_not_the_image_its_name_says_is_not_advertised(): void {
        $this->resetAfterTest();

        $system = \core\context\system::instance();

        $refused = [
            'an SVG named cover.png' => [ogimage_fixture::svg(), 'cover.png'],
            'a text file named cover.png' => ['not really a png', 'cover.png'],
            'an SVG named cover.svg' => [ogimage_fixture::svg(), 'cover.svg'],
        ];
        foreach ($refused as $label => [$content, $filename]) {
            $page = $this->pages()->create_page();
            ogimage_fixture::store($system, (int) $page->id, $content, $filename);

            // Control: the file is in the area the tags read, so what refuses it is the file itself.
            $this->assertNotNull(ogimage::stored($system, (int) $page->id), "{$label}: the file is stored");

            $html = $this->render($page);
            $this->assertStringNotContainsString('og:image', $html, $label);
            $this->assertNull(ogimage::for_page($page), $label);
        }

        // Control: a real image in the same area of another page is advertised, and measured.
        $real = $this->pages()->create_page();
        ogimage_fixture::store($system, (int) $real->id, ogimage_fixture::png(30, 20));
        $html = $this->render($real);
        $this->assertCount(1, $this->contents($html, 'og:image'));
        $this->assertSame(['image/png'], $this->contents($html, 'og:image:type'));
        $this->assertSame(['30'], $this->contents($html, 'og:image:width'));
        $this->assertSame(['20'], $this->contents($html, 'og:image:height'));
    }
}
