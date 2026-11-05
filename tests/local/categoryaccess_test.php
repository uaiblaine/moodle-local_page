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
 * Tests for the logged-in category adapter.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use local_page\tests\discoverable_predicate;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \local_page\local\categoryaccess.
 *
 * Every refusal is asserted beside the same user reaching a category the test changed in one respect
 * only, because an adapter that answers "no" to everything passes every refusal. The unlisted half
 * is driven through \local_page\tests\discoverable_predicate, since local_unlistedcourses need not be
 * installed; the one test that reads the real predicate skips itself where the plugin is absent.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(categoryaccess::class)]
final class categoryaccess_test extends \advanced_testcase {
    /**
     * Every test writes categories and roles, and starts with a double that withholds nothing.
     *
     * @return void
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        discoverable_predicate::reset();
    }

    /**
     * A fresh user holding one capability at one context.
     *
     * @param string $capability Capability name
     * @param \core\context $context Context to grant and assign the role at
     * @return \stdClass The user record
     */
    private function user_holding_at(string $capability, \core\context $context): \stdClass {
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $this->getDataGenerator()->create_role_capability($roleid, [$capability => 'allow'], $context);
        role_assign($roleid, $user->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        return $user;
    }

    /**
     * The root, a missing id and a deleted category are refused; a visible one is the control.
     *
     * @return void
     */
    public function test_a_category_that_is_not_there_is_refused(): void {
        global $DB;

        $visible = $this->getDataGenerator()->create_category();
        $deleted = $this->getDataGenerator()->create_category();
        $deletedid = (int) $deleted->id;
        $deleted->delete_full(false);
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;
        $this->setUser($this->getDataGenerator()->create_user());

        $this->assertTrue(categoryaccess::is_reachable((int) $visible->id, discoverable_predicate::class), 'control');
        foreach (['root' => 0, 'missing' => $missing, 'deleted' => $deletedid] as $label => $categoryid) {
            $this->assertFalse(categoryaccess::is_reachable($categoryid, discoverable_predicate::class), $label);
        }
    }

    /**
     * A hidden category is withheld from a plain user and reached by one who may see hidden categories there.
     *
     * @return void
     */
    public function test_a_hidden_category_is_reached_only_with_the_capability_core_asks_for(): void {
        $visible = (int) $this->getDataGenerator()->create_category()->id;
        $hidden = (int) $this->getDataGenerator()->create_category(['visible' => 0])->id;

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertTrue(categoryaccess::is_reachable($visible, discoverable_predicate::class), 'plain user, visible');
        $this->assertFalse(categoryaccess::is_reachable($hidden, discoverable_predicate::class), 'plain user, hidden');

        $this->setUser($this->user_holding_at(
            'moodle/category:viewhiddencategories',
            \core\context\coursecat::instance($hidden)
        ));
        $this->assertTrue(categoryaccess::is_reachable($hidden, discoverable_predicate::class), 'viewhiddencategories');
    }

    /**
     * Whoever may author a category's pages reaches them while the category is hidden or unlisted.
     *
     * @return void
     */
    public function test_an_author_of_the_categorys_pages_reaches_it_whatever_its_state(): void {
        $hidden = (int) $this->getDataGenerator()->create_category(['visible' => 0])->id;
        $unlisted = (int) $this->getDataGenerator()->create_category()->id;
        discoverable_predicate::reset([$unlisted]);

        foreach (['hidden' => $hidden, 'unlisted' => $unlisted] as $label => $categoryid) {
            $context = \core\context\coursecat::instance($categoryid);
            $this->setUser($this->getDataGenerator()->create_user());
            $this->assertFalse(categoryaccess::is_reachable($categoryid, discoverable_predicate::class), "{$label}: plain user");

            $this->setUser($this->user_holding_at('local/page:managecategorypages', $context));
            $this->assertTrue(categoryaccess::is_reachable($categoryid, discoverable_predicate::class), "{$label}: author");
        }
    }

    /**
     * An unlisted category is withheld where the predicate says so, and the predicate is asked of visible ones only.
     *
     * @return void
     */
    public function test_the_discoverability_predicate_decides_a_visible_category(): void {
        $listed = (int) $this->getDataGenerator()->create_category()->id;
        $unlisted = (int) $this->getDataGenerator()->create_category()->id;
        $hidden = (int) $this->getDataGenerator()->create_category(['visible' => 0])->id;
        $this->setUser($this->getDataGenerator()->create_user());

        discoverable_predicate::reset([$unlisted, $hidden]);
        $this->assertTrue(categoryaccess::is_reachable($listed, discoverable_predicate::class), 'listed');
        $this->assertFalse(categoryaccess::is_reachable($unlisted, discoverable_predicate::class), 'unlisted');
        $this->assertSame(2, discoverable_predicate::calls(), 'Both visible categories were asked about.');

        $this->assertFalse(categoryaccess::is_reachable($hidden, discoverable_predicate::class), 'hidden');
        $this->assertSame(2, discoverable_predicate::calls(), 'Core refused the hidden one before the predicate.');
    }

    /**
     * Without local_unlistedcourses nothing is unlisted, so core's answer stands alone.
     *
     * @return void
     */
    public function test_a_missing_predicate_leaves_core_to_decide(): void {
        $visible = (int) $this->getDataGenerator()->create_category()->id;
        $hidden = (int) $this->getDataGenerator()->create_category(['visible' => 0])->id;
        $this->setUser($this->getDataGenerator()->create_user());

        $absent = '\\local_page\\tests\\no_such_predicate';
        $this->assertTrue(categoryaccess::is_reachable($visible, $absent), 'visible: reached');
        $this->assertFalse(categoryaccess::is_reachable($hidden, $absent), 'hidden: still refused');
    }

    /**
     * The real predicate: an unlisted category is withheld from a user with no relationship to it.
     *
     * @return void
     */
    public function test_the_real_predicate_withholds_an_unlisted_category(): void {
        if (!class_exists(categoryaccess::PREDICATE)) {
            $this->markTestSkipped('local_unlistedcourses is not installed.');
        }
        $state = \local_unlistedcourses\category_discoverability::class;
        $access = categoryaccess::PREDICATE;

        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $user = $this->getDataGenerator()->create_user();
        $access::reset_caches();

        $this->setUser($user);
        $this->assertTrue(categoryaccess::is_reachable($categoryid), 'control: listed');

        $this->setAdminUser();
        $state::set_state($categoryid, $state::STATE_UNLISTED);
        $access::reset_caches();

        $this->setUser($user);
        $this->assertFalse(categoryaccess::is_reachable($categoryid), 'unlisted');
    }
}
