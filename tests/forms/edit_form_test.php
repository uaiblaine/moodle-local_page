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
 * Tests for the save-time validation on the page edit form.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\forms;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');
require_once($CFG->dirroot . '/local/page/forms/edit.php');

/**
 * Tests for pages_edit_product_form::validation().
 *
 * Two of this form's fields are rules rather than text — the access level is evaluated as a
 * capability expression and the friendly URL becomes a public address — and neither had any
 * server-side validation before this stage. The tests call validation() directly, which is the
 * method's declared contract on moodleform: it takes the submitted values and returns the errors,
 * so nothing here needs a posted request.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\pages_edit_product_form::class)]
final class edit_form_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A form instance built the way edit.php builds one for a new page.
     *
     * definition() reads $PAGE->theme for the layout list and initialises the editor, so the page
     * needs a context and a url before the form is constructed — a test process is not a request
     * and neither is set for free.
     *
     * @param \core\context|null $context Context the page being edited belongs to; system when omitted.
     * @return \pages_edit_product_form
     */
    private function form(?\core\context $context = null): \pages_edit_product_form {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        if (!$PAGE->has_set_url()) {
            $PAGE->set_url(new \moodle_url('/local/page/edit.php'));
        }

        return new \pages_edit_product_form(false, $context);
    }

    /**
     * The QuickForm behind a built form, so a test can ask what definition() actually added.
     *
     * moodleform keeps it protected and offers no accessor, and adding one to an upstream file for
     * the sake of a test is a change to the fork's surface for nothing. Reflection reads it without
     * touching the plugin — no setAccessible() call is needed, since PHP 8.1 made protected members
     * reachable through ReflectionProperty by default.
     *
     * @param \pages_edit_product_form $form A built form.
     * @return \MoodleQuickForm The form's own element collection.
     */
    private function mform(\pages_edit_product_form $form): \MoodleQuickForm {
        return (new \ReflectionProperty(\moodleform::class, '_form'))->getValue($form);
    }

    /**
     * A fresh user holding exactly one capability, at one context and nowhere else.
     *
     * @param string $capability Capability name, e.g. local/page:publishcategorypages.
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
     * Runs validation() over one set of submitted values.
     *
     * The contextid defaults to 0, the stored convention for the site-wide scope, which is what an
     * editor working on a site page posts.
     *
     * @param array $data Submitted values; the fields validation() reads default to empty.
     * @param \core\context|null $context Context the form was built for; system when omitted.
     * @return array Errors keyed by element name.
     */
    private function errors(array $data, ?\core\context $context = null): array {
        $data += ['accesslevel' => '', 'menuname' => '', 'id' => 0, 'contextid' => 0];

        return $this->form($context)->validation($data, []);
    }

    /**
     * An access level made only of negations is refused at save time.
     *
     * This is the hole tests/lib_test.php records on the read side: the predicate starts at "no
     * access" and a negated entry flips it to "access" for everyone who does not hold the
     * capability, which is every anonymous visitor. It is closed here, on the way in.
     *
     * @return void
     */
    public function test_an_access_level_of_negations_only_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $errors = $this->errors(['accesslevel' => '!moodle/site:config']);
        $this->assertArrayHasKey('accesslevel', $errors);
        $this->assertSame(get_string('accesslevel_negationonly', 'local_page'), $errors['accesslevel']);

        // Several negations are no better than one.
        $several = $this->errors(['accesslevel' => '!moodle/site:config, !moodle/site:viewparticipants']);
        $this->assertArrayHasKey('accesslevel', $several);
        $this->assertSame(get_string('accesslevel_negationonly', 'local_page'), $several['accesslevel']);
    }

    /**
     * A negation is fine as long as something positive stands beside it.
     *
     * This is the shape the field's own help text recommends, so refusing it would break the
     * documented use. It is also the control for the test above: it proves the refusal there is
     * the negation-only rule and not "any negation at all".
     *
     * @return void
     */
    public function test_a_negation_beside_a_positive_capability_is_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $errors = $this->errors([
            'accesslevel' => 'moodle/site:viewparticipants, !moodle/site:config',
        ]);
        $this->assertArrayNotHasKey('accesslevel', $errors);

        // Order does not matter; the rule is about the list, not about position.
        $reversed = $this->errors([
            'accesslevel' => '!moodle/site:config, moodle/site:viewparticipants',
        ]);
        $this->assertArrayNotHasKey('accesslevel', $reversed);
    }

    /**
     * A capability this site does not define is refused, and named in the message.
     *
     * A typo here does not fail loudly anywhere: has_capability() on an unknown name simply
     * answers false, so a misspelt positive entry silently locks everybody out of the page and a
     * misspelt negated one silently lets everybody in.
     *
     * @return void
     */
    public function test_an_unknown_capability_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $errors = $this->errors(['accesslevel' => 'moodle/site:confg']);
        $this->assertArrayHasKey('accesslevel', $errors);
        $this->assertStringContainsString('moodle/site:confg', $errors['accesslevel']);

        // The entry is named as the author typed it, negation included.
        $negated = $this->errors(['accesslevel' => 'moodle/site:config, !not/a:capability']);
        $this->assertArrayHasKey('accesslevel', $negated);
        $this->assertStringContainsString('!not/a:capability', $negated['accesslevel']);

        // A bare exclamation mark names no capability at all.
        $this->assertArrayHasKey('accesslevel', $this->errors(['accesslevel' => '!']));
    }

    /**
     * An empty access level is the default and must stay acceptable.
     *
     * @return void
     */
    public function test_an_empty_access_level_is_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertArrayNotHasKey('accesslevel', $this->errors([]));
        $this->assertArrayNotHasKey('accesslevel', $this->errors(['accesslevel' => '   ']));

        // A single positive capability, which is the common case.
        $this->assertArrayNotHasKey('accesslevel', $this->errors(['accesslevel' => 'moodle/site:config']));
    }

    /**
     * A friendly URL another live page already holds is refused.
     *
     * @return void
     */
    public function test_a_slug_another_live_page_holds_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->pages()->create_page(['menuname' => 'about']);

        $errors = $this->errors(['menuname' => 'about']);
        $this->assertArrayHasKey('menuname', $errors);
        $this->assertSame(get_string('menuname_taken', 'local_page'), $errors['menuname']);

        // The comparison is on the stored form, so capitals do not get round it.
        $this->assertArrayHasKey('menuname', $this->errors(['menuname' => 'About']));

        // Control: a slug nobody holds is accepted, so the refusals above are the collision.
        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => 'contact']));
    }

    /**
     * A page keeps its own slug when it is saved again.
     *
     * @return void
     */
    public function test_a_page_may_keep_its_own_slug(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page(['menuname' => 'about']);

        $this->assertArrayNotHasKey(
            'menuname',
            $this->errors(['menuname' => 'about', 'id' => (int) $page->id])
        );

        /*
         * Control: the same slug posted for a DIFFERENT page is still refused, so the acceptance
         * above is the id exclusion and not the check being switched off.
         */
        $other = $this->pages()->create_page(['menuname' => 'contact']);
        $this->assertArrayHasKey(
            'menuname',
            $this->errors(['menuname' => 'about', 'id' => (int) $other->id])
        );
    }

    /**
     * A slug held only by a deleted page is available again.
     *
     * @return void
     */
    public function test_a_slug_held_only_by_a_deleted_page_is_accepted(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page(['menuname' => 'about']);
        $id = (int) $page->id;

        // Control: while the page is live the slug is refused.
        $this->assertArrayHasKey('menuname', $this->errors(['menuname' => 'about']));

        // The delete pages.php performs: soft-delete and release the address in one update.
        $DB->update_record('local_page', (object) [
            'id' => $id,
            'deleted' => 1,
            'menuname' => \local_page\local\slug::deleted_name('about', $id),
        ]);

        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => 'about']));
    }

    /**
     * A friendly URL Moodle itself answers on is refused.
     *
     * The cost of accepting one is paid later and somewhere else: depending on how the site's
     * rewrite rules are ordered, either the page never answers or it shadows part of Moodle, and
     * neither is visible from this form.
     *
     * @return void
     */
    public function test_a_reserved_slug_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        foreach (['login', 'course', 'r', 'local_foo'] as $reserved) {
            $errors = $this->errors(['menuname' => $reserved]);
            $this->assertArrayHasKey('menuname', $errors, $reserved);
            $this->assertSame(get_string('menuname_reserved', 'local_page'), $errors['menuname'], $reserved);
        }

        // Control: an ordinary slug nobody holds is accepted, so the refusals above are the rule.
        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => 'about-us']));
    }

    /**
     * The same friendly URL is accepted in another category, and still refused in its own.
     *
     * @return void
     */
    public function test_a_slug_held_in_another_category_is_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $ctxa = \core\context\coursecat::instance($cata->id);
        $ctxb = \core\context\coursecat::instance($catb->id);

        $this->pages()->create_category_page((int) $cata->id, ['menuname' => 'contato']);

        // The other category may have a page of the same name.
        $this->assertArrayNotHasKey(
            'menuname',
            $this->errors(['menuname' => 'contato', 'contextid' => (int) $ctxb->id], $ctxb)
        );

        // So may the site as a whole.
        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => 'contato']));

        /*
         * Control: inside the category that already holds it the slug is still refused, so the two
         * acceptances above are the scoping and not the uniqueness check having been switched off.
         */
        $errors = $this->errors(['menuname' => 'contato', 'contextid' => (int) $ctxa->id], $ctxa);
        $this->assertArrayHasKey('menuname', $errors);
        $this->assertSame(get_string('menuname_taken', 'local_page'), $errors['menuname']);
    }

    /**
     * An empty friendly URL is acceptable; the save path names the page afterwards.
     *
     * @return void
     */
    public function test_an_empty_slug_is_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->pages()->create_page(['menuname' => '']);

        // Several pages may be slugless at once, so an empty value never collides.
        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => '']));
        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => '   ']));
    }

    /**
     * An editor who may not publish sees the field frozen at "Yes", and is told why.
     *
     * The freeze is not the enforcement — the save path applies local_page_apply_publish_gate()
     * whatever is posted — it is what stops the control from looking as though it worked. The
     * static line beside it is the half that makes the state explainable rather than mysterious.
     *
     * @return void
     */
    public function test_the_publishing_field_is_frozen_for_a_category_editor_who_may_not_publish(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);

        $this->setUser($this->user_holding_at('local/page:managecategorypages', $context));
        $mform = $this->mform($this->form($context));

        $this->assertTrue($mform->getElement('onlyloggedin')->isFrozen(), 'the field is frozen');
        /*
         * Frozen at "logged in only", and held there by a constant: MoodleQuickForm::exportValues()
         * merges the constants over everything else (formslib.php:2442), so this is the value the
         * form yields whatever arrives in the request.
         */
        $this->assertSame(
            ['1'],
            (array) $mform->getElementValue('onlyloggedin'),
            'the frozen value'
        );
        $this->assertSame('1', $mform->_constantValues['onlyloggedin'], 'held there by a constant');
        $this->assertTrue(
            $mform->elementExists('onlyloggedin_publishlocked'),
            'the explanation is on the form'
        );
    }

    /**
     * A holder of the publishing capability keeps the choice, and so does a site-wide editor.
     *
     * This is the control for the test above, in both directions: a rule that had simply frozen
     * the field for everybody would pass that one on its own, and a rule keyed on the capability
     * without the context would pass it while a sibling category's manager published freely.
     *
     * @return void
     */
    public function test_the_publishing_field_is_left_alone_for_a_publisher_and_for_a_site_page(): void {
        $this->resetAfterTest();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $ctxa = \core\context\coursecat::instance($cata->id);
        $ctxb = \core\context\coursecat::instance($catb->id);

        $publisher = $this->user_holding_at('local/page:publishcategorypages', $ctxa);
        $this->setUser($publisher);

        $mform = $this->mform($this->form($ctxa));
        $this->assertFalse($mform->getElement('onlyloggedin')->isFrozen(), 'publisher, own category');
        $this->assertFalse($mform->elementExists('onlyloggedin_publishlocked'), 'and no explanation');

        // The same publisher in the category next door, where they hold nothing.
        $sibling = $this->mform($this->form($ctxb));
        $this->assertTrue($sibling->getElement('onlyloggedin')->isFrozen(), 'publisher, sibling category');

        // A site-wide page is governed by local/page:addpages alone, as upstream has it.
        $this->setAdminUser();
        $system = $this->mform($this->form(\context_system::instance()));
        $this->assertFalse($system->getElement('onlyloggedin')->isFrozen(), 'site-wide page');
        $this->assertFalse($system->elementExists('onlyloggedin_publishlocked'), 'and no explanation');
    }

    /**
     * The per-page head HTML field is offered on a site-wide page only.
     *
     * No sanitiser exists for head markup, so the field stays with the capability that is declared
     * RISK_XSS for carrying exactly that. The setting is the control: with it off the field is
     * absent everywhere, so its absence in a category is the scope rule and not the setting.
     *
     * @return void
     */
    public function test_the_head_html_field_is_offered_on_a_site_wide_page_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);

        set_config('additionalhead', 1, 'local_page');

        $this->assertTrue(
            $this->mform($this->form(\context_system::instance()))->elementExists('meta'),
            'site-wide page, setting on'
        );
        $this->assertFalse(
            $this->mform($this->form($context))->elementExists('meta'),
            'category page, setting on'
        );

        // Control: with the setting off even the site-wide form has no such field.
        set_config('additionalhead', 0, 'local_page');
        $this->assertFalse(
            $this->mform($this->form(\context_system::instance()))->elementExists('meta'),
            'site-wide page, setting off'
        );
    }
}
