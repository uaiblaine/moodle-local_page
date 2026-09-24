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
 * The head tags of a page: Open Graph, the SEO metas, robots and the canonical link.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_page\output;

use local_page\local\ogimage;
use local_page\local\scope;

/**
 * The head tags of a page: Open Graph, the SEO metas, robots and the canonical link.
 *
 * Built by local_page_render_view() once the viewer has been found to be allowed the page, and
 * picked up by the before_standard_head_html_generation hook callback, which writes it into the
 * document head. Nothing about a page the viewer may not read is ever built, so nothing about it can
 * reach the head.
 *
 * The template renders the Open Graph property metas only; the name metas (description, keywords,
 * author, robots) and the canonical link are written by the hook callback as literal strings
 * ({@see \local_page\local\hook\output\before_standard_head_html_generation::callback()} says why).
 * This object holds them all so that one decision feeds both halves.
 *
 * Every value is held in the plain spelling (format_string() with escape off) and escaped exactly
 * once where it is written: by the template's double stashes, or by s() in the callback.
 *
 * There is one og:title: the page's meta title when it has one, its name otherwise. A second
 * og:title would leave a scraper free to pick the one not written for it.
 *
 * @package    local_page
 * @copyright  2026 Anderson Blaine
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class opengraph implements \core\output\named_templatable, \core\output\renderable {
    /** @var opengraph|null The tags of the page being rendered, if it is one of this plugin's pages. */
    private static ?opengraph $current = null;

    /**
     * Constructor.
     *
     * @param string $title The og:title, plain spelling
     * @param string|null $description The og:description, plain spelling, or null for none
     * @param string $url The canonical address, which is also the og:url
     * @param string $sitename The site name, plain spelling
     * @param string $locale The Open Graph locale, e.g. pt_BR
     * @param array|null $image The image ({@see ogimage::for_page()}): url, type, width, height; or null
     * @param array $metas Name metas to write as literal strings, name => content, plain spelling, in order
     * @param string $headhtml The page's own raw head HTML, already cleared by local_page_head_html()
     */
    public function __construct(
        /** @var string The og:title. */
        private readonly string $title,
        /** @var string|null The og:description, or null. */
        private readonly ?string $description,
        /** @var string The canonical address. */
        private readonly string $url,
        /** @var string The site name. */
        private readonly string $sitename,
        /** @var string The Open Graph locale. */
        private readonly string $locale,
        /** @var array|null The image: url, type, width and height; or null. */
        private readonly ?array $image = null,
        /** @var array The name metas, name => content. */
        private readonly array $metas = [],
        /** @var string The page's own raw head HTML. */
        private readonly string $headhtml = '',
    ) {
    }

    /**
     * The tags of a page the viewer may read.
     *
     * @param object $page Row from {local_page} (stdClass) or {@see \local_page\custompage}
     * @param \moodle_url $canonical The page's canonical address
     * @return opengraph The tags
     * @throws \dml_missing_record_exception When the row's stored context id names no context
     */
    public static function for_page(object $page, \moodle_url $canonical): opengraph {
        global $CFG, $SITE;
        require_once($CFG->dirroot . '/local/page/lib.php');

        $context = scope::context($page);

        $metatitle = trim((string) ($page->metatitle ?? ''));
        $title = self::plain($metatitle !== '' ? $metatitle : (string) ($page->pagename ?? ''), $context);
        $description = self::plain((string) ($page->metadescription ?? ''), $context);
        $sitename = self::plain((string) $SITE->fullname, \core\context\system::instance());

        $metas = [
            'description' => $description,
            'keywords' => self::plain((string) ($page->metakeywords ?? ''), $context),
            'author' => self::plain((string) ($page->metaauthor ?? ''), $context),
            'robots' => (string) self::robots($page),
        ];

        return new opengraph(
            $title,
            ($description === '') ? null : $description,
            $canonical->out(false),
            $sitename,
            self::locale(),
            ogimage::for_page($page),
            array_filter($metas, static fn (string $content): bool => $content !== ''),
            local_page_head_html($page)
        );
    }

    /**
     * The robots directive of a page, or null for none.
     *
     * The page's own directive when its author wrote one. Otherwise a category page is marked noindex
     * unless the site is open to search engines ($CFG->opentowebcrawlers, "Open to search engines"
     * under Site security settings): a category page is there so that a shared link unfurls, not so
     * that a search engine lists it, and that is the site's decision, not each author's. A site-wide
     * page without a directive gets none.
     *
     * @param object $page Row from {local_page} (stdClass) or {@see \local_page\custompage}
     * @return string|null The directive, or null
     * @throws \dml_missing_record_exception When the row's stored context id names no context
     */
    public static function robots(object $page): ?string {
        global $CFG;

        $own = trim((string) ($page->metarobots ?? ''));
        if ($own !== '') {
            return $own;
        }

        if (scope::is_category($page) && empty($CFG->opentowebcrawlers)) {
            return 'noindex';
        }

        return null;
    }

    /**
     * The Open Graph spelling of a language.
     *
     * Moodle's language codes are lower case with an underscore (pt_br); Open Graph wants the
     * territory upper-cased (pt_BR). A direct copy is invalid, and a code without a territory is
     * passed through as it is.
     *
     * @param string|null $language A Moodle language code, null for the current language
     * @return string The locale
     */
    public static function locale(?string $language = null): string {
        $language = $language ?? current_language();
        if (preg_match('/^([a-z]{2,3})_([a-z]{2})$/', $language, $matches)) {
            return $matches[1] . '_' . strtoupper($matches[2]);
        }

        return $language;
    }

    /**
     * Remember the tags of the page being rendered.
     *
     * @param opengraph|null $tags The tags, or null to forget them
     * @return void
     */
    public static function set(?opengraph $tags): void {
        self::$current = $tags;
    }

    /**
     * The tags of the page being rendered, if any.
     *
     * @return opengraph|null The tags, or null
     */
    public static function current(): ?opengraph {
        return self::$current;
    }

    /**
     * The canonical address, plain spelling.
     *
     * @return string The URL
     */
    public function get_url(): string {
        return $this->url;
    }

    /**
     * The name metas the callback writes as literal strings.
     *
     * @return array name => content, plain spelling, only the non-empty ones, in the order written
     */
    public function get_metas(): array {
        return $this->metas;
    }

    /**
     * The page's own raw head HTML, written as it is.
     *
     * @return string The HTML, '' for none
     */
    public function get_headhtml(): string {
        return $this->headhtml;
    }

    /**
     * The template rendering this renderable.
     *
     * @param \core\output\renderer_base $renderer The renderer
     * @return string The template name
     */
    public function get_template_name(\core\output\renderer_base $renderer): string {
        return 'local_page/opengraph';
    }

    /**
     * Export the template context, plain spellings throughout.
     *
     * @param \core\output\renderer_base $output The renderer
     * @return \stdClass The template context
     */
    public function export_for_template(\core\output\renderer_base $output): \stdClass {
        return (object) [
            'title' => $this->title,
            'hasdescription' => $this->description !== null,
            'description' => $this->description,
            'url' => $this->url,
            'sitename' => $this->sitename,
            'locale' => $this->locale,
            'hasimage' => $this->image !== null,
            'imageurl' => $this->image['url'] ?? null,
            'imagetype' => $this->image['type'] ?? null,
            'hasimagesize' => isset($this->image['width'], $this->image['height']),
            'imagewidth' => $this->image['width'] ?? null,
            'imageheight' => $this->image['height'] ?? null,
        ];
    }

    /**
     * A stored text in the plain spelling: filtered and stripped of tags, never escaped.
     *
     * The meta fields are PARAM_TEXT, which keeps multilang spans, so they go through format_string()
     * like a heading would; escape off because the value is escaped where it is written.
     *
     * @param string $text The stored text
     * @param \core\context $context The context to format in
     * @return string The plain spelling, trimmed
     */
    private static function plain(string $text, \core\context $context): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        return trim(format_string($text, true, ['context' => $context, 'escape' => false]));
    }
}
