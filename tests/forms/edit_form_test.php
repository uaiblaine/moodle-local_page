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
     * @return \pages_edit_product_form
     */
    private function form(): \pages_edit_product_form {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        if (!$PAGE->has_set_url()) {
            $PAGE->set_url(new \moodle_url('/local/page/edit.php'));
        }

        return new \pages_edit_product_form(false);
    }

    /**
     * Runs validation() over one set of submitted values.
     *
     * @param array $data Submitted values; the three fields validation() reads default to empty.
     * @return array Errors keyed by element name.
     */
    private function errors(array $data): array {
        $data += ['accesslevel' => '', 'menuname' => '', 'id' => 0];

        return $this->form()->validation($data, []);
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
}
