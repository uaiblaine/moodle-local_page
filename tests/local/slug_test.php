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
     * A slug is taken by a live page, and free once that page has been deleted.
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
     * pages.php is a script rather than a function, so what is asserted here is the pair of calls
     * that script makes: the mangling deleted_name() applies, and the fact that is_taken() then
     * reports the original slug as free.
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
     * A page restored by hand cannot take back the address its successor is now serving.
     *
     * This is the scenario the mangling in deleted_name() protects, and it is a different one from
     * the `deleted = 0` filter inside is_taken(): the filter hides the row while the flag is set,
     * the mangling is what survives the flag being cleared. Nothing in the plugin restores a page,
     * but `deleted` is a plain column and an administrator who clears it — by hand, or with the
     * raw SQL that undoes a delete made in error — must not end up with two live pages answering
     * to the same friendly URL. custompage::load_by_menuname() resolves that with ORDER BY id DESC
     * and IGNORE_MULTIPLE, so the successor's page would simply stop answering, with no error
     * anywhere and no sign of what took it over.
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

        // The two live pages hold different addresses, which is the whole point of the mangling.
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
     * The upgrade step calls it once, but an upgrade can be replayed and the routine is written to
     * survive that. A second pass that kept appending suffixes would rename every page on every
     * upgrade, and each rename breaks a published URL.
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
}
