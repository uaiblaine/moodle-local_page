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
 * Tests for the public category adapter.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

use local_page\tests\public_predicate;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for \local_page\local\publicaccess.
 *
 * Every refusal here is asserted beside a category that IS public in the same test, because the
 * shape a fail-closed guard degrades into is an adapter that answers "no" to everything — and such
 * an adapter passes every refusal just as well as a correct one does. The public answers come from
 * \local_page\tests\public_predicate, a double handed in through the $predicate parameter, because
 * the CI matrix does not install local_unlistedcourses; the one test that reads the real predicate
 * skips itself where the plugin is absent.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(publicaccess::class)]
final class publicaccess_test extends \advanced_testcase {
    /**
     * Without the predicate class nothing is public: the adapter fails closed.
     *
     * @return void
     */
    public function test_a_missing_predicate_fails_closed(): void {
        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $missing = 'local_page\\tests\\no_such_predicate';

        public_predicate::reset([$categoryid]);
        $this->assertTrue(
            publicaccess::is_public($categoryid, public_predicate::class),
            'Control: a predicate that says public is believed.'
        );

        $this->assertFalse(class_exists($missing), 'Precondition: the class really is not there.');
        $this->assertFalse(publicaccess::is_public($categoryid, $missing), 'A missing predicate must read as not public.');
    }

    /**
     * An id no category can have is refused before the predicate is asked at all.
     *
     * The double is told those very ids are public, so a false answer can only come from the
     * refusal in front of it — and the call count says the predicate never heard the question.
     *
     * @return void
     */
    public function test_an_id_no_category_can_have_is_refused_before_the_predicate_is_asked(): void {
        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;

        public_predicate::reset([0, -1, $categoryid]);
        $this->assertFalse(publicaccess::is_public(0, public_predicate::class), 'The root has no public page.');
        $this->assertFalse(publicaccess::is_public(-1, public_predicate::class), 'Nor has a negative id.');
        $this->assertSame(0, public_predicate::calls(), 'The predicate was never asked.');

        // Control: a real id walks on to the predicate, which answers.
        $this->assertTrue(publicaccess::is_public($categoryid, public_predicate::class));
        $this->assertSame(1, public_predicate::calls());
    }

    /**
     * A predicate that says "not public" is believed too.
     *
     * @return void
     */
    public function test_a_predicate_that_says_no_is_believed(): void {
        $this->resetAfterTest();
        $private = (int) $this->getDataGenerator()->create_category()->id;
        $public = (int) $this->getDataGenerator()->create_category()->id;

        public_predicate::reset([$public]);
        $this->assertFalse(publicaccess::is_public($private, public_predicate::class));
        $this->assertTrue(publicaccess::is_public($public, public_predicate::class), 'Control: the other one is public.');
        $this->assertSame(2, public_predicate::calls(), 'Both answers were the predicate\'s own.');
    }

    /**
     * The real predicate, where local_unlistedcourses is installed: public only once a manager says so.
     *
     * Skipped where the plugin is absent, which is every leg of the CI matrix — the adapter's own
     * rules are pinned by the tests above through the double, and this one only proves that the
     * constant names a class that really answers the question.
     *
     * @return void
     */
    public function test_the_real_predicate_answers_for_a_category_made_public(): void {
        if (!class_exists(publicaccess::PREDICATE)) {
            $this->markTestSkipped('local_unlistedcourses is not installed on this site.');
        }

        $this->resetAfterTest();
        $predicate = publicaccess::PREDICATE;
        // The predicate memoises per category for the request, and ids repeat between tests.
        $predicate::reset_caches();

        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->assertFalse(publicaccess::is_public($categoryid), 'A fresh category is listed, not public.');

        $this->setAdminUser();
        $predicate::set_state($categoryid, $predicate::STATE_PUBLIC);
        $predicate::reset_caches();

        $this->assertTrue(publicaccess::is_public($categoryid), 'Made public, it is.');
    }

    /**
     * A visitor is somebody not logged in, or the guest account, and nobody else.
     *
     * @return void
     */
    public function test_a_visitor_is_nobody_or_the_guest(): void {
        $this->resetAfterTest();

        $this->setUser(null);
        $this->assertTrue(publicaccess::is_visitor(), 'Nobody logged in.');

        $this->setGuestUser();
        $this->assertTrue(publicaccess::is_visitor(), 'The guest account.');

        $this->setUser($this->getDataGenerator()->create_user());
        $this->assertFalse(publicaccess::is_visitor(), 'A user.');

        $this->setAdminUser();
        $this->assertFalse(publicaccess::is_visitor(), 'An administrator.');
    }
}
