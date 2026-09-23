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
 * The Open Graph image of a page: which file, at which address, of which size.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\local;

/**
 * The Open Graph image of a page: which file, at which address, of which size.
 *
 * A page holds at most one image in its 'ogimage' area, stored in the page's own context under the
 * page id at filepath '/'. That is the only place local_page_pluginfile() looks, so it is the only
 * place this class looks too: an image the file route would not serve is never advertised.
 *
 * The ADDRESS carries the file's content hash as a directory segment. A messaging app keeps the
 * preview it fetched for as long as it likes, keyed by the image URL; a flat address stays the same
 * when the author replaces the picture, and the old one goes on being shown. With the hash in the
 * path a replaced image is a new address. The segment is a cache key, never a credential: the file
 * route does not compare it with the file (see local_page_pluginfile()).
 *
 * The SIZE is what lets the tags state og:image:width and og:image:height, which some clients need
 * before they render a large preview. It is read through stored_file::get_imageinfo(), and core
 * already caches that answer by content hash in the application cache core/file_imageinfo
 * (lib/filestorage/file_system.php, get_imageinfo()), so this class keeps no cache of its own. Nor
 * does the save path measure anything: the editor's file manager restricts its accepted types, so
 * core validates the submitted draft area through file_get_all_files_in_draftarea(), which measures
 * every image in it — and the draft file has the stored file's content hash, so the first render
 * after a save finds the size cached. A render after a purge measures again on the spot.
 *
 * The CONTENT has to be the picture the name says, which nothing in core checks on the way in: the
 * file manager and the upload repository accept a file by its extension alone, and the file store
 * types it by that extension. An SVG saved as cover.png — markup, able to carry a script — was
 * stored, typed image/png, advertised with the size GD's SVG fallback invents, and served to
 * anybody at the anonymous image address. is_image() is the one answer to "is this our image", and
 * the editor's validation, the file route and file() below all ask it, so a file refused on the way
 * in is also never advertised and never served if it got into the area some other way.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ogimage {
    /** @var string The file area the image lives in. */
    public const FILEAREA = 'ogimage';

    /**
     * The image a page advertises, when there is one the file route will serve.
     *
     * The publication gate comes first and is the same function the file route applies, so the tags
     * never name an image whose address answers 404: a draft, archived, expired, not yet started or
     * deleted page advertises nothing, even to an editor previewing it. The file route refuses a file
     * that is not the image its name says (is_image()), so neither is such a file advertised.
     *
     * @param object $page Row from {local_page} (stdClass) or {@see \local_page\custompage}
     * @return \stored_file|null The file, or null
     * @throws \dml_missing_record_exception When the row's stored context id names no context
     */
    public static function file(object $page): ?\stored_file {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/lib.php');

        if (!local_page_ogimage_is_servable($page)) {
            return null;
        }

        $file = self::stored(scope::context($page), (int) $page->id);
        if ($file === null || !self::is_image($file)) {
            return null;
        }

        return $file;
    }

    /**
     * Whether a file is an image this plugin advertises and serves: a raster whose content is the type
     * its name says.
     *
     * Two of core's own checks, and both are needed. The name must carry one of the extensions the
     * editor's file manager accepts, which keeps out a genuine SVG — stored_file::is_valid_image()
     * accepts one, SVG being a web image to core. And stored_file::is_valid_image() must pass: the
     * stored type is a web image, and the type read from the CONTENT is that same type, which is what
     * refuses an SVG, a web page or a text file saved under an image's name. The content is read
     * through stored_file::get_imageinfo(), which core caches by content hash (core/file_imageinfo),
     * so asking again for the same file costs a cache hit.
     *
     * @param \stored_file $file The file, in the page's image area or in an editor's draft area
     * @return bool
     */
    public static function is_image(\stored_file $file): bool {
        global $CFG;
        require_once($CFG->dirroot . '/local/page/lib.php');

        if ($file->is_directory()) {
            return false;
        }

        $types = new \core_form\filetypes_util();
        $accepted = $types->normalize_file_types(local_page_ogimage_filemanager_options()['accepted_types']);
        if (!$types->is_allowed_file_type($file->get_filename(), $accepted)) {
            return false;
        }

        return $file->is_valid_image();
    }

    /**
     * The file stored in a page's image area, whatever the state of the page.
     *
     * @param \core\context $context The page's own context
     * @param int $pageid The page id, which is the area's item id
     * @return \stored_file|null The file at filepath '/', or null
     */
    public static function stored(\core\context $context, int $pageid): ?\stored_file {
        if ($pageid <= 0) {
            return null;
        }

        $files = get_file_storage()->get_area_files(
            $context->id,
            'local_page',
            self::FILEAREA,
            $pageid,
            'itemid, filepath, filename',
            false
        );

        foreach ($files as $file) {
            if ($file->get_filepath() === '/') {
                return $file;
            }
        }

        return null;
    }

    /**
     * The address of an image, carrying its content hash.
     *
     * @param \stored_file $file The image
     * @return \moodle_url The pluginfile address: contextid/local_page/ogimage/pageid/contenthash/filename
     */
    public static function url(\stored_file $file): \moodle_url {
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            'local_page',
            self::FILEAREA,
            $file->get_itemid(),
            '/' . $file->get_contenthash() . '/',
            $file->get_filename(),
            false
        );
    }

    /**
     * The pixel size of an image, when it is a raster that can be measured.
     *
     * @param \stored_file $file The image
     * @return array|null Keys width and height, as integers; null when the file cannot be measured
     */
    public static function size(\stored_file $file): ?array {
        $info = $file->get_imageinfo();
        if (!is_array($info) || empty($info['width']) || empty($info['height'])) {
            return null;
        }

        return ['width' => (int) $info['width'], 'height' => (int) $info['height']];
    }

    /**
     * Everything the tags say about a page's image.
     *
     * @param object $page Row from {local_page} (stdClass) or {@see \local_page\custompage}
     * @return array|null Keys url, type, width and height (the last two null when unmeasurable), or null
     * @throws \dml_missing_record_exception When the row's stored context id names no context
     */
    public static function for_page(object $page): ?array {
        $file = self::file($page);
        if ($file === null) {
            return null;
        }

        $size = self::size($file);

        return [
            'url' => self::url($file)->out(false),
            'type' => $file->get_mimetype(),
            'width' => $size['width'] ?? null,
            'height' => $size['height'] ?? null,
        ];
    }
}
