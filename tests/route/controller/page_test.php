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
 * Tests for the routed category address.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\route\controller;

use core\router\route_loader_interface;
use local_page\local\links;
use local_page\local\request;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

/**
 * The routed category address, end to end: core's router, this controller, the shared request and body.
 *
 * Every test runs in the production shape — the router configured and forcelogin on — and the
 * harness's router is the only one the process may build: nothing here spells a routed address
 * before process_request(), or the harness would map every route a second time onto the first
 * router's collector and answer 500. Expected addresses are spelled after the request, through the
 * same router. Where a test needs more than one request, the container is emptied between them, so
 * each request builds its own router the way a real one does.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(page::class)]
#[CoversClass(request::class)]
final class page_test extends \core\tests\router\route_testcase {
    /** @var string The real public predicate, from local_unlistedcourses; tests adapt or skip where it is absent. */
    private const PREDICATE = '\local_unlistedcourses\category_discoverability';

    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * Declare the router configured and force login site-wide: the production shape.
     *
     * @return void
     */
    private function production_shape(): void {
        global $CFG;

        $CFG->routerconfigured = true;
        $CFG->forcelogin = 1;
    }

    /**
     * Forget what the real predicate memoised, when it is installed: category ids repeat between tests.
     *
     * @return void
     */
    private function forget_predicate(): void {
        if (class_exists(self::PREDICATE)) {
            \local_unlistedcourses\category_discoverability::reset_caches();
        }
    }

    /**
     * Request a category's page through the router, as a browser would.
     *
     * @param string $category The category path segment
     * @param string $slug The slug path segment, already URL-encoded
     * @return ResponseInterface The response
     */
    private function request(string $category, string $slug): ResponseInterface {
        global $SESSION;

        unset($SESSION->wantsurl);
        $this->forget_predicate();
        $GLOBALS['PAGE'] = new \moodle_page();

        return $this->process_request(
            'GET',
            'local_page/category/' . $category . '/' . $slug,
            route_loader_interface::ROUTE_GROUP_PAGE,
            ['HTTP_ACCEPT' => 'text/html'],
            null,
            null
        );
    }

    /**
     * A second request in the same test: the container is emptied first, so it builds its own router.
     *
     * @param string $category The category path segment
     * @param string $slug The slug path segment, already URL-encoded
     * @return ResponseInterface The response
     */
    private function another_request(string $category, string $slug): ResponseInterface {
        \core\di::reset_container();

        return $this->request($category, $slug);
    }

    /**
     * The three refusals a visitor can meet are one refusal: same status, same Location, same body.
     *
     * A private category's page that exists, a category that does not exist and a public category's
     * slug that names nothing. If any of them answered differently — a 404 for the missing category,
     * say — an anonymous client could list which categories and slugs exist; see request::category().
     * The public category comes from the real predicate where local_unlistedcourses is installed;
     * where it is absent nothing is public, and the third case is a second private category, which
     * still has to answer alike.
     *
     * A controller that answered everybody with the login page would pass this test; it is caught by
     * test_a_logged_in_user_reads_a_private_categorys_page, and by
     * test_a_visitor_reads_a_public_categorys_page where that one runs.
     *
     * @return void
     */
    public function test_every_refusal_a_visitor_meets_is_the_same_answer(): void {
        global $DB, $SESSION;

        $this->resetAfterTest();
        $this->production_shape();
        $this->setAdminUser();
        $private = (int) $this->getDataGenerator()->create_category()->id;
        $public = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($private, ['menuname' => 'handbook']);
        $this->pages()->create_category_page($public, ['menuname' => 'handbook']);
        if (class_exists(self::PREDICATE)) {
            \local_unlistedcourses\category_discoverability::set_state(
                $public,
                \local_unlistedcourses\category_discoverability::STATE_PUBLIC,
                (int) get_admin()->id
            );
            // Precondition: the third case passes the category check, and is refused by the lookup.
            $this->forget_predicate();
            $this->assertTrue(\local_page\local\publicaccess::is_public($public), 'The public category is public.');
        }
        $missing = (int) $DB->get_field_sql('SELECT MAX(id) FROM {course_categories}') + 1000;
        $this->setUser(null);

        $responses = [
            'a private category\'s page' => $this->request((string) $private, 'handbook'),
            'a category that does not exist' => $this->another_request((string) $missing, 'handbook'),
            'a public category\'s missing slug' => $this->another_request((string) $public, 'nosuchpage'),
        ];
        $wantsurl = $SESSION->wantsurl ?? null;

        $shapes = [];
        foreach ($responses as $label => $response) {
            $shapes[$label] = [
                'status' => $response->getStatusCode(),
                'location' => $response->getHeaderLine('Location'),
                'body' => (string) $response->getBody(),
            ];
        }

        $expected = ['status' => request::STATUS_LOGIN, 'location' => get_login_url(), 'body' => ''];
        foreach ($shapes as $label => $shape) {
            $this->assertSame($expected, $shape, "{$label}: the login refusal and nothing else");
        }
        $this->assertCount(1, array_unique(array_map('serialize', $shapes)), 'One refusal for all three.');
        $this->assertSame(
            links::category_page($public, 'nosuchpage')->out(false),
            $wantsurl,
            'The way back is the address that was asked for, spelled by the builder.'
        );
    }

    /**
     * A visitor reads a public category's page at its routed address, with no login required by the route.
     *
     * The category is made public through the real predicate, local_unlistedcourses'
     * category_discoverability, so this is skipped where that plugin is absent. The test double
     * cannot reach the route, because the controller takes no predicate argument; request_test covers
     * the same decision with the double.
     *
     * @return void
     */
    public function test_a_visitor_reads_a_public_categorys_page(): void {
        global $PAGE;

        if (!class_exists(self::PREDICATE)) {
            $this->markTestSkipped('local_unlistedcourses is not installed, so no category can be public here.');
        }

        $this->resetAfterTest();
        $this->production_shape();
        $this->setAdminUser();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($categoryid, [
            'menuname' => 'handbook',
            'pagename' => 'Campus handbook',
            'pagecontent' => '<p>The public handbook starts here.</p>',
        ]);
        \local_unlistedcourses\category_discoverability::set_state(
            $categoryid,
            \local_unlistedcourses\category_discoverability::STATE_PUBLIC,
            (int) get_admin()->id
        );
        $this->setUser(null);

        $response = $this->request((string) $categoryid, 'handbook');

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('The public handbook starts here.', $body);
        $this->assertSame(
            links::category_page($categoryid, 'handbook')->out(false),
            $PAGE->url->out(false),
            'The page is set up at its routed address.'
        );
        $this->assertSame(\core\context\coursecat::instance($categoryid)->id, $PAGE->context->id);
    }

    /**
     * A logged-in user reads a page of a category that is not public: the predicate is a visitor's question.
     *
     * @return void
     */
    public function test_a_logged_in_user_reads_a_private_categorys_page(): void {
        $this->resetAfterTest();
        $this->production_shape();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->pages()->create_category_page($categoryid, [
            'menuname' => 'handbook',
            'pagecontent' => '<p>The staff handbook starts here.</p>',
        ]);
        $this->setUser($this->getDataGenerator()->create_user());

        $response = $this->request((string) $categoryid, 'handbook');

        $body = (string) $response->getBody();
        $this->assertSame(200, $response->getStatusCode(), substr($body, 0, 2000));
        $this->assertStringContainsString('The staff handbook starts here.', $body);
        $this->assertStringNotContainsString('local-page-admin-controls', $body, 'No edit button for a reader.');
    }

    /**
     * A slug outside the route's character set is refused by core's validator, before the controller runs.
     *
     * The route's slug parameter is ALPHANUMEXT, the set every stored slug is cleaned to, so a space
     * can name no page. The refusal is the router's not-found page, and it says nothing about which
     * pages exist. That the controller was never reached shows in the session: a visitor who reached
     * it would have been refused with wantsurl set to come back to this address.
     *
     * @return void
     */
    public function test_a_slug_outside_the_character_set_is_the_routers_not_found(): void {
        global $SESSION;

        $this->resetAfterTest();
        $this->production_shape();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $this->setUser(null);

        $response = $this->request((string) $categoryid, 'bad%20slug');

        $this->assert_valid_response($response, 404);
        $this->assertFalse(isset($SESSION->wantsurl), 'The controller never ran: nobody was sent to log in.');
    }
}
