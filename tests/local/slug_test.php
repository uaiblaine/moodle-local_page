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
 * Tests for the friendly-URL slug rules.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for \local_page\local\slug.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(slug::class)]
final class slug_test extends \advanced_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * The stored slug of one page.
     *
     * @param int $pageid Page id.
     * @return string
     */
    private function stored_slug(int $pageid): string {
        global $DB;

        return (string) $DB->get_field('local_page', 'menuname', ['id' => $pageid], MUST_EXIST);
    }

    /**
     * The context id of a course category, as the column stores it.
     *
     * @param int $categoryid Course category id.
     * @return int
     */
    private function category_scope(int $categoryid): int {
        return (int) \core\context\coursecat::instance($categoryid)->id;
    }

    /**
     * A slug is taken by a live page, except for that page itself, and an empty slug never is.
     *
     * @return void
     */
    public function test_is_taken_counts_only_pages_that_are_not_deleted(): void {
        $this->resetAfterTest();

        $page = $this->pages()->create_page(['menuname' => 'about']);

        $this->assertTrue(slug::is_taken('about'));

        // A page never collides with itself, or the owner could not re-save it.
        $this->assertFalse(slug::is_taken('about', (int) $page->id));

        // Another page's id does not excuse the collision.
        $other = $this->pages()->create_page(['menuname' => 'contact']);
        $this->assertTrue(slug::is_taken('about', (int) $other->id));

        // Comparison is on the stored form: trimmed and lower-cased.
        $this->assertTrue(slug::is_taken('  ABOUT '));

        // An empty slug is never taken; several pages may legitimately have none.
        $this->assertFalse(slug::is_taken(''));

        // A slug nobody holds is free.
        $this->assertFalse(slug::is_taken('nobody-has-this'));
    }

    /**
     * Deleting a page releases the address it was holding.
     *
     * pages.php is a script, so this repeats the update it makes (the flag and deleted_name() in
     * one write) and asserts that is_taken() then reports the original slug as free.
     *
     * @return void
     */
    public function test_a_deleted_page_releases_its_slug(): void {
        global $DB;

        $this->resetAfterTest();

        $page = $this->pages()->create_page(['menuname' => 'about']);
        $id = (int) $page->id;

        // Control: the slug is taken while the page is live, so the release below is the delete.
        $this->assertTrue(slug::is_taken('about'));

        // What pages.php writes, in one update.
        $DB->update_record('local_page', (object) [
            'id' => $id,
            'deleted' => 1,
            'menuname' => slug::deleted_name('about', $id),
        ]);

        $this->assertSame('about-deleted-' . $id, $this->stored_slug($id));
        $this->assertFalse(slug::is_taken('about'));

        // And the address really is available to another page now.
        $successor = $this->pages()->create_page(['menuname' => 'about']);
        $this->assertTrue(slug::is_taken('about'));
        $this->assertFalse(slug::is_taken('about', (int) $successor->id));
    }

    /**
     * A slug is only taken within the context that owns it.
     *
     * Two categories may each own a page called "contato", and a site-wide page of the same name
     * is a third, separate address.
     *
     * @return void
     */
    public function test_a_slug_is_taken_only_within_its_own_context(): void {
        $this->resetAfterTest();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();
        $scopea = $this->category_scope((int) $cata->id);
        $scopeb = $this->category_scope((int) $catb->id);

        $this->pages()->create_category_page((int) $cata->id, ['menuname' => 'contato']);

        // Taken where it was written...
        $this->assertTrue(slug::is_taken('contato', 0, $scopea));

        // ...and free in every other context.
        $this->assertFalse(slug::is_taken('contato', 0, $scopeb));
        $this->assertFalse(slug::is_taken('contato'), 'the system scope is not the category scope');

        // The second category may now take it, and still does not collide with the first.
        $second = $this->pages()->create_category_page((int) $catb->id, ['menuname' => 'contato']);
        $this->assertTrue(slug::is_taken('contato', 0, $scopeb));
        $this->assertFalse(slug::is_taken('contato', (int) $second->id, $scopeb), 'a page keeps its own slug');

        /*
         * Control: a site-wide page called "contato" makes the system scope answer true while both
         * category answers stay as they were. Without it, an is_taken() that answered false for
         * everything would satisfy the three assertions above.
         */
        $this->pages()->create_page(['menuname' => 'contato']);
        $this->assertTrue(slug::is_taken('contato'));
        $this->assertTrue(slug::is_taken('contato', 0, $scopea));
        $this->assertTrue(slug::is_taken('contato', 0, $scopeb));
    }

    /**
     * normalise_all() separates duplicates inside one context and leaves the other contexts alone.
     *
     * @return void
     */
    public function test_normalise_all_separates_duplicates_within_one_context_only(): void {
        $this->resetAfterTest();

        $cata = $this->getDataGenerator()->create_category();
        $catb = $this->getDataGenerator()->create_category();

        $first = $this->pages()->create_category_page((int) $cata->id, ['menuname' => 'about']);
        $second = $this->pages()->create_category_page((int) $cata->id, ['menuname' => 'about']);
        $elsewhere = $this->pages()->create_category_page((int) $catb->id, ['menuname' => 'about']);
        $sitewide = $this->pages()->create_page(['menuname' => 'about']);

        // Exactly one row moves: the duplicate inside category A.
        $this->assertSame(1, slug::normalise_all());

        $this->assertSame('about', $this->stored_slug((int) $first->id));
        $this->assertSame('about-' . (int) $second->id, $this->stored_slug((int) $second->id));

        /*
         * Controls: the pages in the other category and in the site scope share the address with
         * the first one and are untouched, which is what "per context" means. A normalise_all()
         * that still grouped globally would have moved both of them.
         */
        $this->assertSame('about', $this->stored_slug((int) $elsewhere->id));
        $this->assertSame('about', $this->stored_slug((int) $sitewide->id));
    }

    /**
     * Slugs Moodle answers on itself are refused, and the refusal is by exact name or by prefix.
     *
     * @return void
     */
    public function test_is_reserved_covers_core_paths_router_segments_and_plugin_names(): void {
        $this->resetAfterTest();

        // Each is a webroot directory, a webroot script or a router segment.
        foreach (['course', 'login', 'pluginfile', 'r', 'p', 's', 'esm', 'admin', 'lib'] as $name) {
            $this->assertTrue(slug::is_reserved($name), "{$name} is answered by Moodle itself");
        }

        // Anything shaped like a frankenstyle component, because a plugin may claim it tomorrow.
        foreach (['local_foo', 'mod_quiz', 'theme_boost', 'qtype_multichoice'] as $name) {
            $this->assertTrue(slug::is_reserved($name), "{$name} is a component name");
        }

        // Compared the way the value is stored: trimmed and lower-cased.
        $this->assertTrue(slug::is_reserved('  LOGIN '));

        /*
         * Controls: ordinary slugs are accepted, including ones that merely start with a reserved
         * name, like courses or logins, and a plugin type without the underscore, like localfoo;
         * local alone is reserved as a webroot directory. Without these, an is_reserved() that
         * answered true for everything would pass the assertions above.
         */
        foreach (['about-us', 'contato', 'courses', 'logins', 'local', 'localfoo', 'my-blog'] as $name) {
            $expected = $name === 'local';
            $this->assertSame($expected, slug::is_reserved($name), $name);
        }

        // An empty slug is not reserved; the save path names it page-<id> instead.
        $this->assertFalse(slug::is_reserved(''));
    }

    /**
     * normalise_all() leaves a stored slug that would now be refused exactly as it is.
     *
     * Only the form refuses reserved slugs; renaming a published one at upgrade time would break
     * its address. See {@see slug::is_reserved()}.
     *
     * @return void
     */
    public function test_normalise_all_does_not_rename_a_reserved_legacy_slug(): void {
        $this->resetAfterTest();

        $legacy = $this->pages()->create_page(['menuname' => 'login']);

        // Control: the routine ran and rewrote a row, lower-casing Legal.
        $this->pages()->create_page(['menuname' => 'Legal']);
        $this->assertGreaterThan(0, slug::normalise_all());

        $this->assertTrue(slug::is_reserved('login'));
        $this->assertSame('login', $this->stored_slug((int) $legacy->id));
    }

    /**
     * A page restored by hand cannot take back the address its successor is now serving.
     *
     * The `deleted = 0` filter in is_taken() hides the row only while the flag is set; the mangling
     * in deleted_name() is what survives an administrator clearing the flag by hand (nothing in the
     * plugin restores a page). Without it two live pages would share one friendly URL, and
     * custompage::load_by_menuname() (ORDER BY id DESC, IGNORE_MULTIPLE) would silently serve only
     * the one with the higher id.
     *
     * @return void
     */
    public function test_a_restored_page_cannot_take_back_the_address_its_successor_holds(): void {
        global $DB;

        $this->resetAfterTest();

        $page = $this->pages()->create_page(['menuname' => 'about']);
        $id = (int) $page->id;

        // The delete pages.php performs: the flag AND the mangling, in one update.
        $DB->update_record('local_page', (object) [
            'id' => $id,
            'deleted' => 1,
            'menuname' => slug::deleted_name('about', $id),
        ]);

        // The successor takes the freed address.
        $successor = $this->pages()->create_page(['menuname' => 'about']);
        $successorid = (int) $successor->id;

        // A restore by hand: the flag is cleared and the stored slug is left exactly as it is.
        $DB->set_field('local_page', 'deleted', 0, ['id' => $id]);
        $restored = $this->stored_slug($id);

        /*
         * Control: the restored row really is live again, so the separation asserted below is the
         * mangled slug doing the work and not a row the deleted filter is still hiding.
         */
        $this->assertTrue(slug::is_taken($restored));

        // The two live pages hold different addresses.
        $this->assertSame('about-deleted-' . $id, $restored);
        $this->assertSame('about', $this->stored_slug($successorid));
        $this->assertFalse(slug::is_taken($restored, $id));
        $this->assertFalse(slug::is_taken('about', $successorid));
    }

    /**
     * deleted_name() carries the id, lower-cases, and is stable under repetition.
     *
     * @return void
     */
    public function test_deleted_name(): void {
        $this->resetAfterTest();

        $this->assertSame('about-deleted-7', slug::deleted_name('about', 7));
        $this->assertSame('about-deleted-7', slug::deleted_name('  About  ', 7));

        // An empty slug is named first, so a deleted row never carries a bare suffix.
        $this->assertSame('page-7-deleted-7', slug::deleted_name('', 7));

        /*
         * Idempotent: normalise_all() runs this over rows that may already have been through it,
         * so applying it twice must not stack suffixes.
         */
        $once = slug::deleted_name('about', 7);
        $this->assertSame($once, slug::deleted_name($once, 7));

        // Two pages that shared an address get distinct mangled values, because the id is in them.
        $this->assertNotSame(slug::deleted_name('about', 7), slug::deleted_name('about', 8));

        // The result fits the column even when the original filled it.
        $long = str_repeat('a', 255);
        $this->assertSame(255, \core_text::strlen(slug::deleted_name($long, 12)));
        $this->assertStringEndsWith('-deleted-12', slug::deleted_name($long, 12));
    }

    /**
     * normalise_all() names empty slugs, separates duplicates, mangles deleted rows, lower-cases.
     *
     * @return void
     */
    public function test_normalise_all_brings_every_row_into_line(): void {
        $this->resetAfterTest();

        $empty = $this->pages()->create_page(['menuname' => '']);
        $first = $this->pages()->create_page(['menuname' => 'about']);
        $second = $this->pages()->create_page(['menuname' => 'about']);
        $deleted = $this->pages()->create_page(['menuname' => 'contact', 'deleted' => 1]);
        $upper = $this->pages()->create_page(['menuname' => 'Legal']);
        $control = $this->pages()->create_page(['menuname' => 'privacy']);

        $this->assertSame(4, slug::normalise_all());

        // An empty live slug becomes addressable.
        $this->assertSame('page-' . (int) $empty->id, $this->stored_slug((int) $empty->id));

        // Among duplicates the lowest id keeps the address and the other is moved aside.
        $this->assertSame('about', $this->stored_slug((int) $first->id));
        $this->assertSame('about-' . (int) $second->id, $this->stored_slug((int) $second->id));

        // A deleted row releases the address it was holding.
        $this->assertSame('contact-deleted-' . (int) $deleted->id, $this->stored_slug((int) $deleted->id));
        $this->assertFalse(slug::is_taken('contact'));

        // Stored slugs are lower-cased, because that is what the save path writes.
        $this->assertSame('legal', $this->stored_slug((int) $upper->id));

        /*
         * Control: a row that already satisfies every rule is not touched. Without it a routine
         * that rewrote every row in the table would pass all the assertions above.
         */
        $this->assertSame('privacy', $this->stored_slug((int) $control->id));

        // Every live address is now unique.
        $this->assertFalse(slug::is_taken('about', (int) $first->id));
    }

    /**
     * Running the normalisation twice changes nothing the second time.
     *
     * An upgrade can be replayed, and a second pass that kept appending suffixes would rename
     * pages, breaking their published URLs.
     *
     * @return void
     */
    public function test_normalise_all_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest();

        $this->pages()->create_page(['menuname' => '']);
        $this->pages()->create_page(['menuname' => 'about']);
        $this->pages()->create_page(['menuname' => 'about']);
        $this->pages()->create_page(['menuname' => 'contact', 'deleted' => 1]);
        $this->pages()->create_page(['menuname' => 'Legal']);

        // Control: the first pass really did change rows, so the 0 below is not an empty table.
        $this->assertGreaterThan(0, slug::normalise_all());

        $after = $DB->get_records_menu('local_page', null, 'id ASC', 'id, menuname');

        $this->assertSame(0, slug::normalise_all());
        $this->assertSame($after, $DB->get_records_menu('local_page', null, 'id ASC', 'id, menuname'));
    }

    /**
     * A moved page never lands on a slug another live page of its context holds, and a second run changes nothing.
     *
     * The lowest id holds x-<third>, which is exactly the form the third page moves to when it loses
     * x to the second one. The form is taken, so the third page goes on to x-<third>-2. Handing it
     * x-<third> would leave two live pages on one address, and a second run would keep them there,
     * because a slug that already ends in the page's id is the form that page would move to.
     *
     * @return void
     */
    public function test_normalise_all_never_moves_a_page_onto_a_slug_already_held(): void {
        global $DB;

        $this->resetAfterTest();

        $category = (int) $this->getDataGenerator()->create_category()->id;
        $first = (int) $this->pages()->create_category_page($category, ['menuname' => 'placeholder'])->id;
        $second = (int) $this->pages()->create_category_page($category, ['menuname' => 'x'])->id;
        $third = (int) $this->pages()->create_category_page($category, ['menuname' => 'x'])->id;
        $DB->set_field('local_page', 'menuname', "x-{$third}", ['id' => $first]);

        // Only the third page moves.
        $this->assertSame(1, slug::normalise_all());

        $this->assertSame("x-{$third}", $this->stored_slug($first));
        $this->assertSame('x', $this->stored_slug($second));
        $this->assertSame("x-{$third}-2", $this->stored_slug($third));

        // The run is idempotent for real: the second one writes nothing and the table is unchanged.
        $after = $DB->get_records_menu('local_page', null, 'id ASC', 'id, menuname');
        $this->assertCount(3, array_unique($after), 'Three live pages, three addresses.');
        $this->assertSame(0, slug::normalise_all());
        $this->assertSame($after, $DB->get_records_menu('local_page', null, 'id ASC', 'id, menuname'));
    }

    /**
     * A page that has to move skips every form a later page of its context holds, and counts on.
     *
     * The later pages hold x-<second> and x-<second>-2, the first two forms the second page could
     * move to, so it ends on x-<second>-3 and both keep the slugs they were already serving. A
     * site-wide page holding x-<second>-3 is the control that the forms are compared within the
     * context only: were it counted, the second page would have gone on to -4.
     *
     * @return void
     */
    public function test_normalise_all_skips_forms_a_later_page_holds_and_counts_on(): void {
        global $DB;

        $this->resetAfterTest();

        $category = (int) $this->getDataGenerator()->create_category()->id;
        $first = (int) $this->pages()->create_category_page($category, ['menuname' => 'x'])->id;
        $second = (int) $this->pages()->create_category_page($category, ['menuname' => 'x'])->id;
        $third = (int) $this->pages()->create_category_page($category, ['menuname' => "x-{$second}"])->id;
        $fourth = (int) $this->pages()->create_category_page($category, ['menuname' => "x-{$second}-2"])->id;
        $sitewide = (int) $this->pages()->create_page(['menuname' => "x-{$second}-3"])->id;

        $this->assertSame(1, slug::normalise_all());

        $this->assertSame('x', $this->stored_slug($first));
        $this->assertSame("x-{$second}-3", $this->stored_slug($second));
        $this->assertSame("x-{$second}", $this->stored_slug($third));
        $this->assertSame("x-{$second}-2", $this->stored_slug($fourth));
        $this->assertSame("x-{$second}-3", $this->stored_slug($sitewide));

        $after = $DB->get_records_menu('local_page', null, 'id ASC', 'id, menuname');
        $this->assertSame(0, slug::normalise_all());
        $this->assertSame($after, $DB->get_records_menu('local_page', null, 'id ASC', 'id, menuname'));
    }

    /**
     * A slug that already ends in the page's own id gains only the counter, never a second copy of the id.
     *
     * @return void
     */
    public function test_a_slug_ending_in_the_page_id_gains_only_the_counter(): void {
        global $DB;

        $this->resetAfterTest();

        $category = (int) $this->getDataGenerator()->create_category()->id;
        $scope = $this->category_scope($category);
        $first = (int) $this->pages()->create_category_page($category, ['menuname' => 'placeholder'])->id;
        $second = (int) $this->pages()->create_category_page($category, ['menuname' => 'placeholder-2'])->id;
        $DB->set_field('local_page', 'menuname', "z-{$second}", ['id' => $first]);
        $DB->set_field('local_page', 'menuname', "z-{$second}", ['id' => $second]);

        // Arriving in the context: the page's own slug is taken, and so is the -<id> form, which is the same value.
        $this->assertSame("z-{$second}-2", slug::unique_in_context("z-{$second}", $second, $scope));

        // The upgrade's routine settles the duplicate the same way.
        $this->assertSame(1, slug::normalise_all());
        $this->assertSame("z-{$second}", $this->stored_slug($first));
        $this->assertSame("z-{$second}-2", $this->stored_slug($second));
    }

    /**
     * A page arriving in a context keeps a free slug, gains its id on a taken one, and a counter after that.
     *
     * The second context holds the same slugs and changes nothing: the answer is the arriving
     * context's alone.
     *
     * @return void
     */
    public function test_unique_in_context_keeps_a_free_slug_and_suffixes_a_taken_one(): void {
        $this->resetAfterTest();

        $there = (int) $this->getDataGenerator()->create_category()->id;
        $elsewhere = (int) $this->getDataGenerator()->create_category()->id;
        $scope = $this->category_scope($there);

        $arriving = $this->pages()->create_category_page($elsewhere, ['menuname' => 'contato']);
        $id = (int) $arriving->id;
        $this->pages()->create_category_page($elsewhere, ['menuname' => 'sobre']);

        $this->assertSame('contato', slug::unique_in_context('contato', $id, $scope), 'Free: kept.');
        $this->assertSame('sobre', slug::unique_in_context('sobre', $id, $scope), 'Held elsewhere only: kept.');

        $this->pages()->create_category_page($there, ['menuname' => 'contato']);
        $this->assertSame("contato-{$id}", slug::unique_in_context('contato', $id, $scope), 'Taken: the id follows.');

        $this->pages()->create_category_page($there, ['menuname' => "contato-{$id}"]);
        $this->assertSame("contato-{$id}-2", slug::unique_in_context('contato', $id, $scope), 'That too: a counter follows.');

        $this->pages()->create_category_page($there, ['menuname' => "contato-{$id}-2"]);
        $this->assertSame("contato-{$id}-3", slug::unique_in_context('contato', $id, $scope));

        // The page itself is never its own rival: its own row in the context does not take the slug.
        $this->assertSame('contato', slug::unique_in_context('contato', $id, $this->category_scope($elsewhere)));
    }
}
