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
 * Tests for the friendly URL (menuname) rules.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use PHPUnit\Framework\Attributes\CoversClass;

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
     * The plugin's data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A slug is taken while a page that is not deleted holds it, and free otherwise.
     *
     * @return void
     */
    public function test_is_taken(): void {
        $this->resetAfterTest();

        $about = $this->pages()->create_page(['menuname' => 'about']);
        $this->pages()->create_page(['menuname' => 'contact', 'deleted' => 1]);

        $this->assertTrue(slug::is_taken('about'));
        $this->assertTrue(slug::is_taken(' About '), 'Compared trimmed and lower-cased, as stored.');

        // A page keeps its own slug; the same slug is still taken for any other page.
        $this->assertFalse(slug::is_taken('about', $about->id));
        $this->assertTrue(slug::is_taken('about', $about->id + 1000));

        // A deleted page holds no address, even with its slug not yet renamed.
        $this->assertFalse(slug::is_taken('contact'));

        $this->assertFalse(slug::is_taken('nobody'));
        $this->assertFalse(slug::is_taken(''));
    }

    /**
     * The slug of a deleted page carries its id, and renaming it twice changes nothing.
     *
     * @return void
     */
    public function test_deleted_name(): void {
        $this->assertSame('about-deleted-7', slug::deleted_name('about', 7));
        $this->assertSame('about-deleted-7', slug::deleted_name(' About ', 7));
        $this->assertSame('about-deleted-7', slug::deleted_name('about-deleted-7', 7));
        $this->assertSame('page-7-deleted-7', slug::deleted_name('', 7));

        // Two pages that shared an address do not share the renamed one.
        $this->assertNotSame(slug::deleted_name('about', 7), slug::deleted_name('about', 8));

        // The result still fits the column, with the suffix intact.
        $long = slug::deleted_name(str_repeat('a', 255), 7);
        $this->assertSame(255, \core_text::strlen($long));
        $this->assertStringEndsWith('-deleted-7', $long);
    }

    /**
     * normalise_all() names empty slugs, separates live duplicates and renames deleted rows, once.
     *
     * @return void
     */
    public function test_normalise_all(): void {
        global $DB;

        $this->resetAfterTest();

        $first = $this->pages()->create_page(['menuname' => 'about']);
        $duplicate = $this->pages()->create_page(['menuname' => 'About ']);
        $empty = $this->pages()->create_page(['menuname' => '']);
        $deleted = $this->pages()->create_page(['menuname' => 'contact', 'deleted' => 1]);
        $untouched = $this->pages()->create_page(['menuname' => 'news']);
        // Already holds what the duplicate is about to be given, so it has to move along in turn.
        $collision = $this->pages()->create_page(['menuname' => 'about-' . $duplicate->id]);

        $this->assertSame(4, slug::normalise_all());

        $expected = [
            $first->id => 'about',
            $duplicate->id => 'about-' . $duplicate->id,
            $empty->id => 'page-' . $empty->id,
            $deleted->id => 'contact-deleted-' . $deleted->id,
            $untouched->id => 'news',
            $collision->id => 'about-' . $duplicate->id . '-' . $collision->id,
        ];
        $this->assertEquals($expected, $DB->get_records_menu('local_page', null, 'id', 'id, menuname'));

        // Idempotent: a second run finds nothing to change.
        $this->assertSame(0, slug::normalise_all());
        $this->assertEquals($expected, $DB->get_records_menu('local_page', null, 'id', 'id, menuname'));
    }
}
