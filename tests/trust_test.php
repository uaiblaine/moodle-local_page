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
 * Tests for the trust, head-scope and publishing rules of a category page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');

/**
 * Tests for the five functions that decide what a category page may contain and who may see it.
 *
 * The whole stage rests on one asymmetry, so it is worth stating before the assertions. A
 * SITE-WIDE page is written by a holder of local/page:addpages, which is declared RISK_XSS
 * precisely because such a page carries arbitrary markup; its content is rendered trusted and
 * uncleaned, exactly as upstream wrote it, and nothing here narrows that. A CATEGORY page is
 * written by somebody a site delegated one programme's pages to, and its content goes through
 * core's trusttext rules instead — cleaned unless the author was trusted, with
 * $CFG->enabletrusttext (off by default) and $CFG->forceclean deciding on top.
 *
 * Every test therefore carries the site-wide case as its control: a rule that had simply started
 * cleaning everything would pass the category assertions on its own.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_page_content_trust')]
#[CoversFunction('local_page_render_content')]
#[CoversFunction('local_page_editable_content')]
#[CoversFunction('local_page_head_html')]
#[CoversFunction('local_page_apply_publish_gate')]
final class trust_test extends \advanced_testcase {
    /** @var string Body HTML carrying something only an uncleaned render keeps. */
    private const DIRTY = '<p>Body</p><script>alert(1)</script>';

    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A fresh user holding exactly one capability, at one context and nowhere else.
     *
     * @param string $capability Capability name, e.g. moodle/site:trustcontent.
     * @param \core\context $context Context to grant and assign the role at.
     * @return \stdClass The user record.
     */
    private function user_holding_at(string $capability, \core\context $context): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->create_role_capability(
            $roleid,
            [$capability => 'allow'],
            $context
        );
        role_assign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $user;
    }

    /**
     * Trust needs the site setting AND the capability, at the page's own context.
     *
     * The three refusals each carry the control that isolates them: the capability really is held
     * when the setting is what answers, and the setting really is on when the context is.
     *
     * @return void
     */
    public function test_content_trust_needs_the_setting_and_the_capability_in_this_context(): void {
        global $CFG;

        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);
        $trusted = $this->user_holding_at('moodle/site:trustcontent', $context);
        $plain = $this->getDataGenerator()->create_user();

        $CFG->enabletrusttext = 1;

        $this->setUser($trusted);
        $this->assertSame(1, \local_page_content_trust($context), 'trusted author, setting on');

        $this->setUser($plain);
        $this->assertSame(0, \local_page_content_trust($context), 'author without the capability');

        /*
         * With the feature switched off nobody is trusted, whoever they are — which is the state
         * every Moodle site is in until an administrator changes it. The control is the assertion
         * that the capability is still held, so what answers below is the setting.
         */
        $CFG->enabletrusttext = 0;
        $this->setUser($trusted);
        $this->assertTrue(
            has_capability('moodle/site:trustcontent', $context),
            'control: the capability is still held'
        );
        $this->assertSame(0, \local_page_content_trust($context), 'trusted author, setting off');

        /*
         * And trust does not travel upwards: being trusted in one category does not make somebody
         * a trusted author of the site's own pages. Setting back on, so the refusal is the context.
         */
        $CFG->enabletrusttext = 1;
        $this->assertSame(1, \local_page_content_trust($context), 'control: trusted in the category');
        $this->assertSame(
            0,
            \local_page_content_trust(\context_system::instance()),
            'the same author at the system context'
        );
    }

    /**
     * A category page's content is cleaned unless its author was trusted, and core decides the rest.
     *
     * Both stored HTML fields go through the same call, which is the change: the Content HTML block
     * of a category page is no longer concatenated raw.
     *
     * @return void
     */
    public function test_render_content_cleans_a_category_page_unless_its_author_was_trusted(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $trustedpage = $this->pages()->create_category_page((int) $category->id, [
            'pagecontent' => self::DIRTY,
            'contenthtml' => self::DIRTY,
            'contenttrust' => 1,
        ]);
        $untrustedpage = $this->pages()->create_category_page((int) $category->id, [
            'pagecontent' => self::DIRTY,
            'contenthtml' => self::DIRTY,
            'contenttrust' => 0,
        ]);

        $CFG->enabletrusttext = 1;

        foreach (['pagecontent', 'contenthtml'] as $field) {
            $this->assertStringContainsString(
                '<script',
                \local_page_render_content($trustedpage, (string) $trustedpage->$field),
                "trusted author: {$field}"
            );
            $this->assertStringNotContainsString(
                '<script',
                \local_page_render_content($untrustedpage, (string) $untrustedpage->$field),
                "untrusted author: {$field}"
            );
        }

        // With the feature off the stored flag means nothing: core trusts nobody.
        $CFG->enabletrusttext = 0;
        $this->assertStringNotContainsString(
            '<script',
            \local_page_render_content($trustedpage, (string) $trustedpage->pagecontent),
            'trusted flag, enabletrusttext off'
        );

        // And $CFG->forceclean beats a trusted author outright.
        $CFG->enabletrusttext = 1;
        $CFG->forceclean = 1;
        $this->assertStringNotContainsString(
            '<script',
            \local_page_render_content($trustedpage, (string) $trustedpage->pagecontent),
            'trusted flag, forceclean on'
        );
    }

    /**
     * A site-wide page is rendered exactly as upstream renders it, under every one of those settings.
     *
     * This is the control for the test above: it keeps its markup with the trust flag at 0 and with
     * the trust feature switched off, which are the two states that strip a category page. The one
     * setting it does obey is $CFG->forceclean, and that is core's own rule — format_text() applies
     * it over 'noclean' (lib/classes/formatting.php:195) — asserted here so that a later reading of
     * "unconditionally" cannot go looking for a bug that is not there.
     *
     * @return void
     */
    public function test_render_content_leaves_a_site_wide_page_alone(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page([
            'pagecontent' => self::DIRTY,
            'contenttrust' => 0,
        ]);

        $CFG->enabletrusttext = 0;
        $this->assertStringContainsString(
            '<script',
            \local_page_render_content($page, (string) $page->pagecontent),
            'site-wide page, trust flag 0 and the feature off'
        );

        $CFG->enabletrusttext = 1;
        $this->assertStringContainsString(
            '<script',
            \local_page_render_content($page, (string) $page->pagecontent),
            'site-wide page, the feature on'
        );

        $CFG->forceclean = 1;
        $this->assertStringNotContainsString(
            '<script',
            \local_page_render_content($page, (string) $page->pagecontent),
            'site-wide page under forceclean, which is core cleaning everything'
        );
    }

    /**
     * An untrusted editor reopening a trusted category page gets cleaned text.
     *
     * Without this the rendering rule undoes itself: the script the viewer never sees arrives in
     * the untrusted editor's form, and saving the page unchanged stores it again under their own
     * flag — where it is cleaned on the way out, but has been carried across a trust boundary in
     * the meantime.
     *
     * @return void
     */
    public function test_editable_content_cleans_for_an_editor_who_is_not_trusted(): void {
        global $CFG;

        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);
        $trusted = $this->user_holding_at('moodle/site:trustcontent', $context);
        $untrusted = $this->getDataGenerator()->create_user();

        $page = $this->pages()->create_category_page((int) $category->id, [
            'pagecontent' => self::DIRTY,
            'contenthtml' => self::DIRTY,
            'contenttrust' => 1,
        ]);

        $CFG->enabletrusttext = 1;

        $this->setUser($trusted);
        foreach (['pagecontent', 'contenthtml'] as $field) {
            $this->assertSame(
                self::DIRTY,
                \local_page_editable_content($page, $field, $context),
                "trusted editor: {$field} comes back untouched"
            );
        }

        $this->setUser($untrusted);
        foreach (['pagecontent', 'contenthtml'] as $field) {
            $this->assertStringNotContainsString(
                '<script',
                \local_page_editable_content($page, $field, $context),
                "untrusted editor: {$field} is cleaned"
            );
        }

        /*
         * The stored flag is the other half of the same rule: a page whose author was NOT trusted
         * is cleaned before even a trusted editor sees it, which is what stops a page written
         * while the feature was off from being blessed by whoever opens it next.
         */
        $storeduntrusted = $this->pages()->create_category_page((int) $category->id, [
            'pagecontent' => self::DIRTY,
            'contenttrust' => 0,
        ]);
        $this->setUser($trusted);
        $this->assertStringNotContainsString(
            '<script',
            \local_page_editable_content($storeduntrusted, 'pagecontent', $context),
            'trusted editor, untrusted stored flag'
        );

        /*
         * Control: a site-wide page is handed back unchanged to both of them, so the cleaning
         * above is the category rule and not this function cleaning everything it is given.
         */
        $sitepage = $this->pages()->create_page(['pagecontent' => self::DIRTY, 'contenttrust' => 0]);
        $system = \context_system::instance();
        foreach ([$trusted, $untrusted] as $index => $user) {
            $this->setUser($user);
            $this->assertSame(
                self::DIRTY,
                \local_page_editable_content($sitepage, 'pagecontent', $system),
                "site-wide page, editor {$index}"
            );
        }
    }

    /**
     * The per-page head HTML belongs to site-wide pages and to the setting, and to nothing else.
     *
     * @return void
     */
    public function test_head_html_is_withheld_from_a_category_page(): void {
        $this->resetAfterTest();

        $head = '<meta name="verification" content="abc">';
        $category = $this->getDataGenerator()->create_category();
        $sitepage = $this->pages()->create_page(['meta' => $head]);
        $categorypage = $this->pages()->create_category_page((int) $category->id, ['meta' => $head]);

        set_config('additionalhead', 1, 'local_page');

        // Control: with the setting on a site-wide page does contribute its head HTML.
        $this->assertSame($head, \local_page_head_html($sitepage), 'site-wide page, setting on');

        // A category page does not, even though the row holds exactly the same value.
        $this->assertSame('', \local_page_head_html($categorypage), 'category page, setting on');

        // And with the setting off nobody does.
        set_config('additionalhead', 0, 'local_page');
        $this->assertSame('', \local_page_head_html($sitepage), 'site-wide page, setting off');
        $this->assertSame('', \local_page_head_html($categorypage), 'category page, setting off');
    }

    /**
     * A category page saved without the publishing capability is stored for logged-in users only.
     *
     * @return void
     */
    public function test_the_publish_gate_forces_a_category_page_to_logged_in_visitors(): void {
        $this->resetAfterTest();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $ctxa = \core\context\coursecat::instance($cata->id);
        $ctxb = \core\context\coursecat::instance($catb->id);

        $author = $this->user_holding_at('local/page:managecategorypages', $ctxa);
        $publisher = $this->user_holding_at('local/page:publishcategorypages', $ctxa);

        // An author who may write the page but not publish it: the posted 0 becomes a stored 1.
        $this->setUser($author);
        $this->assertSame(
            1,
            (int) \local_page_apply_publish_gate((object) ['onlyloggedin' => 0], $ctxa)->onlyloggedin,
            'author without the publishing capability'
        );

        // Control: the holder's own 0 survives, so the forcing above is the capability.
        $this->setUser($publisher);
        $this->assertSame(
            0,
            (int) \local_page_apply_publish_gate((object) ['onlyloggedin' => 0], $ctxa)->onlyloggedin,
            'publisher in their own category'
        );

        /*
         * Control, and the one that makes the capability contextual rather than global: the same
         * publisher is forced in the category next door, where they hold nothing.
         */
        $this->assertSame(
            1,
            (int) \local_page_apply_publish_gate((object) ['onlyloggedin' => 0], $ctxb)->onlyloggedin,
            'publisher in a sibling category'
        );

        /*
         * Control: the system context is untouched, so the gate is the category rule and not a
         * blanket refusal. A site-wide page is governed by local/page:addpages alone, as upstream
         * has it — asserted as the author, who does not hold the publishing capability anywhere.
         */
        $this->setUser($author);
        $this->assertSame(
            0,
            (int) \local_page_apply_publish_gate((object) ['onlyloggedin' => 0], \context_system::instance())->onlyloggedin,
            'the system context'
        );

        // A record already restricted stays restricted, whoever saves it.
        $this->setUser($publisher);
        $this->assertSame(
            1,
            (int) \local_page_apply_publish_gate((object) ['onlyloggedin' => 1], $ctxa)->onlyloggedin,
            'a page the publisher restricted on purpose'
        );
    }
}
