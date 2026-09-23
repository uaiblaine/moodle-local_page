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
 * A category's custom page on Moodle's router.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\route\controller;

use core\param;
use core\router\route;
use core\router\schema\parameters\path_parameter;
use local_page\local\request;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A category's custom page at /local_page/category/{category}/{slug}.
 *
 * The prefix is core's: a plugin's page route always carries its frankenstyle name
 * (\core\router\util::normalise_component_path()), and only core may place routes anywhere. The
 * route's shape is three decisions:
 *
 * - No requirelogin. The page serves visitors of a public category on a site that forces login,
 *   and decides for itself who reads what; a login requirement here would refuse them before the
 *   decision was asked.
 * - Plain parameters, never a category path type. A resolver of that kind looks the category up
 *   and throws not_found before the controller runs, and a 404 for a missing category against a
 *   redirect for a real one is an existence oracle for an anonymous client — the one thing the
 *   request class is arranged to deny. The slug is ALPHANUMEXT, the character set every stored
 *   slug is cleaned to; anything else is refused by core's request validator as not found, which
 *   says something about the characters and nothing about which pages exist.
 * - GET only. Nothing on the page posts back to it.
 *
 * Every decision is {@see request::category()}'s, the same call the legacy script makes, and the
 * body is local_page_render_view()'s, the same function the script renders with: this method only
 * turns the answer into a response.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class page {
    use \core\router\route_controller;

    /**
     * Answer a request for a category's page.
     *
     * @param ServerRequestInterface $request The request
     * @param ResponseInterface $response The response to fill
     * @param int $category The category id from the path
     * @param string $slug The page's friendly URL from the path
     * @return ResponseInterface The page, or a redirect
     */
    #[route(
        path: '/category/{category}/{slug}',
        method: ['GET'],
        pathtypes: [
            new path_parameter(name: 'category', type: param::INT),
            new path_parameter(name: 'slug', type: param::ALPHANUMEXT),
        ],
    )]
    public function view(
        ServerRequestInterface $request,
        ResponseInterface $response,
        int $category,
        string $slug,
    ): ResponseInterface {
        global $CFG, $OUTPUT;
        require_once($CFG->dirroot . '/local/page/lib.php');

        $answer = request::category($category, 0, $slug);
        if ($answer->redirect !== null) {
            return $this->redirect($response, $answer->redirect)->withStatus($answer->status);
        }

        $body = local_page_render_view($answer);

        // Guarded like core's restricted_module controller: under PHPUnit the header renders nothing.
        if ($header = $OUTPUT->header()) {
            $response->getBody()->write($header);
        }
        $response->getBody()->write($body);
        if ($footer = $OUTPUT->footer()) {
            $response->getBody()->write($footer);
        }

        return $response;
    }
}
