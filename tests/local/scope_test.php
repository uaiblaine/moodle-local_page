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
 * Tests for the context dimension of a page.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for \local_page\local\scope.
 *
 * Two things are worth stating before reading the assertions. The stored 0 means the system
 * context and is resolved in code, because a context id is a row id that differs between sites and
 * cannot be written into an XMLDB default; and the mapping from a context to a capability is what
 * decides who may author and preview a page, so it is pinned at every level this plugin accepts
 * plus one it does not.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(scope::class)]
final class scope_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A row that names no context is a site-wide page.
     *
     * Every row written before this stage is in exactly this state, which is why the convention
     * exists at all: the column defaults to 0 and no migration touches a single row.
     *
     * @return void
     */
    public function test_a_row_with_no_context_of_its_own_is_a_system_page(): void {
        $this->resetAfterTest();

        $system = \core\context\system::instance();
        $page = $this->pages()->create_page();

        $this->assertSame((int) $system->id, (int) scope::context($page)->id);
        $this->assertFalse(scope::is_category($page));

        // The same answer for a row object that carries no contextid property at all.
        $legacy = clone $page;
        unset($legacy->contextid);
        $this->assertSame((int) $system->id, (int) scope::context($legacy)->id);
        $this->assertFalse(scope::is_category($legacy));
    }

    /**
     * A category row resolves to that category's context.
     *
     * @return void
     */
    public function test_a_category_row_resolves_to_its_own_category_context(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \core\context\coursecat::instance($category->id);

        $page = $this->pages()->create_category_page((int) $category->id);

        $this->assertSame((int) $categorycontext->id, (int) scope::context($page)->id);
        $this->assertSame(CONTEXT_COURSECAT, scope::context($page)->contextlevel);
        $this->assertSame((int) $category->id, (int) scope::context($page)->instanceid);
        $this->assertTrue(scope::is_category($page));

        /*
         * Control: a site-wide page created in the same test answers differently, so what is
         * asserted above is the stored column and not something every row would produce.
         */
        $this->assertFalse(scope::is_category($this->pages()->create_page()));
    }

    /**
     * A stored id naming no context at all is refused rather than silently read as the system.
     *
     * @return void
     */
    public function test_a_context_id_that_names_nothing_is_refused(): void {
        global $DB;

        $this->resetAfterTest();

        $highest = (int) $DB->get_field_sql('SELECT MAX(id) FROM {context}');
        $orphan = (object) ['contextid' => $highest + 1000];

        $this->expectException(\dml_missing_record_exception::class);
        scope::context($orphan);
    }

    /**
     * Each accepted context level maps to its own capability.
     *
     * Authoring a site-wide page and authoring a category's pages are different powers held by
     * different people; a single capability checked at two contexts would make the second a
     * consequence of the first.
     *
     * @return void
     */
    public function test_each_context_level_maps_to_its_own_capability(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();

        $this->assertSame(
            'local/page:addpages',
            scope::capability(\core\context\system::instance())
        );
        $this->assertSame(
            'local/page:managecategorypages',
            scope::capability(\core\context\coursecat::instance($category->id))
        );
    }

    /**
     * A context level this plugin does not store is a programming error, not a fallback.
     *
     * @return void
     */
    public function test_a_context_level_the_plugin_does_not_use_is_refused(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $coursecontext = \core\context\course::instance($course->id);

        $refusals = 0;
        try {
            scope::capability($coursecontext);
        } catch (\coding_exception $e) {
            $refusals++;
        }
        try {
            scope::stored_contextid($coursecontext);
        } catch (\coding_exception $e) {
            $refusals++;
        }

        $this->assertSame(2, $refusals, 'both directions refuse a course context');
    }

    /**
     * stored_contextid() is the inverse of context(), including the 0 convention.
     *
     * @return void
     */
    public function test_stored_contextid_is_the_inverse_of_context(): void {
        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();
        $categorycontext = \core\context\coursecat::instance($category->id);

        $this->assertSame(0, scope::stored_contextid(\core\context\system::instance()));
        $this->assertSame((int) $categorycontext->id, scope::stored_contextid($categorycontext));

        // Round trip: what the save path stores is what the read path resolves back to.
        foreach ([\core\context\system::instance(), $categorycontext] as $context) {
            $stored = (object) ['contextid' => scope::stored_contextid($context)];
            $this->assertSame((int) $context->id, (int) scope::context($stored)->id);
        }
    }

    /**
     * for_category() answers with the category's context, and refuses a category that is not there.
     *
     * @return void
     */
    public function test_for_category_refuses_a_category_that_does_not_exist(): void {
        global $DB;

        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();

        // Control: a real category resolves, so the refusal below is the missing row.
        $this->assertSame(
            (int) \core\context\coursecat::instance($category->id)->id,
            (int) scope::for_category((int) $category->id)->id
        );

        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;

        $this->expectException(\dml_missing_record_exception::class);
        scope::for_category($missing);
    }

    /**
     * Every capability this plugin declares has a language string.
     *
     * Not cosmetic on Moodle 5.x: get_capability_string() falls through to get_string() whenever
     * the component directory exists, the resulting developer debugging is rendered by Whoops, and
     * Whoops turns it into an uncaught ErrorException that terminates the request — so one missing
     * string takes down admin/roles/permissions.php mid-table for every context that lists the
     * capability. Nothing else in the pipeline cross-checks db/access.php against lang/en.
     *
     * @return void
     */
    public function test_every_declared_capability_has_its_string(): void {
        global $CFG;

        $this->resetAfterTest();

        $capabilities = [];
        require($CFG->dirroot . '/local/page/db/access.php');

        // Control: the file really did define some, so an empty loop cannot pass this test.
        $this->assertNotEmpty($capabilities);

        foreach (array_keys($capabilities) as $name) {
            $identifier = substr($name, strpos($name, '/') + 1);
            $this->assertTrue(
                get_string_manager()->string_exists($identifier, 'local_page'),
                "missing lang string {$identifier} for capability {$name}"
            );
        }
    }

    /**
     * The two language packs carry exactly the same keys.
     *
     * The fleet rule is that lang/en and lang/pt_br are updated in the same commit, and nothing in
     * any pipeline checks it: phpcs reads the ordering of each file on its own and never compares
     * the two. A key added to one and forgotten in the other surfaces as an English word in the
     * middle of a Portuguese screen, or — for a key only pt_br has — as a string nobody will ever
     * see, and both go unnoticed for as long as nobody looks.
     *
     * It sits beside the capability-string test above because that one is the same kind of check:
     * a cross-file assertion about the language pack, made where the file that needs it lives.
     *
     * @return void
     */
    public function test_the_two_language_packs_carry_the_same_keys(): void {
        global $CFG;

        $this->resetAfterTest();

        $string = [];
        require($CFG->dirroot . '/local/page/lang/en/local_page.php');
        $en = array_keys($string);

        $string = [];
        require($CFG->dirroot . '/local/page/lang/pt_br/local_page.php');
        $ptbr = array_keys($string);

        // Control: both files really did define something, so two empty lists cannot pass.
        $this->assertNotEmpty($en);
        $this->assertNotEmpty($ptbr);

        sort($en);
        sort($ptbr);
        $this->assertSame($en, $ptbr, 'lang/en and lang/pt_br are out of lockstep');
    }
}
