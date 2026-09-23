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
 * Tests for core's public shortlink route answering this plugin's codes.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page;

use local_page\local\links;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

/**
 * Core's /p/{shortcode} route, end to end: the route, core's manager, this plugin's handler.
 *
 * The rows are written directly, never through links::share(): spelling an address builds a router,
 * and the harness would then build a second one whose application maps every route onto the first
 * one's collector again. One router per process, the harness's; expected addresses are spelled
 * after the request.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[CoversClass(shortlink_handler::class)]
#[CoversClass(links::class)]
final class shortlink_route_test extends \core\tests\router\route_testcase {
    /**
     * The plugin's own data generator.
     *
     * @return \local_page_generator
     */
    private function pages(): \local_page_generator {
        return $this->getDataGenerator()->get_plugin_generator('local_page');
    }

    /**
     * A public code row for a page, written the way core's manager writes one.
     *
     * @param string $code The short code
     * @param int $pageid Page id
     * @return void
     */
    private function code(string $code, int $pageid): void {
        global $DB;

        $DB->insert_record('shortlink', (object) [
            'shortcode' => $code,
            'userid' => 0,
            'component' => links::COMPONENT,
            'linktype' => links::LINKTYPE,
            'identifier' => (string) $pageid,
        ]);
    }

    /**
     * A visitor on a site that forces login is sent to the page, in the production shape.
     *
     * @param string $code The short code
     * @return ResponseInterface
     */
    private function visit(string $code): ResponseInterface {
        global $CFG;

        $CFG->routerconfigured = true;
        $CFG->forcelogin = 1;
        $this->setUser(null);

        return $this->process_request('GET', '/p/' . $code, '');
    }

    /**
     * A category page's code sends a visitor to the page's routed address; the page decides from there.
     *
     * @return void
     */
    public function test_a_code_sends_a_visitor_to_the_page(): void {
        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'handbook']);
        $this->code('Ab3xK', (int) $page->id);

        $response = $this->visit('Ab3xK');

        $this->assertSame(302, $response->getStatusCode(), substr((string) $response->getBody(), 0, 2000));
        $this->assertSame(links::page($page)->out(false), $response->getHeaderLine('Location'));
        $this->assertStringEndsWith("/local_page/category/{$categoryid}/handbook", $response->getHeaderLine('Location'));
    }

    /**
     * A code nobody minted is not found.
     *
     * @return void
     */
    public function test_an_unknown_code_is_not_found(): void {
        $this->resetAfterTest();

        $this->assert_valid_response($this->visit('Zz9yQ'), 404);
    }

    /**
     * The code of a deleted page is not found, even while its row is still in the table.
     *
     * pages.php forgets a page's codes when it deletes the page; this is the handler refusing a row
     * that was left behind anyway, which a page deleted by any other route would leave.
     *
     * @return void
     */
    public function test_the_code_of_a_deleted_page_is_not_found(): void {
        $this->resetAfterTest();
        $categoryid = (int) $this->getDataGenerator()->create_category()->id;
        $page = $this->pages()->create_category_page($categoryid, ['menuname' => 'gone', 'deleted' => 1]);
        $this->code('Dl7wP', (int) $page->id);

        $this->assert_valid_response($this->visit('Dl7wP'), 404);
    }
}
