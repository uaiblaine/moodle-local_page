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
 * Tests for the viewer's request class.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use local_page\tests\public_predicate;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \local_page\local\request: the viewer's guard order, for both kinds of address.
 *
 * Every test runs with $CFG->forcelogin on: the request class never reads it, and under it somebody
 * not logged in holds no capability at all (see has_capability()), so a page that renders for them
 * here renders for them on any site.
 *
 * Whether a category is public comes from \local_page\tests\public_predicate, handed in through the
 * $predicate parameter — local_unlistedcourses need not be installed, and a refusal can only be told
 * apart from "refuses everything" by a public category served in the same test.
 *
 * redirect() is never reached: in a CLI process it throws a moodle_exception carrying no URL, which
 * is why the class returns its redirect target and index.php or the route controller issues it.
 *
 * Addresses are spelled through \local_page\local\links, which answers the route while
 * $CFG->routerconfigured is set and the script otherwise. The value a test site inherits from its
 * config.php varies, so a test whose answer depends on it sets it explicitly; the others hold on
 * either setting because they compare against the builder.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(request::class)]
final class request_test extends \advanced_testcase {
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
     * Declare the site's router configured or not; the router memoises its base path when built.
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
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * Log everybody out, under forcelogin, and forget any wantsurl a previous call left behind.
     *
     * @return void
     */
    private function become_visitor(): void {
        global $CFG, $SESSION;

        $CFG->forcelogin = 1;
        $this->setUser(null);
        unset($SESSION->wantsurl);
    }

    /**
     * A new $PAGE, because set_category_by_id() refuses to run twice on the same one.
     *
     * @return void
     */
    private function fresh_page(): void {
        $GLOBALS['PAGE'] = new \moodle_page();
    }

    /**
     * The address of a category's page, as the request class builds it: through the builder.
     *
     * @param int $categoryid Course category id
     * @param string $slug Friendly URL within the category
     * @return string
     */
    private function category_address(int $categoryid, string $slug): string {
        return links::category_page($categoryid, $slug)->out(false);
    }

    /**
     * Asserts that an answer is the visitor's refusal, with wantsurl pointing back at an address.
     *
     * @param request $answer The answer
     * @param string $wantsurl The address the visitor must come back to after logging in
     * @param string $label What is being refused
     * @return void
     */
    private function assert_refused(request $answer, string $wantsurl, string $label): void {
        global $SESSION;

        $this->assertNotNull($answer->redirect, "{$label}: a redirect");
        $this->assertSame(get_login_url(), $answer->redirect->out(false), "{$label}: to the login page");
        $this->assertNull($answer->page, "{$label}: no page was handed out");
        $this->assertFalse($answer->canview, "{$label}: nothing to read");
        $this->assertSame($wantsurl, $SESSION->wantsurl ?? null, "{$label}: wantsurl brings them back here");
    }

    /**
     * A public category's page renders for a visitor, a private one's does not.
     *
     * The two pages are identical — both live, both open to visitors by their own rules, both at the
     * same slug — so the only thing that can tell them apart is whether their category is public.
     *
     * @return void
     */
    public function test_a_public_categorys_page_renders_for_a_visitor_while_a_private_ones_refuses(): void {
        global $SESSION;

        $this->resetAfterTest();
        $public = (int) $this->getDataGenerator()->create_category()->id;
        $private = (int) $this->getDataGenerator()->create_category()->id;
        $publicpage = $this->pages()->create_category_page($public, ['menuname' => 'handbook']);
        $this->pages()->create_category_page($private, ['menuname' => 'handbook']);

        public_predicate::reset([$public]);
        $this->become_visitor();

        $refused = request::category($private, 0, 'handbook', public_predicate::class);
        $this->assert_refused($refused, $this->category_address($private, 'handbook'), 'private category');

        unset($SESSION->wantsurl);
        $served = request::category($public, 0, 'handbook', public_predicate::class);
        $this->assertNull($served->redirect, 'public category: no redirect');
        $this->assertTrue($served->canview, 'public category: the visitor may read it');
        $this->assertSame((int) $publicpage->id, (int) $served->page->id, 'public category: its own page');
        $this->assertFalse(isset($SESSION->wantsurl), 'public category: nothing to come back to');
    }

    /**
     * A visitor whom a public category's page withholds itself from is sent to log in, like a missing page.
     *
     * Three pages in one public category: a live one, one for logged-in readers and a draft. The
     * live one is the control — it renders, so the refusals below are the rules' and not the
     * category's — and the other two answer the visitor with the same login redirect a slug that
     * names nothing gets, at the category address and at the page's own id address alike. A
     * logged-in user meets the rules themselves: the page for logged-in readers renders, the draft
     * is the "no access" page and never a redirect.
     *
     * @return void
     */
    public function test_a_visitor_a_public_categorys_page_withholds_itself_from_is_sent_to_log_in(): void {
        global $SESSION;

        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $members = $this->pages()->create_category_page($categoryid, ['menuname' => 'members', 'onlyloggedin' => 1]);
        $this->pages()->create_category_page($categoryid, ['menuname' => 'draft', 'status' => 'draft']);
        $user = $this->getDataGenerator()->create_user();

        public_predicate::reset([$categoryid]);
        $this->become_visitor();

        // Control: the category is public and its live page renders for the visitor.
        $served = request::category($categoryid, 0, 'handbook', public_predicate::class);
        $this->assertNull($served->redirect, 'live page: no redirect');
        $this->assertTrue($served->canview, 'live page: readable by a visitor');

        foreach (['members', 'draft', 'nosuchpage'] as $slug) {
            unset($SESSION->wantsurl);
            $this->fresh_page();
            $answer = request::category($categoryid, 0, $slug, public_predicate::class);
            $this->assert_refused($answer, $this->category_address($categoryid, $slug), "visitor at {$slug}");
        }

        // The page's own id address answers the visitor the same way.
        unset($SESSION->wantsurl);
        $this->fresh_page();
        $byid = request::legacy((int) $members->id, '', public_predicate::class);
        $wantsurl = (new \moodle_url('/local/page/index.php', ['id' => (int) $members->id]))->out(false);
        $this->assert_refused($byid, $wantsurl, 'visitor at the members page by id');

        // A logged-in user meets the rules, never the redirect.
        $this->setUser($user);
        unset($SESSION->wantsurl);
        $this->fresh_page();
        $forreader = request::category($categoryid, 0, 'members', public_predicate::class);
        $this->assertNull($forreader->redirect, 'user at members: no redirect');
        $this->assertTrue($forreader->canview, 'user at members: readable once logged in');
        $this->fresh_page();
        $fordraft = request::category($categoryid, 0, 'draft', public_predicate::class);
        $this->assertNull($fordraft->redirect, 'user at draft: no redirect');
        $this->assertFalse($fordraft->canview, 'user at draft: the "no access" page');
    }

    /**
     * A category that does not exist is refused exactly like a private one, before anything is looked up.
     *
     * An anonymous client must not be able to tell which category ids exist, so both answers are
     * compared: the same login page, a wantsurl of the same shape, and zero database statements on
     * either path. The statement count is what "before any lookup" means in a form a test can hold,
     * and the call count says each refusal was the predicate's and not a missing row's. The control
     * is the same address with the category made public, which does read — so the meter can see a
     * lookup when one happens.
     *
     * @return void
     */
    public function test_a_missing_category_is_refused_like_a_private_one_and_before_any_lookup(): void {
        global $DB;

        $this->resetAfterTest();
        $private = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($private, ['menuname' => 'handbook']);
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;

        $this->become_visitor();

        // Warm the class loader, so that nothing measured below is the first use of a class.
        public_predicate::reset([]);
        request::category($private, 0, 'handbook', public_predicate::class);
        public_predicate::reset([]);

        $before = $DB->perf_get_reads();
        $formissing = request::category($missing, 0, 'handbook', public_predicate::class);
        $readsformissing = $DB->perf_get_reads() - $before;
        $this->assert_refused($formissing, $this->category_address($missing, 'handbook'), 'missing category');

        $before = $DB->perf_get_reads();
        $forprivate = request::category($private, 0, 'handbook', public_predicate::class);
        $readsforprivate = $DB->perf_get_reads() - $before;
        $this->assert_refused($forprivate, $this->category_address($private, 'handbook'), 'private category');

        $this->assertEquals($formissing->redirect, $forprivate->redirect, 'One refusal for both.');
        $this->assertSame(0, $readsformissing, 'The missing category was refused before any statement.');
        $this->assertSame(0, $readsforprivate, 'So was the private one.');
        $this->assertSame(2, public_predicate::calls(), 'Both refusals were the predicate\'s.');

        // Control: the same address in a public category reaches the lookup, and the meter sees it.
        public_predicate::reset([$private]);
        $before = $DB->perf_get_reads();
        $served = request::category($private, 0, 'handbook', public_predicate::class);
        $this->assertGreaterThan(0, $DB->perf_get_reads() - $before, 'Control: a lookup is counted.');
        $this->assertTrue($served->canview);
    }

    /**
     * The guest account is a visitor, refused and admitted on the same terms as nobody at all.
     *
     * @return void
     */
    public function test_the_guest_account_is_a_visitor(): void {
        global $CFG;

        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $CFG->forcelogin = 1;

        public_predicate::reset([]);
        $this->setGuestUser();
        $refused = request::category($categoryid, 0, 'handbook', public_predicate::class);
        $this->assert_refused($refused, $this->category_address($categoryid, 'handbook'), 'guest, private category');
        $this->assertSame(1, public_predicate::calls(), 'The guest was asked about.');

        // Control: a public category's page reaches the guest.
        public_predicate::reset([$categoryid]);
        $served = request::category($categoryid, 0, 'handbook', public_predicate::class);
        $this->assertNull($served->redirect);
        $this->assertTrue($served->canview, 'guest, public category');
        $this->assertSame((int) $page->id, (int) $served->page->id);
    }

    /**
     * A logged-in user is never asked about, and meets the page's own rules instead.
     *
     * The members-only page is what separates the two classes of viewer: the guest gets through the
     * category (it is public here) and is then refused by the page itself — with the visitor's login
     * refusal, the one answer a visitor ever gets — while a user reads it in a category that is NOT
     * public, because they were never the predicate's business.
     *
     * @return void
     */
    public function test_a_logged_in_user_is_never_asked_and_meets_the_pages_own_rules(): void {
        global $CFG, $SESSION;

        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($categoryid, ['menuname' => 'members', 'onlyloggedin' => 1]);
        $this->pages()->create_category_page($categoryid, ['menuname' => 'open']);
        $CFG->forcelogin = 1;

        // The guest passes the category and is refused by the page, with the visitor's one refusal.
        // Two questions: the request's own before the lookup, and the page rule's — the clause the
        // file route shares — once the row was found; a refusal at the category would have stopped at one.
        public_predicate::reset([$categoryid]);
        $this->setGuestUser();
        unset($SESSION->wantsurl);
        $forguest = request::category($categoryid, 0, 'members', public_predicate::class);
        $this->assertSame(2, public_predicate::calls(), 'guest: the category let them through to the page');
        $this->assert_refused($forguest, $this->category_address($categoryid, 'members'), 'guest at members');

        // A user, in a category that is not public at all.
        public_predicate::reset([]);
        $this->setUser($this->getDataGenerator()->create_user());
        foreach (['members', 'open'] as $slug) {
            $this->fresh_page();
            $foruser = request::category($categoryid, 0, $slug, public_predicate::class);
            $this->assertNull($foruser->redirect, "user, {$slug}: no redirect");
            $this->assertTrue($foruser->canview, "user, {$slug}: readable");
        }
        $this->assertSame(0, public_predicate::calls(), 'The predicate is a visitor\'s question only.');
    }

    /**
     * A page of another context is not this address's page, whatever its id or slug.
     *
     * @return void
     */
    public function test_a_page_of_another_context_is_not_found_at_this_address(): void {
        $this->resetAfterTest();
        $cata = (int) $this->getDataGenerator()->create_category()->id;
        $catb = (int) $this->getDataGenerator()->create_category()->id;
        $pageb = $this->pages()->create_category_page($catb, ['menuname' => 'handbook']);
        $sitepage = $this->pages()->create_page(['menuname' => 'welcome']);
        public_predicate::reset([$cata, $catb]);

        $this->setUser($this->getDataGenerator()->create_user());
        $notfound = [
            'another category\'s page by id' => [$cata, (int) $pageb->id, ''],
            'another category\'s page by slug' => [$cata, 0, 'handbook'],
            'a site-wide page by id' => [$cata, (int) $sitepage->id, ''],
            'a site-wide page by slug' => [$cata, 0, 'welcome'],
        ];
        foreach ($notfound as $label => [$categoryid, $pageid, $slug]) {
            $this->fresh_page();
            $answer = request::category($categoryid, $pageid, $slug, public_predicate::class);
            $this->assertNull($answer->redirect, "user, {$label}: no redirect");
            $this->assertSame(0, (int) $answer->page->id, "user, {$label}: the placeholder, not the row");
            $this->assertFalse($answer->canview, "user, {$label}: nothing to read");
        }

        // Control: the page's own category serves it, by id and by slug.
        foreach ([[(int) $pageb->id, ''], [0, 'handbook']] as [$pageid, $slug]) {
            $this->fresh_page();
            $answer = request::category($catb, $pageid, $slug, public_predicate::class);
            $this->assertSame((int) $pageb->id, (int) $answer->page->id, 'control: its own category');
            $this->assertTrue($answer->canview);
        }

        // A visitor meets "not found" as the one refusal, even in a public category.
        $this->become_visitor();
        $answer = request::category($cata, (int) $pageb->id, '', public_predicate::class);
        $wantsurl = (new \moodle_url('/local/page/index.php', ['category' => $cata, 'id' => (int) $pageb->id]))->out(false);
        $this->assert_refused($answer, $wantsurl, 'visitor, another category\'s page');
    }

    /**
     * A category that does not exist answers a logged-in user with the "no access" page, not an exception.
     *
     * @return void
     */
    public function test_a_missing_category_answers_a_user_with_the_placeholder(): void {
        global $DB, $PAGE;

        $this->resetAfterTest();
        $this->getDataGenerator()->create_category();
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;

        $this->setUser($this->getDataGenerator()->create_user());
        $answer = request::category($missing, 0, 'handbook', public_predicate::class);

        $this->assertNull($answer->redirect);
        $this->assertSame(0, (int) $answer->page->id);
        $this->assertFalse($answer->canview);
        $this->assertSame(\context_system::instance()->id, $PAGE->context->id, 'The placeholder names no category.');
    }

    /**
     * Upstream's addresses: a category page by id is refused to a visitor unless public; site pages are untouched.
     *
     * @return void
     */
    public function test_the_legacy_addresses_apply_the_predicate_to_category_pages_only(): void {
        $this->resetAfterTest();
        // The router off: its control renders a category page on the script, which the router would redirect.
        $this->set_router(false);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $categorypage = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $sitepage = $this->pages()->create_page(['menuname' => 'welcome']);

        $this->become_visitor();

        public_predicate::reset([]);
        $refused = request::legacy((int) $categorypage->id, '', public_predicate::class);
        $wantsurl = (new \moodle_url('/local/page/index.php', ['id' => (int) $categorypage->id]))->out(false);
        $this->assert_refused($refused, $wantsurl, 'category page by id, private category');

        // Control: the same address in a public category renders.
        public_predicate::reset([$categoryid]);
        $served = request::legacy((int) $categorypage->id, '', public_predicate::class);
        $this->assertNull($served->redirect, 'category page by id, public category');
        $this->assertTrue($served->canview);

        // Site-wide pages are nobody's category: the predicate is never asked about them.
        public_predicate::reset([]);
        foreach ([[0, 'welcome'], [(int) $sitepage->id, '']] as [$pageid, $menuname]) {
            $this->fresh_page();
            $answer = request::legacy($pageid, $menuname, public_predicate::class);
            $this->assertNull($answer->redirect, "site page {$pageid}/{$menuname}: no redirect");
            $this->assertTrue($answer->canview, "site page {$pageid}/{$menuname}: readable by a visitor");
            $this->assertSame((int) $sitepage->id, (int) $answer->page->id);
        }
        $this->assertSame(0, public_predicate::calls(), 'Never asked about a site-wide row.');
    }

    /**
     * $PAGE ends up in the page's context, at the address that was asked for.
     *
     * @return void
     */
    public function test_page_is_set_up_in_the_pages_context_at_the_address_asked_for(): void {
        global $PAGE;

        $this->resetAfterTest();
        // The router off: the legacy id address of a category page renders only while it is.
        $this->set_router(false);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $context = \core\context\coursecat::instance($categoryid);
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $this->pages()->create_page(['menuname' => 'welcome']);
        $this->setUser($this->getDataGenerator()->create_user());

        request::category($categoryid, 0, 'handbook', public_predicate::class);
        $this->assertSame($context->id, $PAGE->context->id, 'category address by slug: the category context');
        $this->assertSame($categoryid, (int) $PAGE->category->id, 'and the category itself');
        $this->assertSame($this->category_address($categoryid, 'handbook'), $PAGE->url->out(false));

        $this->fresh_page();
        request::category($categoryid, (int) $page->id, '', public_predicate::class);
        $this->assertSame($context->id, $PAGE->context->id, 'category address by id');
        $this->assertSame(
            (new \moodle_url('/local/page/index.php', ['category' => $categoryid, 'id' => (int) $page->id]))->out(false),
            $PAGE->url->out(false)
        );

        $this->fresh_page();
        request::legacy((int) $page->id, '', public_predicate::class);
        $this->assertSame($context->id, $PAGE->context->id, 'legacy id address of a category page');
        $legacyaddress = new \moodle_url('/local/page/index.php', ['id' => (int) $page->id]);
        $this->assertSame($legacyaddress->out(false), $PAGE->url->out(false));

        $this->fresh_page();
        request::legacy(0, 'welcome', public_predicate::class);
        $this->assertSame(\context_system::instance()->id, $PAGE->context->id, 'legacy friendly URL: the system context');
        $this->assertSame((new \moodle_url('/welcome'))->out(false), $PAGE->url->out(false));
    }

    /**
     * Every answer carries the status it calls for: 200 to render, 302 for the visitor's refusal, 303 to move.
     *
     * The route controller sends this status; index.php cannot (core's redirect() always answers 303),
     * which is why it is a field of the answer and not the controller's own choice.
     *
     * @return void
     */
    public function test_every_answer_carries_its_status(): void {
        $this->resetAfterTest();
        $this->set_router(true);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        public_predicate::reset([]);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertSame(request::STATUS_OK, request::category($categoryid, 0, 'handbook', public_predicate::class)->status);
        $moved = request::category($categoryid, 0, 'handbook', public_predicate::class, legacy: true);
        $this->assertSame(request::STATUS_SEE_OTHER, $moved->status);

        $this->become_visitor();
        $this->assertSame(request::STATUS_LOGIN, request::category($categoryid, 0, 'handbook', public_predicate::class)->status);
    }

    /**
     * A category page's ?id= address sends a reader to its route, and a visitor it is withheld from to log in.
     *
     * The 303 spells the page's category and slug, so it is issued only once the page's own rules and
     * the public predicate have let the viewer read the page. A visitor of a private category must get
     * the login refusal and never the routed address — handing it to them would tell an anonymous
     * client what the id names. The draft is the logged-in half of the same rule: the "no access"
     * page, rendered where it was asked for, not a redirect to the draft's address.
     *
     * @return void
     */
    public function test_a_category_pages_id_address_moves_a_reader_and_refuses_a_visitor(): void {
        $this->resetAfterTest();
        $this->set_router(true);
        $private = (int) $this->getDataGenerator()->create_category()->id;
        $public = (int) $this->getDataGenerator()->create_category()->id;
        $privatepage = $this->pages()->create_category_page($private, ['menuname' => 'handbook']);
        $publicpage = $this->pages()->create_category_page($public, ['menuname' => 'handbook']);
        $draft = $this->pages()->create_category_page($private, ['menuname' => 'draft', 'status' => 'draft']);
        $sitepage = $this->pages()->create_page(['menuname' => 'welcome']);
        public_predicate::reset([$public]);

        // A visitor of the private category: the refusal, never the route.
        $this->become_visitor();
        $refused = request::legacy((int) $privatepage->id, '', public_predicate::class);
        $this->assert_refused($refused, links::legacy((int) $privatepage->id)->out(false), 'visitor, private category');
        $this->assertNotSame(links::page($privatepage)->out(false), $refused->redirect->out(false), 'The slug is not leaked.');

        // Control: a visitor the public category's page reaches is moved to the route.
        $moved = request::legacy((int) $publicpage->id, '', public_predicate::class);
        $this->assertSame(request::STATUS_SEE_OTHER, $moved->status);
        $this->assertSame(links::page($publicpage)->out(false), $moved->redirect->out(false));
        $this->assertStringContainsString("/local_page/category/{$public}/handbook", $moved->redirect->out(false));

        // A logged-in reader of the private category is moved as well; a draft is the "no access" page.
        $this->setUser($this->getDataGenerator()->create_user());
        $this->fresh_page();
        $forreader = request::legacy((int) $privatepage->id, '', public_predicate::class);
        $this->assertSame(links::page($privatepage)->out(false), $forreader->redirect->out(false));
        $this->fresh_page();
        $fordraft = request::legacy((int) $draft->id, '', public_predicate::class);
        $this->assertNull($fordraft->redirect, 'A draft withheld from the reader is not redirected to.');
        $this->assertFalse($fordraft->canview);

        // A site-wide page is never moved: it has no route.
        $this->fresh_page();
        $forsite = request::legacy((int) $sitepage->id, '', public_predicate::class);
        $this->assertNull($forsite->redirect);
        $this->assertTrue($forsite->canview);
    }

    /**
     * The legacy script's slug form is moved to the route before anything is looked up, for everybody.
     *
     * Zero database statements for a category that exists and for one that does not, for a visitor and
     * for a logged-in user, and the predicate never asked: the redirect is the same for every id and
     * every slug, so it tells nobody anything, and the guard order runs where it lands. The controls
     * are the id form, which has no route and is decided here, and the same slug form with the router
     * off, which is decided here too — and whose lookup the meter sees.
     *
     * @return void
     */
    public function test_the_legacy_slug_form_moves_to_the_route_before_any_lookup(): void {
        global $DB;

        $this->resetAfterTest();
        $this->set_router(true);
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;
        $user = $this->getDataGenerator()->create_user();

        // Warm the class loader and the router, so that nothing measured below is a first use.
        request::category($missing, 0, 'handbook', public_predicate::class, legacy: true);

        foreach (['visitor' => null, 'user' => $user] as $who => $viewer) {
            $this->setUser($viewer);
            foreach ([$categoryid, $missing] as $id) {
                public_predicate::reset([]);
                $before = $DB->perf_get_reads();
                $answer = request::category($id, 0, 'handbook', public_predicate::class, legacy: true);
                $reads = $DB->perf_get_reads() - $before;

                $this->assertSame(0, $reads, "{$who}, category {$id}: no statement before the redirect");
                $this->assertSame(0, public_predicate::calls(), "{$who}, category {$id}: nobody was asked about");
                $this->assertSame(request::STATUS_SEE_OTHER, $answer->status);
                $this->assertSame(links::category_page($id, 'handbook')->out(false), $answer->redirect->out(false));
                $this->assertNull($answer->page);
            }
        }

        // Control: the id form has no route, so it is decided here and renders for the user.
        $this->fresh_page();
        $byid = request::category($categoryid, (int) $page->id, '', public_predicate::class, legacy: true);
        $this->assertNull($byid->redirect, 'The id form is not moved.');
        $this->assertTrue($byid->canview);

        // Control: with the router off the slug form is decided here, and the meter sees its lookup.
        $this->set_router(false);
        $this->fresh_page();
        $before = $DB->perf_get_reads();
        $script = request::category($categoryid, 0, 'handbook', public_predicate::class, legacy: true);
        $this->assertGreaterThan(0, $DB->perf_get_reads() - $before, 'Control: a lookup is counted.');
        $this->assertNull($script->redirect);
        $this->assertTrue($script->canview);
        $this->assertSame(links::legacy_category($categoryid, 'handbook')->out(false), $script->canonical->out(false));
    }

    /**
     * The canonical a page is rendered with: upstream's for a site page, the category address for a category page.
     *
     * @return void
     */
    public function test_the_canonical_address_of_each_kind_of_page(): void {
        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $categorypage = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $sitepage = $this->pages()->create_page(['menuname' => 'welcome']);
        $this->setUser($this->getDataGenerator()->create_user());

        // With the router, the route itself: what the head tags and og:url carry.
        $this->set_router(true);
        $routed = request::category($categoryid, 0, 'handbook', public_predicate::class);
        $this->assertSame(links::page($categorypage)->out(false), $routed->canonical->out(false));
        $this->assertStringContainsString('/local_page/category/', $routed->canonical->out(false));

        // Site-wide pages keep upstream's canonical, byte for byte, router or not.
        foreach ([false, true] as $configured) {
            $this->set_router($configured);
            $this->fresh_page();
            $bymenuname = request::legacy(0, 'welcome', public_predicate::class);
            $this->assertSame((new \moodle_url('/welcome'))->out(false), $bymenuname->canonical->out(false));
            $this->fresh_page();
            $byid = request::legacy((int) $sitepage->id, '', public_predicate::class);
            $expected = new \moodle_url('/local/page/index.php', ['id' => (int) $sitepage->id]);
            $this->assertSame($expected->out(false), $byid->canonical->out(false));
        }

        // A category page read on the script, the router being off: its category address.
        $this->set_router(false);
        $this->fresh_page();
        $byid = request::legacy((int) $categorypage->id, '', public_predicate::class);
        $this->assertSame(links::legacy_category($categoryid, 'handbook')->out(false), $byid->canonical->out(false));
    }
}
