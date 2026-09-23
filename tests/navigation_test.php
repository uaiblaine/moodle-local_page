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
 * Tests for the category entry point and the URLs every screen is built from.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use core\navigation\navigation_node;
use PHPUnit\Framework\Attributes\CoversFunction;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/page/lib.php');

/**
 * Tests for the navigation callback and the two URL builders.
 *
 * The node is the only way a category manager reaches their pages at all: the admin tree's
 * "Manage pages" entry is gated on local/page:addpages, which they do not hold. So the assertions
 * here are about delegation rather than about decoration — a node offered at the wrong category,
 * or to somebody holding nothing, is an invitation to a screen that will refuse them.
 *
 * Every negative assertion carries its own control, because a callback that adds nothing at all
 * satisfies all three of them at once.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversFunction('local_page_extend_navigation_category_settings')]
#[CoversFunction('local_page_list_url')]
#[CoversFunction('local_page_edit_url')]
final class navigation_test extends \advanced_testcase {
    /**
     * A fresh user holding exactly one capability, at one context and nowhere else.
     *
     * @param string $capability Capability name, e.g. local/page:managecategorypages.
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
     * The node the callback would add to a category's settings menu, or false.
     *
     * A bare navigation_node stands in for the category node core builds: the callback only ever
     * calls add() on it, and what this asks about is whether it did.
     *
     * @param \core\context $context Category context the menu is being built for.
     * @return navigation_node|false The added node, or false when the callback added nothing.
     */
    private function node_for(\core\context $context) {
        $menu = new navigation_node(['text' => 'Category']);
        \local_page_extend_navigation_category_settings($menu, $context);

        return $menu->get('local_page_pages');
    }

    /**
     * A manager of the category is offered its pages, at the address the listing screen answers on.
     *
     * @return void
     */
    public function test_the_node_is_offered_to_a_manager_of_that_category(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);
        $this->setUser($this->user_holding_at('local/page:managecategorypages', $context));

        $node = $this->node_for($context);

        $this->assertInstanceOf(navigation_node::class, $node);
        $this->assertSame(get_string('categorypages', 'local_page'), (string) $node->text);
        $this->assertSame(navigation_node::TYPE_SETTING, $node->type);
        $this->assertSame(
            \local_page_list_url($context)->out(false),
            $node->action()->out(false)
        );
        // The address carries the category, or it lists the site's pages instead of this one's.
        $this->assertSame((int) $context->id, (int) $node->action()->get_param('contextid'));
    }

    /**
     * Somebody holding nothing here is offered nothing.
     *
     * The control is the same user in the same category once the capability is granted: without it
     * this test would pass against a callback that never adds a node at all.
     *
     * @return void
     */
    public function test_the_node_is_withheld_from_a_user_who_holds_nothing(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse($this->node_for($context), 'a user holding nothing was offered the node');

        // Control: the force that adds the node is switched on for the same category.
        $this->setUser($this->user_holding_at('local/page:managecategorypages', $context));
        $this->assertInstanceOf(navigation_node::class, $this->node_for($context));
    }

    /**
     * Holding the capability in one category does not offer the node in another.
     *
     * This is the assertion the whole delegation rests on: the capability is read at the category
     * whose menu is being built, not at the system context and not at the user's own category.
     *
     * @return void
     */
    public function test_the_node_is_withheld_at_a_category_the_manager_does_not_hold(): void {
        $this->resetAfterTest();

        $own = \core\context\coursecat::instance($this->getDataGenerator()->create_category()->id);
        $other = \core\context\coursecat::instance($this->getDataGenerator()->create_category()->id);
        $this->setUser($this->user_holding_at('local/page:managecategorypages', $own));

        $this->assertFalse($this->node_for($other), 'a manager of one category was offered another');

        // Control: the same user, the same callback, in the category they actually manage.
        $this->assertInstanceOf(navigation_node::class, $this->node_for($own));
    }

    /**
     * A context level this menu never runs at is ignored rather than refused.
     *
     * Core calls the callback with a category context only, but a guard that throws would turn any
     * future caller's mistake into a fatal page rather than a missing link.
     *
     * @return void
     */
    public function test_a_context_that_is_not_a_category_adds_nothing(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->assertFalse($this->node_for(\core\context\system::instance()));

        $course = $this->getDataGenerator()->create_course();
        $this->assertFalse($this->node_for(\core\context\course::instance($course->id)));
    }

    /**
     * The listing address names a category and says nothing at all about the system context.
     *
     * @return void
     */
    public function test_the_listing_address_names_a_category_and_only_a_category(): void {
        $this->resetAfterTest();

        $system = \core\context\system::instance();
        $this->assertSame([], \local_page_list_url($system)->params());

        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);
        $this->assertSame(
            ['contextid' => (string) $context->id],
            \local_page_list_url($context)->params()
        );
    }

    /**
     * The editor's address: an id for a page that exists, a category for one that does not yet.
     *
     * @return void
     */
    public function test_the_editor_address_names_the_page_or_the_category_it_will_belong_to(): void {
        $this->resetAfterTest();

        $system = \core\context\system::instance();
        $category = $this->getDataGenerator()->create_category();
        $context = \core\context\coursecat::instance($category->id);

        // A new site-wide page: upstream's bare address, unchanged.
        $this->assertSame([], \local_page_edit_url($system)->params());
        $this->assertSame(['id' => '7'], \local_page_edit_url($system, 7)->params());

        // A new category page carries the category id, which is what edit.php's ?category= reads.
        $this->assertSame(
            ['category' => (string) $category->id],
            \local_page_edit_url($context)->params()
        );

        /*
         * An existing category page carries its id and nothing else: edit.php reads the stored row
         * for the context, so a category parameter beside the id would be a second opinion the row
         * has already settled.
         */
        $this->assertSame(['id' => '7'], \local_page_edit_url($context, 7)->params());
    }

    /**
     * Neither address can be built for a context level the plugin does not author in.
     *
     * @return void
     */
    public function test_a_context_level_the_plugin_does_not_use_has_no_addresses(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \core\context\course::instance($course->id);

        try {
            \local_page_list_url($context);
            $this->fail('a course context produced a listing address');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('local_page', $e->getMessage());
        }

        try {
            \local_page_edit_url($context, 7);
            $this->fail('a course context produced an editor address');
        } catch (\coding_exception $e) {
            $this->assertStringContainsString('local_page', $e->getMessage());
        }
    }
}
