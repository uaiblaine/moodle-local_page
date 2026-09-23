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
 * Open Graph image fixtures for the tests.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\tests;

/**
 * Real, measurable images and the files that hold them.
 *
 * A size can only be asserted against a file getimagesize() can read, so the images are drawn with
 * GD rather than faked. The files that must NOT pass for an image are a text file and an SVG under
 * an image's name: the file store types both by their extension, and only the content says what
 * they are. It lives in tests/classes because four test files use it, and Moodle autoloads the
 * local_page\tests namespace from here during a PHPUnit run.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ogimage_fixture {
    /**
     * The bytes of a PNG of a given size.
     *
     * @param int $width Width in pixels
     * @param int $height Height in pixels
     * @return string The PNG
     */
    public static function png(int $width, int $height): string {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /**
     * The bytes of an SVG carrying a script, which is what an author could save as cover.png.
     *
     * It states a width and a height on purpose: core's SVG branch of get_imageinfo() reports them
     * (and invents 800 x 600 when they are missing), so a check that only asked whether the file
     * CAN be measured would take it for an image of that size. Only the type read from the content
     * gives it away.
     *
     * @return string The SVG
     */
    public static function svg(): string {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="630">'
            . '<script>alert(document.domain)</script></svg>';
    }

    /**
     * Store an image in a page's og image area, the way the save path leaves it.
     *
     * @param \core\context $context The page's own context
     * @param int $pageid The page id, which is the area's item id
     * @param string $content The file's bytes
     * @param string $filename The file's name
     * @return \stored_file The stored file
     */
    public static function store(
        \core\context $context,
        int $pageid,
        string $content,
        string $filename = 'cover.png'
    ): \stored_file {
        return get_file_storage()->create_file_from_string(
            [
                'contextid' => $context->id,
                'component' => 'local_page',
                'filearea' => 'ogimage',
                'itemid' => $pageid,
                'filepath' => '/',
                'filename' => $filename,
            ],
            $content
        );
    }

    /**
     * Put a file in a user's draft area, the way the editor's file manager leaves an upload.
     *
     * @param int $userid The user whose draft area it is
     * @param string $content The file's bytes
     * @param string $filename The file's name
     * @return int The draft item id, which is what the form posts for the file manager
     */
    public static function draft(int $userid, string $content, string $filename = 'cover.png'): int {
        $draftitemid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string(
            [
                'contextid' => \core\context\user::instance($userid)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => $draftitemid,
                'filepath' => '/',
                'filename' => $filename,
            ],
            $content
        );

        return $draftitemid;
    }
}
