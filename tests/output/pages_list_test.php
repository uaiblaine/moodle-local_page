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
 * Tests for the addresses the listing screen renders.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\output;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for pages_list and page_card.
 *
 * Every link on this screen used to be built for the site-wide context and for no other, which is
 * how three separate defects arrived together: the add button opened the system editor, the delete
 * link asked the system screen to delete a category's row, and the friendly URL advertised an
 * address the site answers with somebody else's page. Since stage 6 a category card's friendly URL
 * is its own category address, and its public short address once one was minted. The site-wide shapes are asserted beside the
 * category ones on purpose — upstream's own screen must come out of this unchanged.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(pages_list::class)]
#[CoversClass(page_card::class)]
final class pages_list_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A renderer for the renderable to export against.
     *
     * export_for_template() never reads it, but the signature demands one and building it here is
     * what makes the test exercise the real call rather than a stub.
     *
     * @return \renderer_base
     */
    private function renderer(): \renderer_base {
        global $PAGE;

        $PAGE->set_url('/local/page/pages.php');

        return $PAGE->get_renderer('local_page');
    }

    /**
     * The one live card of an exported listing.
     *
     * @param \stdClass $exported Output of pages_list::export_for_template().
     * @return \stdClass
     */
    private function only_live_card(\stdClass $exported): \stdClass {
        $this->assertCount(1, $exported->livepages);

        return $exported->livepages[0];
    }

    /**
     * A category's listing keeps every link inside that category.
     *
     * @return void
     */
    public function test_a_categorys_listing_keeps_every_link_inside_the_category(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);
        $page = $this->pages()->create_category_page($category->id, ['menuname' => 'handbook']);

        $exported = (new pages_list([$page->id => $page], $context))->export_for_template($this->renderer());

        // The add button opens the editor for a new page IN THIS CATEGORY.
        $this->assertSame((string) $category->id, $exported->addpageurl->get_param('category'));
        $this->assertNull($exported->addpageurl->get_param('id'));

        $card = $this->only_live_card($exported);

        // The delete link names the context it was authorised in; without it the action is refused.
        $this->assertSame((string) $context->id, $card->deleteurl->get_param('contextid'));
        $this->assertSame((string) $page->id, $card->deleteurl->get_param('pagedel'));
        $this->assertSame(sesskey(), $card->deleteurl->get_param('sesskey'));

        /*
         * Never the site-wide wwwroot/<slug>, which would advertise an address that serves a different
         * page altogether: the friendly URL of a category page is its category address, spelled by
         * the builder — the route while the router is configured, the script otherwise.
         */
        $this->assertSame('handbook', $card->menuname);
        $this->assertSame(\local_page\local\links::page($page)->out(false), $card->friendlyurl);
        $this->assertNotSame($CFG->wwwroot . '/handbook', $card->friendlyurl);
    }

    /**
     * A category card shows its category address and, once one was minted, its public short address.
     *
     * The listing is a GET, and a GET never mints: the card only READS the code the save path minted.
     * Without the router there is no short address worth printing, code or not.
     *
     * @return void
     */
    public function test_a_category_card_shows_its_addresses_and_never_mints(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);
        $page = $this->pages()->create_category_page($category->id, ['menuname' => 'handbook']);
        $export = fn () => $this->only_live_card(
            (new pages_list([$page->id => $page], $context))->export_for_template($this->renderer())
        );

        // The router on and no code yet: the route, and no short address — and none minted by looking.
        $CFG->routerconfigured = true;
        \core\di::reset_container();
        $card = $export();
        $this->assertSame("{$CFG->wwwroot}/local_page/category/{$category->id}/handbook", $card->friendlyurl);
        $this->assertObjectNotHasProperty('shareurl', $card);
        $this->assertSame(0, $DB->count_records('shortlink', ['component' => 'local_page']), 'A GET never mints.');

        // Once the save path has minted one, the card shows it.
        $share = \local_page\local\links::share($page)->out(false);
        $card = $export();
        $this->assertSame($share, $card->shareurl);
        $this->assertStringStartsWith("{$CFG->wwwroot}/p/", $card->shareurl);

        // The router off: the script's address, and no short address even though a code exists.
        $CFG->routerconfigured = false;
        \core\di::reset_container();
        $card = $export();
        $this->assertSame("{$CFG->wwwroot}/local/page/index.php?category={$category->id}&page=handbook", $card->friendlyurl);
        $this->assertObjectNotHasProperty('shareurl', $card);

        // The site-wide card never carries one.
        $sitecard = (new page_card(7, 'Page', 'live', 0, 0, 'contact'))->export_for_template($this->renderer());
        $this->assertObjectNotHasProperty('shareurl', $sitecard);
    }

    /**
     * The friendly URL the RENDERED card prints is escaped, and reads the same for a site-wide card.
     *
     * Without the router a category page's address is the script's ?category=N&page=slug, the first
     * value of that block with an ampersand in it. Upstream printed the block through a triple stash,
     * which was harmless while it only ever held wwwroot/slug and puts a raw ampersand into the page
     * the moment it holds a query string. The exported string is asserted elsewhere; only the rendered
     * HTML shows which stash it went through, so this test reads the block out of the markup.
     *
     * @return void
     */
    public function test_the_rendered_friendly_url_is_escaped(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);
        $page = $this->pages()->create_category_page($category->id, ['menuname' => 'handbook']);

        $CFG->routerconfigured = false;
        \core\di::reset_container();
        $renderer = $this->renderer();
        $html = $renderer->render_page_card(new page_card($page->id, 'Handbook', 'live', 0, 0, 'handbook', $context));

        // Precondition: the value holds the ampersand this test is about, or it would pass by doing nothing.
        $address = "{$CFG->wwwroot}/local/page/index.php?category={$category->id}&page=handbook";
        $this->assertSame($address, \local_page\local\links::page($page)->out(false));

        $blocks = $this->friendly_url_blocks($html);
        $this->assertSame([s($address)], $blocks);
        $this->assertStringContainsString('&amp;page=handbook', $blocks[0]);
        $this->assertStringNotContainsString('&page=', $blocks[0]);

        // Control: a site-wide card prints exactly what upstream printed.
        $sitehtml = $renderer->render_page_card(new page_card(7, 'Page', 'live', 0, 0, 'contact'));
        $this->assertSame(["{$CFG->wwwroot}/contact"], $this->friendly_url_blocks($sitehtml));
    }

    /**
     * The contents of a rendered card's friendly URL block, and of no other address block.
     *
     * Every address on the card sits in the same kind of pre element, so the block is found by the
     * label rendered just before it rather than by a pattern that could match a neighbour.
     *
     * @param string $html A rendered page card
     * @return array The inner HTML of each friendly URL block, in document order
     */
    private function friendly_url_blocks(string $html): array {
        $label = preg_quote(get_string('menu_name', 'local_page'), '~');
        preg_match_all('~' . $label . '</label>\s*<pre class="custompage-url mb-2">(.*?)</pre>~s', $html, $matches);

        return $matches[1];
    }

    /**
     * The site-wide listing renders exactly what it rendered before the context dimension existed.
     *
     * This is the control for the test above: a card that simply stopped emitting friendly URLs,
     * or an add button that always named a category, would satisfy that one and break this.
     *
     * @return void
     */
    public function test_the_site_wide_listing_is_unchanged(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page(['menuname' => 'about-us']);

        $exported = (new pages_list([$page->id => $page]))->export_for_template($this->renderer());

        $this->assertSame([], $exported->addpageurl->params());

        $card = $this->only_live_card($exported);

        $this->assertNull($card->deleteurl->get_param('contextid'));
        $this->assertSame((string) $page->id, $card->deleteurl->get_param('pagedel'));
        $this->assertSame('about-us', $card->menuname);
        $this->assertSame($CFG->wwwroot . '/about-us', $card->friendlyurl);
    }

    /**
     * A card built without a context is a site-wide card, so every existing caller keeps working.
     *
     * @return void
     */
    public function test_a_card_with_no_context_of_its_own_is_a_site_wide_card(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $card = (new page_card(7, 'Page', 'live', 0, 0, 'contact'))->export_for_template($this->renderer());

        $this->assertNull($card->deleteurl->get_param('contextid'));
        $this->assertSame($CFG->wwwroot . '/contact', $card->friendlyurl);
    }
}
