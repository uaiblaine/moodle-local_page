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
 * Tests for the server-side validation of the page edit form.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\forms;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/forms/edit.php');

/**
 * Tests for pages_edit_product_form::validation().
 *
 * validation() is called directly: it takes the submitted values and returns the errors, so no posted
 * request is needed.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(\pages_edit_product_form::class)]
final class edit_form_test extends \advanced_testcase {
    /**
     * The plugin's data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * Run validation() over one set of submitted values, on a form built the way edit.php builds one.
     *
     * @param array $data Submitted values; the fields validation() reads default to empty
     * @return array Errors keyed by element name
     */
    private function errors(array $data): array {
        global $PAGE;

        $PAGE->set_context(\context_system::instance());
        if (!$PAGE->has_set_url()) {
            $PAGE->set_url(new \moodle_url('/local/page/edit.php'));
        }

        $data += ['id' => 0, 'accesslevel' => '', 'menuname' => ''];

        return (new \pages_edit_product_form(false))->validation($data, []);
    }

    /**
     * An access level made only of negations is refused.
     *
     * @return void
     */
    public function test_an_access_level_of_negations_only_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $expected = get_string('accesslevel_negationonly', 'local_page');
        $this->assertSame($expected, $this->errors(['accesslevel' => '!moodle/site:config'])['accesslevel'] ?? null);
        $this->assertSame(
            $expected,
            $this->errors(['accesslevel' => '!moodle/site:config, !moodle/site:viewparticipants'])['accesslevel'] ?? null
        );
    }

    /**
     * A capability the site does not define is refused, and named in the message.
     *
     * @return void
     */
    public function test_an_unknown_capability_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $cases = [
            'a misspelt capability' => ['moodle/site:confg', 'moodle/site:confg'],
            'a misspelt negation beside a valid entry' => ['moodle/site:config, !not/a:capability', '!not/a:capability'],
            'a bare exclamation mark' => ['!', '!'],
        ];
        foreach ($cases as $label => [$accesslevel, $named]) {
            $errors = $this->errors(['accesslevel' => $accesslevel]);
            $expected = get_string('accesslevel_unknowncapability', 'local_page', $named);
            $this->assertSame($expected, $errors['accesslevel'] ?? null, $label);
        }
    }

    /**
     * Valid access levels are accepted: the controls for the two refusals above.
     *
     * @return void
     */
    public function test_valid_access_levels_are_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $valid = [
            'empty' => '',
            'one capability' => 'moodle/site:config',
            'a negation beside a positive capability' => 'moodle/site:viewparticipants, !moodle/site:config',
            'the same, negation first' => '!moodle/site:config, moodle/site:viewparticipants',
        ];
        foreach ($valid as $label => $accesslevel) {
            $this->assertArrayNotHasKey('accesslevel', $this->errors(['accesslevel' => $accesslevel]), $label);
        }
    }

    /**
     * A friendly URL another page that is not deleted already holds is refused.
     *
     * @return void
     */
    public function test_a_slug_another_page_holds_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->pages()->create_page(['menuname' => 'about']);

        $expected = get_string('menuname_taken', 'local_page');
        $this->assertSame($expected, $this->errors(['menuname' => 'about'])['menuname'] ?? null);
        $this->assertSame($expected, $this->errors(['menuname' => 'About'])['menuname'] ?? null, 'Capitals do not get round it.');

        // Control: a slug nobody holds is accepted, and so is an empty one (the save path names the page).
        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => 'contact']));
        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => '']));
    }

    /**
     * A page keeps its own friendly URL when it is saved again.
     *
     * @return void
     */
    public function test_a_page_may_keep_its_own_slug(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page(['menuname' => 'about']);
        $other = $this->pages()->create_page(['menuname' => 'contact']);

        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => 'about', 'id' => $page->id]));

        // Control: the same slug posted for a different page is still refused.
        $this->assertArrayHasKey('menuname', $this->errors(['menuname' => 'about', 'id' => $other->id]));
    }

    /**
     * Deleting a page, the way pages.php deletes one, releases its friendly URL.
     *
     * @return void
     */
    public function test_a_slug_is_released_when_its_page_is_deleted(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $page = $this->pages()->create_page(['menuname' => 'about']);

        // Control: while the page is live the slug is refused.
        $this->assertArrayHasKey('menuname', $this->errors(['menuname' => 'about']));

        $DB->update_record('local_page', (object) [
            'id' => $page->id,
            'deleted' => 1,
            'menuname' => \local_page\local\slug::deleted_name('about', $page->id),
        ]);

        $this->assertArrayNotHasKey('menuname', $this->errors(['menuname' => 'about']));
    }
}
