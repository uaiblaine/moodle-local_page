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
 * Tests for the address builder.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \local_page\local\links: every address of a page, with the router configured and without.
 *
 * $CFG->routerconfigured is set explicitly in every test, both ways, because the value a test site
 * inherits from its config.php differs from site to site. The router memoises its base path when it
 * is built, which is why the container is emptied each time the setting changes.
 *
 * These are plain advanced_testcase tests, which may spell routed addresses freely. Inside a
 * route_testcase, whose harness builds its own router, spelling one before the request would build
 * a second router.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(links::class)]
final class links_test extends \advanced_testcase {
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
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
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
     * This plugin's shortlink rows of a page, oldest first.
     *
     * @param int $pageid Page id
     * @return array
     */
    private function rows(int $pageid): array {
        global $DB;

        return array_values($DB->get_records('shortlink', [
            'component' => links::COMPONENT,
            'identifier' => (string) $pageid,
        ], 'id ASC'));
    }

    /**
     * Without the router every address is the script's, and nothing spells a route.
     *
     * @return void
     */
    public function test_without_the_router_every_address_is_the_scripts(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->set_router(false);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $categorypage = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $sitepage = $this->pages()->create_page(['menuname' => 'welcome']);

        $script = $CFG->wwwroot . '/local/page/index.php';
        $this->assertSame(
            "{$script}?category={$categoryid}&page=handbook",
            links::category_page($categoryid, 'handbook')->out(false),
            'The builder falls back to the script while the router is off.'
        );
        $this->assertSame("{$script}?category={$categoryid}&page=handbook", links::page($categorypage)->out(false));
        $this->assertSame("{$script}?id={$sitepage->id}", links::page($sitepage)->out(false));
    }

    /**
     * With the router a category page's canonical address is its route; the script's forms stay the script's.
     *
     * @return void
     */
    public function test_with_the_router_a_category_page_is_addressed_by_its_route(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->set_router(true);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $categorypage = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $sitepage = $this->pages()->create_page(['menuname' => 'welcome']);

        $route = "{$CFG->wwwroot}/local_page/category/{$categoryid}/handbook";
        $this->assertSame($route, links::category_page($categoryid, 'handbook')->out(false), 'The route, without r.php.');
        $this->assertSame($route, links::page($categorypage)->out(false));

        // The script's own forms are the script's, router or not: they are what the route falls back to.
        $script = $CFG->wwwroot . '/local/page/index.php';
        $this->assertSame(
            "{$script}?category={$categoryid}&page=handbook",
            links::legacy_category($categoryid, 'handbook')->out(false)
        );
        $this->assertSame(
            "{$script}?category={$categoryid}&id={$categorypage->id}",
            links::legacy_category_id($categoryid, (int) $categorypage->id)->out(false)
        );

        // A site-wide page keeps upstream's addresses: page() never answers wwwroot/slug, which only legacy_menuname() spells.
        $this->assertSame("{$script}?id={$sitepage->id}", links::page($sitepage)->out(false));
        $this->assertSame("{$CFG->wwwroot}/welcome", links::legacy_menuname('welcome')->out(false));
    }

    /**
     * A category row whose slug the route would refuse is addressed by its id, which the script answers.
     *
     * @return void
     */
    public function test_a_category_row_without_a_usable_slug_is_addressed_by_id(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->set_router(true);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $noslug = $this->pages()->create_category_page($categoryid, ['menuname' => '']);
        $badslug = $this->pages()->create_category_page($categoryid, ['menuname' => 'two words']);

        $script = $CFG->wwwroot . '/local/page/index.php';
        $this->assertSame("{$script}?category={$categoryid}&id={$noslug->id}", links::page($noslug)->out(false));
        $this->assertSame("{$script}?category={$categoryid}&id={$badslug->id}", links::page($badslug)->out(false));
    }

    /**
     * share() mints a category page's code once and reuses it; another page gets a code of its own.
     *
     * @return void
     */
    public function test_share_mints_once_and_reuses_the_code(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->set_router(true);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $other = $this->pages()->create_category_page($categoryid, ['menuname' => 'contacts']);

        $this->assertNull(links::existing_code((int) $page->id), 'Nothing minted before the first call.');
        $this->assertNull(links::existing_share((int) $page->id));

        $first = links::share($page)->out(false);
        $second = links::share($page)->out(false);

        $rows = $this->rows((int) $page->id);
        $this->assertCount(1, $rows, 'One row for the page, however often it is shared.');
        $code = (string) $rows[0]->shortcode;
        $this->assertSame(0, (int) $rows[0]->userid, 'A public code: no user.');
        $this->assertSame(links::LINKTYPE, $rows[0]->linktype);
        $this->assertSame("{$CFG->wwwroot}/p/{$code}", $first, 'The public route, without r.php.');
        $this->assertSame($first, $second, 'The same address twice.');
        $this->assertSame($code, links::existing_code((int) $page->id));
        $this->assertSame($first, links::existing_share((int) $page->id)->out(false));

        // Control: another page gets a code of its own, so the reuse above is not one code for everybody.
        $othercode = links::share($other)->out(false);
        $this->assertNotSame($first, $othercode);
        $this->assertCount(1, $this->rows((int) $other->id));
    }

    /**
     * The oldest row wins, so two rows left by a race spell one address.
     *
     * @return void
     */
    public function test_the_oldest_code_wins(): void {
        global $DB;

        $this->resetAfterTest();
        $this->set_router(true);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid);
        foreach (['older1', 'newer2'] as $code) {
            $DB->insert_record('shortlink', (object) [
                'shortcode' => $code,
                'userid' => 0,
                'component' => links::COMPONENT,
                'linktype' => links::LINKTYPE,
                'identifier' => (string) $page->id,
            ]);
        }

        $this->assertSame('older1', links::existing_code((int) $page->id));
        $this->assertStringEndsWith('/p/older1', links::share($page)->out(false));
        $this->assertCount(2, $this->rows((int) $page->id), 'share() reused a code rather than minting a third.');
    }

    /**
     * Without the router share() is the page's own address, and nothing is minted.
     *
     * @return void
     */
    public function test_share_without_the_router_is_the_page_and_mints_nothing(): void {
        $this->resetAfterTest();
        $this->set_router(false);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);

        $this->assertSame(links::page($page)->out(false), links::share($page)->out(false));
        $this->assertSame([], $this->rows((int) $page->id));
        $this->assertNull(links::existing_share((int) $page->id));
    }

    /**
     * A site-wide page never mints a code: it keeps upstream's friendly URL.
     *
     * @return void
     */
    public function test_a_site_wide_page_never_mints(): void {
        $this->resetAfterTest();
        $this->set_router(true);
        $page = $this->pages()->create_page(['menuname' => 'welcome']);

        $this->assertSame(links::page($page)->out(false), links::share($page)->out(false));
        $this->assertSame([], $this->rows((int) $page->id));
    }

    /**
     * forget() deletes the page's rows and nobody else's.
     *
     * @return void
     */
    public function test_forget_deletes_the_pages_codes_and_nobody_elses(): void {
        global $DB;

        $this->resetAfterTest();
        $this->set_router(true);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $other = $this->pages()->create_category_page($categoryid, ['menuname' => 'contacts']);
        links::share($page);
        links::share($other);
        // Another component's row with the same identifier is not this plugin's to delete.
        $DB->insert_record('shortlink', (object) [
            'shortcode' => 'foreign1',
            'userid' => 0,
            'component' => 'mod_example',
            'linktype' => 'submit',
            'identifier' => (string) $page->id,
        ]);

        links::forget((int) $page->id);

        $this->assertSame([], $this->rows((int) $page->id));
        $this->assertNull(links::existing_code((int) $page->id));
        $this->assertCount(1, $this->rows((int) $other->id), 'The other page keeps its code.');
        $this->assertTrue($DB->record_exists('shortlink', ['shortcode' => 'foreign1']), 'Another component keeps its row.');
    }

    /**
     * Uninstalling the plugin removes every code it minted and nothing else.
     *
     * @return void
     */
    public function test_uninstall_removes_the_plugins_codes(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/page/db/uninstall.php');

        $this->resetAfterTest();
        $this->set_router(true);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        links::share($page);
        $DB->insert_record('shortlink', (object) [
            'shortcode' => 'foreign1',
            'userid' => 0,
            'component' => 'mod_example',
            'linktype' => 'submit',
            'identifier' => '1',
        ]);
        $this->assertCount(1, $this->rows((int) $page->id), 'Precondition: a code to remove.');

        xmldb_local_page_uninstall();

        $this->assertFalse($DB->record_exists('shortlink', ['component' => links::COMPONENT]));
        $this->assertTrue($DB->record_exists('shortlink', ['shortcode' => 'foreign1']));
    }
}
