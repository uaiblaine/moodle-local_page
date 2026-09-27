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
 * Tests for the category pages catalogue.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use local_page\tests\public_predicate;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The list of a category's pages another plugin draws: what the viewer may read, and only that.
 *
 * Every test runs with $CFG->forcelogin on, the production state, and hands the predicate double to
 * the code under test: local_unlistedcourses is not a dependency and may be absent from the site.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(catalogue::class)]
final class catalogue_test extends \advanced_testcase {
    /**
     * Forget the predicate double's answers and count, and the router's memoised base path.
     *
     * @return void
     */
    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        $CFG->forcelogin = 1;
        $CFG->routerconfigured = false;
        \core\di::reset_container();
        public_predicate::reset();
    }

    /**
     * Forget the router the test built.
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
     * The names of an answer, in order.
     *
     * @param array $answer The catalogue's answer
     * @return string[]
     */
    private static function names(array $answer): array {
        return array_column($answer, 'name');
    }

    /**
     * A visitor is refused before any statement unless the category is public, and a missing one answers alike.
     *
     * @return void
     */
    public function test_a_visitor_is_refused_before_any_lookup_unless_the_category_is_public(): void {
        global $DB;

        $private = (int) $this->getDataGenerator()->create_category()->id;
        $public = (int) $this->getDataGenerator()->create_category()->id;
        $missing = $public + 1000;
        foreach ([$private, $public] as $categoryid) {
            $this->pages()->create_category_page($categoryid, ['pagename' => 'Handbook', 'menuname' => 'handbook']);
        }
        $this->setUser(null);

        public_predicate::reset([$public]);
        foreach (['private' => $private, 'missing' => $missing] as $label => $categoryid) {
            $before = $DB->perf_get_reads();
            $this->assertSame([], catalogue::for_viewer($categoryid, public_predicate::class), $label);
            $this->assertSame(0, $DB->perf_get_reads() - $before, "{$label}: refused before any statement.");
        }
        $this->assertSame(2, public_predicate::calls(), 'Both refusals were the predicate\'s.');

        $before = $DB->perf_get_reads();
        $this->assertSame(['Handbook'], self::names(catalogue::for_viewer($public, public_predicate::class)));
        $this->assertGreaterThan(0, $DB->perf_get_reads() - $before, 'Control: a lookup is counted.');
    }

    /**
     * The guest account is a visitor: refused in a private category, served in a public one.
     *
     * @return void
     */
    public function test_the_guest_account_is_a_visitor(): void {
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Handbook']);
        $this->setGuestUser();

        $this->assertSame([], catalogue::for_viewer($categoryid, public_predicate::class));
        $this->assertSame(1, public_predicate::calls(), 'The guest was asked about.');

        public_predicate::reset([$categoryid]);
        $this->assertSame(['Handbook'], self::names(catalogue::for_viewer($categoryid, public_predicate::class)));
    }

    /**
     * Only the category's own live pages inside their window are listed, even to somebody who may preview the rest.
     *
     * The viewer is the site administrator, whom the read-side rule lets through to every page: the
     * list's own filters are what keep a draft, an archived, a deleted, a scheduled and an expired
     * page off it - and the pages of another category, of a subcategory and of the site, which the
     * administrator could all read at their own addresses.
     *
     * @return void
     */
    public function test_only_the_categorys_own_live_pages_inside_their_window_are_listed(): void {
        $category = $this->getDataGenerator()->create_category();
        $categoryid = (int) $category->id;
        $other = (int) $this->getDataGenerator()->create_category()->id;
        $child = (int) $this->getDataGenerator()->create_category(['parent' => $categoryid])->id;
        $now = time();

        $this->pages()->create_category_page($categoryid, ['pagename' => 'Live']);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Draft', 'status' => 'draft']);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Archived', 'status' => 'archived']);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Deleted', 'deleted' => 1]);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Scheduled', 'pagedate' => $now + DAYSECS]);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Expired', 'enddate' => $now - DAYSECS]);
        $this->pages()->create_category_page($categoryid, [
            'pagename' => 'Open window',
            'pagedate' => $now - DAYSECS,
            'enddate' => $now + DAYSECS,
        ]);
        $this->pages()->create_category_page($other, ['pagename' => 'Another category']);
        $this->pages()->create_category_page($child, ['pagename' => 'A subcategory']);
        $this->pages()->create_page(['pagename' => 'Site-wide']);

        $this->setAdminUser();
        $this->assertSame(['Live', 'Open window'], self::names(catalogue::for_viewer($categoryid)));
        $this->assertSame([], catalogue::for_viewer(0), 'The root holds no page.');
    }

    /**
     * The page's own rules apply: logged-in only, and the access level.
     *
     * @return void
     */
    public function test_the_pages_own_rules_decide_who_sees_it(): void {
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Everyone']);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Members', 'onlyloggedin' => 1]);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Managers', 'accesslevel' => 'moodle/category:manage']);

        $this->setUser(null);
        public_predicate::reset([$categoryid]);
        $this->assertSame(['Everyone'], self::names(catalogue::for_viewer($categoryid, public_predicate::class)));

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(['Everyone', 'Members'], self::names(catalogue::for_viewer($categoryid, public_predicate::class)));

        $manager = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $manager->id, \core\context\coursecat::instance($categoryid)->id);
        $this->setUser($manager);
        $this->assertSame(
            ['Everyone', 'Managers', 'Members'],
            self::names(catalogue::for_viewer($categoryid, public_predicate::class)),
            'The access level admits the category\'s manager.'
        );
    }

    /**
     * The answer is plain data in name order: the plain spelling, the page's own address, an integer id.
     *
     * @return void
     */
    public function test_the_answer_is_plain_data_in_name_order(): void {
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $edital = $this->pages()->create_category_page($categoryid, [
            'pagename' => 'Édital & regras',
            'menuname' => 'edital',
            'metadescription' => 'Prazos <b>e</b> documentos',
        ]);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Calendário', 'menuname' => 'calendario']);
        $this->pages()->create_category_page($categoryid, ['pagename' => 'Agenda', 'menuname' => 'agenda']);
        $this->setUser($this->getDataGenerator()->create_user());

        $answer = catalogue::for_viewer($categoryid);
        $this->assertSame(['Agenda', 'Calendário', 'Édital & regras'], self::names($answer), 'The collator\'s order.');

        $page = $answer[2];
        $this->assertSame(['id', 'name', 'url', 'description'], array_keys($page));
        $this->assertSame((int) $edital->id, $page['id']);
        $this->assertSame('Édital & regras', $page['name'], 'Plain: the ampersand is escaped where it is written.');
        $this->assertSame('Prazos e documentos', $page['description'], 'Tags stripped, never escaped.');
        $this->assertSame(links::page($edital)->out(false), $page['url']);
        $this->assertSame('', $answer[0]['description'], 'No description, the empty string.');
    }
}
