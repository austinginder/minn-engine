<?php
/**
 * The feed object fetch_feed() returns, named and shaped as plugins know it
 * (the SimplePie API), over the engine's own reader: Minn\Feed parses,
 * the WordPress HTTP API fetches (so its filters apply), the parsed tree
 * is cached in site transients the way the reference caches it, and HTML
 * goes through kses. Original implementation from captured behavior
 * (contracts/runtime.md, "Feeds").
 */

namespace SimplePie;

use Minn\Feed\Document;
use Minn\Feed\FeedError;
use Minn\Feed\Iri;
use Minn\Feed\Locator as FeedLocator;
use Minn\Feed\Tree;

class SimplePie
{
    use MinnFields;

    public $data = [];
    public $error;
    public $status_code = 0;
    public $sanitize;
    public $useragent = SIMPLEPIE_USERAGENT;
    public $feed_url;
    public $permanent_url;
    public $raw_data;
    public $timeout = 10;
    public $curl_options = [];
    public $force_fsockopen = false;
    public $force_feed = false;
    public $force_cache_fallback = false;
    public $cache_duration = 3600;
    public $autodiscovery_cache_duration = 604800;
    public $cache_location = './cache';
    public $cache_name_function = 'md5';
    public $order_by_date = true;
    public $input_encoding = false;
    public $autodiscovery = SIMPLEPIE_LOCATOR_ALL;
    public $registry;
    public $max_checked_feeds = 10;
    public $all_discovered_feeds = [];
    public $image_handler = '';
    public $multifeed_url = [];
    public $multifeed_objects = [];
    public $config_settings;
    public $item_limit = 0;
    public $check_modified = false;
    public $strip_attributes = [];
    public $add_attributes = [];
    public $strip_htmltags = [];
    public $rename_attributes = [];
    public $enable_exceptions = false;

    private bool $minn_cache = true;
    private string $minn_output = 'UTF-8';
    private ?Document $minn_document = null;
    private ?array $minn_ordered = null;
    private ?array $minn_links = null;
    private ?File $minn_file = null;
    /** @var array<string, string> */
    private array $minn_classes = [
        'cache' => 'SimplePie\\Cache', 'locator' => 'SimplePie\\Locator', 'parser' => 'SimplePie\\Parser', 'file' => 'SimplePie\\File',
        'sanitize' => 'SimplePie\\Sanitize', 'item' => 'SimplePie\\Item', 'author' => 'SimplePie\\Author', 'category' => 'SimplePie\\Category',
        'enclosure' => 'SimplePie\\Enclosure', 'caption' => 'SimplePie\\Caption', 'copyright' => 'SimplePie\\Copyright', 'credit' => 'SimplePie\\Credit',
        'rating' => 'SimplePie\\Rating', 'restriction' => 'SimplePie\\Restriction', 'content_type_sniffer' => 'SimplePie\\Content\\Type\\Sniffer', 'source' => 'SimplePie\\Source',
    ];

    public function __construct()
    {
        $this->sanitize = new Sanitize();
        $this->registry = new Registry($this);
    }

    public function __toString()
    {
        return md5(serialize($this->data));
    }

    public function __destruct()
    {
    }

    public function force_feed($enable = false)
    {
        $this->force_feed = (bool) $enable;
    }

    public function set_feed_url($url)
    {
        $this->multifeed_url = [];
        if (is_array($url)) {
            $this->multifeed_url = array_map(static fn ($one) => self::minn_fix_protocol((string) $one), array_values($url));
            return;
        }
        $this->feed_url = self::minn_fix_protocol((string) $url);
        $this->permanent_url = $this->feed_url;
    }

    public function set_file($file)
    {
        if ($file instanceof File) {
            $this->feed_url = $file->url;
            $this->permanent_url = $file->url;
            $this->minn_file = $file;
            return true;
        }
        return false;
    }

    public function set_raw_data($data)
    {
        $this->raw_data = $data;
    }

    public function set_http_client($http_client, $request_factory, $uri_factory)
    {
    }

    public function set_timeout($timeout = 10)
    {
        $this->timeout = (int) $timeout;
    }

    public function set_curl_options(array $curl_options = [])
    {
        $this->curl_options = $curl_options;
    }

    public function force_fsockopen($enable = false)
    {
        $this->force_fsockopen = (bool) $enable;
    }

    public function enable_cache($enable = true)
    {
        $this->minn_cache = (bool) $enable;
    }

    public function set_cache($cache)
    {
    }

    public function force_cache_fallback($enable = false)
    {
        $this->force_cache_fallback = (bool) $enable;
    }

    public function set_cache_duration($seconds = 3600)
    {
        $this->cache_duration = (int) $seconds;
    }

    public function set_autodiscovery_cache_duration($seconds = 604800)
    {
        $this->autodiscovery_cache_duration = (int) $seconds;
    }

    public function set_cache_location($location = './cache')
    {
        $this->cache_location = (string) $location;
    }

    public function get_cache_filename($url)
    {
        return call_user_func(is_callable($this->cache_name_function) ? $this->cache_name_function : 'md5', (string) $url);
    }

    public function enable_order_by_date($enable = true)
    {
        $this->order_by_date = (bool) $enable;
    }

    public function set_input_encoding($encoding = false)
    {
        $this->input_encoding = $encoding ? (string) $encoding : false;
    }

    public function set_autodiscovery_level($level = SIMPLEPIE_LOCATOR_ALL)
    {
        $this->autodiscovery = (int) $level;
    }

    public function get_registry()
    {
        return $this->registry;
    }

    public function set_cache_class($class = 'SimplePie\\Cache')
    {
        return $this->minn_set_class('cache', $class);
    }

    public function set_locator_class($class = 'SimplePie\\Locator')
    {
        return $this->minn_set_class('locator', $class);
    }

    public function set_parser_class($class = 'SimplePie\\Parser')
    {
        return $this->minn_set_class('parser', $class);
    }

    public function set_file_class($class = 'SimplePie\\File')
    {
        return $this->minn_set_class('file', $class);
    }

    public function set_sanitize_class($class = 'SimplePie\\Sanitize')
    {
        $set = $this->minn_set_class('sanitize', $class);
        if ($set) {
            $this->sanitize = new $class();
        }
        return $set;
    }

    public function set_item_class($class = 'SimplePie\\Item')
    {
        return $this->minn_set_class('item', $class);
    }

    public function set_author_class($class = 'SimplePie\\Author')
    {
        return $this->minn_set_class('author', $class);
    }

    public function set_category_class($class = 'SimplePie\\Category')
    {
        return $this->minn_set_class('category', $class);
    }

    public function set_enclosure_class($class = 'SimplePie\\Enclosure')
    {
        return $this->minn_set_class('enclosure', $class);
    }

    public function set_caption_class($class = 'SimplePie\\Caption')
    {
        return $this->minn_set_class('caption', $class);
    }

    public function set_copyright_class($class = 'SimplePie\\Copyright')
    {
        return $this->minn_set_class('copyright', $class);
    }

    public function set_credit_class($class = 'SimplePie\\Credit')
    {
        return $this->minn_set_class('credit', $class);
    }

    public function set_rating_class($class = 'SimplePie\\Rating')
    {
        return $this->minn_set_class('rating', $class);
    }

    public function set_restriction_class($class = 'SimplePie\\Restriction')
    {
        return $this->minn_set_class('restriction', $class);
    }

    public function set_content_type_sniffer_class($class = 'SimplePie\\Content\\Type\\Sniffer')
    {
        return $this->minn_set_class('content_type_sniffer', $class);
    }

    public function set_source_class($class = 'SimplePie\\Source')
    {
        return $this->minn_set_class('source', $class);
    }

    public function set_useragent($ua = null)
    {
        $this->useragent = $ua === null ? SIMPLEPIE_USERAGENT : (string) $ua;
    }

    public function set_cache_namefilter($filter)
    {
    }

    public function set_cache_name_function($function = null)
    {
        $this->cache_name_function = $function ?? 'md5';
    }

    public function set_stupidly_fast($set = false)
    {
        if ($set) {
            $this->enable_order_by_date(false);
            $this->strip_htmltags = [];
            $this->strip_attributes = [];
            $this->add_attributes = [];
        }
    }

    public function set_max_checked_feeds($max = 10)
    {
        $this->max_checked_feeds = (int) $max;
    }

    public function remove_div($enable = true)
    {
        $this->sanitize->remove_div($enable);
    }

    public function strip_htmltags($tags = '', $encode = null)
    {
        $this->strip_htmltags = is_array($tags) ? $tags : array_filter(explode(',', (string) $tags));
        $this->sanitize->strip_htmltags($tags);
    }

    public function encode_instead_of_strip($enable = true)
    {
        $this->sanitize->encode_instead_of_strip($enable);
    }

    public function rename_attributes($attribs = '')
    {
        $this->rename_attributes = is_array($attribs) ? $attribs : array_filter(explode(',', (string) $attribs));
    }

    public function strip_attributes($attribs = '')
    {
        $this->strip_attributes = is_array($attribs) ? $attribs : array_filter(explode(',', (string) $attribs));
        $this->sanitize->strip_attributes($attribs);
    }

    public function add_attributes($attribs = '')
    {
        $this->add_attributes = is_array($attribs) ? $attribs : [];
    }

    public function set_output_encoding($encoding = 'UTF-8')
    {
        $this->minn_output = (string) $encoding;
    }

    public function strip_comments($strip = false)
    {
        $this->sanitize->strip_comments($strip);
    }

    public function set_url_replacements($element_attribute = null)
    {
    }

    public function set_https_domains($domains = [])
    {
    }

    public function set_image_handler($page = false, $qs = 'i')
    {
        $this->image_handler = $page ? (string) $page . '?' . $qs . '=' : '';
    }

    public function set_favicon_handler($page = false, $qs = 'i')
    {
    }

    public function set_item_limit($limit = 0)
    {
        $this->item_limit = (int) $limit;
    }

    public function enable_exceptions($enable = true)
    {
        $this->enable_exceptions = (bool) $enable;
    }

    /** Reads the feed: from the cache when it holds it, else fetched (with autodiscovery for a page) and parsed. */
    public function init()
    {
        $this->error = null;
        $this->minn_document = null;
        $this->minn_ordered = null;
        $this->minn_links = null;
        if ($this->multifeed_url !== []) {
            return $this->minn_init_multifeed();
        }
        if (($this->feed_url ?? '') === '' && $this->minn_file === null) {
            return is_string($this->raw_data) && $this->raw_data !== '' && $this->minn_read('', $this->raw_data, '', []);
        }
        $url = (string) $this->feed_url;
        $cache = $this->minn_cache ? $this->minn_make_cache($url) : null;
        $cached = $cache?->load();
        if (is_array($cached) && isset($cached['child']) && is_array($cached['child'])) {
            $this->data = $cached;
            $this->feed_url = (string) ($cached['url'] ?? $url);
            $this->minn_document = Document::fromTree(['child' => $cached['child']]);
            $this->status_code = 200;
            return true;
        }
        if (!$this->minn_fetch($url)) {
            return false;
        }
        $cache?->save($this->data);
        return true;
    }

    public function error()
    {
        return $this->error;
    }

    public function status_code()
    {
        return $this->status_code;
    }

    public function get_raw_data()
    {
        return $this->raw_data;
    }

    public function get_encoding()
    {
        return $this->minn_output;
    }

    public function handle_content_type($mime = 'text/html')
    {
        if (!headers_sent()) {
            header('Content-type: ' . $mime . '; charset=' . $this->minn_output);
        }
    }

    public function get_type()
    {
        return $this->minn_document?->type ?? SIMPLEPIE_TYPE_NONE;
    }

    public function subscribe_url($permanent = false)
    {
        $url = $permanent ? $this->permanent_url : $this->feed_url;
        return $url === null || $url === '' ? null : $this->sanitize((string) $url, SIMPLEPIE_CONSTRUCT_IRI);
    }

    public function get_feed_tags($namespace, $tag)
    {
        return $this->minn_document?->feedTags((string) $namespace, (string) $tag);
    }

    public function get_channel_tags($namespace, $tag)
    {
        return $this->minn_document?->channelTags((string) $namespace, (string) $tag);
    }

    public function get_image_tags($namespace, $tag)
    {
        return $this->minn_document?->imageTags((string) $namespace, (string) $tag);
    }

    public function get_base($element = [])
    {
        if (!empty($element['xml_base_explicit']) && isset($element['xml_base'])) {
            return $element['xml_base'];
        }
        return $this->get_link() ?? $this->subscribe_url();
    }

    public function sanitize($data, $type, $base = '')
    {
        return $this->sanitize->sanitize($data, $type, $base);
    }

    public function get_title()
    {
        return $this->minn_text([
            [Tree::ATOM_10, 'title', 'atom'], [Tree::ATOM_03, 'title', 'atom'],
            [Tree::RSS_10, 'title', SIMPLEPIE_CONSTRUCT_HTML], [Tree::RSS_090, 'title', SIMPLEPIE_CONSTRUCT_HTML], ['', 'title', SIMPLEPIE_CONSTRUCT_HTML],
            [SIMPLEPIE_NAMESPACE_DC_11, 'title', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_10, 'title', SIMPLEPIE_CONSTRUCT_TEXT],
        ]);
    }

    public function get_category($key = 0)
    {
        return $this->get_categories()[$key] ?? null;
    }

    public function get_categories()
    {
        return $this->minn_category_list() ?: null;
    }

    public function get_author($key = 0)
    {
        return $this->get_authors()[$key] ?? null;
    }

    public function get_authors()
    {
        return [...$this->minn_people('author'), ...$this->minn_creators()] ?: null;
    }

    public function get_contributor($key = 0)
    {
        return $this->get_contributors()[$key] ?? null;
    }

    public function get_contributors()
    {
        return $this->minn_people('contributor') ?: null;
    }

    public function get_link($key = 0, $rel = 'alternate')
    {
        return $this->get_links($rel)[$key] ?? null;
    }

    public function get_permalink()
    {
        return $this->get_link(0);
    }

    public function get_links($rel = 'alternate')
    {
        if ($this->minn_document === null) {
            return null;
        }
        $this->minn_links ??= $this->minn_link_map([[Tree::RSS_10, 'link'], [Tree::RSS_090, 'link'], ['', 'link']]);
        return $this->minn_links[$rel] ?? null;
    }

    public function get_all_discovered_feeds()
    {
        return $this->all_discovered_feeds;
    }

    public function get_description()
    {
        return $this->minn_text([
            [Tree::ATOM_10, 'subtitle', 'atom'], [Tree::ATOM_03, 'tagline', 'atom'],
            [Tree::RSS_10, 'description', SIMPLEPIE_CONSTRUCT_HTML], [Tree::RSS_090, 'description', SIMPLEPIE_CONSTRUCT_HTML], ['', 'description', SIMPLEPIE_CONSTRUCT_HTML],
            [SIMPLEPIE_NAMESPACE_DC_11, 'description', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_10, 'description', SIMPLEPIE_CONSTRUCT_TEXT],
            [SIMPLEPIE_NAMESPACE_ITUNES, 'summary', SIMPLEPIE_CONSTRUCT_HTML], [SIMPLEPIE_NAMESPACE_ITUNES, 'subtitle', SIMPLEPIE_CONSTRUCT_HTML],
        ]);
    }

    public function get_copyright()
    {
        return $this->minn_text([
            [Tree::ATOM_10, 'rights', 'atom'], [Tree::ATOM_03, 'copyright', 'atom'], ['', 'copyright', SIMPLEPIE_CONSTRUCT_TEXT],
            [SIMPLEPIE_NAMESPACE_DC_11, 'rights', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_10, 'rights', SIMPLEPIE_CONSTRUCT_TEXT],
        ]);
    }

    /** The channel's language; an Atom or RSS 1.0 feed without one answers with its root's xml:lang. */
    public function get_language()
    {
        $language = $this->minn_text([['', 'language', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_11, 'language', SIMPLEPIE_CONSTRUCT_TEXT], [SIMPLEPIE_NAMESPACE_DC_10, 'language', SIMPLEPIE_CONSTRUCT_TEXT]]);
        if ($language !== null || $this->minn_document === null) {
            return $language;
        }
        $type = $this->minn_document->type;
        return $type >= Document::ATOM_03 || $type === Document::RSS_10 || $type === Document::RSS_090 ? (string) ($this->minn_document->root()['xml_lang'] ?? '') : null;
    }

    public function get_latitude()
    {
        return $this->minn_coordinate(0);
    }

    public function get_longitude()
    {
        return $this->minn_coordinate(1);
    }

    public function get_image_title()
    {
        return $this->minn_image_text('title', SIMPLEPIE_CONSTRUCT_TEXT);
    }

    public function get_image_url()
    {
        return $this->minn_text([[Tree::ATOM_10, 'logo', SIMPLEPIE_CONSTRUCT_IRI], [Tree::ATOM_10, 'icon', SIMPLEPIE_CONSTRUCT_IRI]])
            ?? $this->minn_image_text('url', SIMPLEPIE_CONSTRUCT_IRI)
            ?? $this->minn_itunes_image();
    }

    public function get_image_link()
    {
        return $this->minn_image_text('link', SIMPLEPIE_CONSTRUCT_IRI);
    }

    /** The image's width; an RSS image that gives none is 88 wide. */
    public function get_image_width()
    {
        $width = $this->get_image_tags('', 'width')[0]['data'] ?? null;
        return $width !== null ? (int) round((float) $width) : ($this->minn_document?->image() !== null ? 88 : null);
    }

    /** The image's height; an RSS image that gives none is 31 high. */
    public function get_image_height()
    {
        $height = $this->get_image_tags('', 'height')[0]['data'] ?? null;
        return $height !== null ? (int) round((float) $height) : ($this->minn_document?->image() !== null ? 31 : null);
    }

    public function get_item_quantity($max = 0)
    {
        $quantity = count($this->minn_items());
        return (int) $max === 0 ? $quantity : min($quantity, (int) $max);
    }

    public function get_item($key = 0)
    {
        return $this->minn_items()[(int) $key] ?? null;
    }

    public function get_items($start = 0, $end = 0)
    {
        return array_slice($this->minn_items(), (int) $start, (int) $end === 0 ? null : (int) $end);
    }

    public function get_favicon()
    {
        return null;
    }

    public function __call($method, $args)
    {
        \Minn\Runtime\PlaceholderTrace::hit('SimplePie::' . $method);
        return null;
    }

    /** Newer first, for items that carry dates. */
    public static function sort_items($a, $b)
    {
        return $a->get_date('U') <= $b->get_date('U') ? 1 : -1;
    }

    /** The items of several feeds as one list, newest first (undated ones lead), each feed contributing at most $limit. */
    public static function merge_items($urls, $start = 0, $end = 0, $limit = 0)
    {
        $items = [];
        foreach ((array) $urls as $feed) {
            if ($feed instanceof self) {
                array_push($items, ...$feed->get_items(0, (int) $limit));
            }
        }
        return array_slice(self::minn_sort($items), (int) $start, (int) $end === 0 ? null : (int) $end);
    }

    /** @internal the class a part of the feed is built with */
    public function minn_class(string $kind): string
    {
        return $this->minn_classes[$kind];
    }

    /** @internal the base a relative URL in an item resolves against: the channel's own link, else the feed's URL */
    public function minn_item_base(): string
    {
        return $this->minn_channel_link() ?? (string) $this->feed_url;
    }

    protected function minn_tags(string $namespace, string $tag): ?array
    {
        return $this->get_channel_tags($namespace, $tag);
    }

    protected function minn_children(): array
    {
        return (array) ($this->minn_document?->channel()['child'] ?? []);
    }

    protected function minn_feed(): SimplePie
    {
        return $this;
    }

    protected function minn_raw_base(array $node, bool $forLink = false): string
    {
        if (!empty($node['xml_base_explicit']) && isset($node['xml_base'])) {
            return (string) $node['xml_base'];
        }
        return $forLink ? (string) $this->feed_url : $this->minn_item_base();
    }

    private function minn_set_class(string $kind, $class): bool
    {
        if (!is_string($class) || !class_exists($class)) {
            return false;
        }
        $this->minn_classes[$kind] = $class;
        return true;
    }

    private static function minn_fix_protocol(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (preg_match('#^feed:(//)?#i', $url, $m)) {
            $rest = substr($url, strlen($m[0]));
            return preg_match('#^[a-z][a-z0-9+.-]*:#i', $rest) ? $rest : 'http://' . $rest;
        }
        return preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) ? $url : 'http://' . $url;
    }

    private function minn_make_cache(string $url): object
    {
        $class = $this->minn_classes['cache'] === 'SimplePie\\Cache' ? \WP_Feed_Cache_Transient::class : $this->minn_classes['cache'];
        return new $class($this->cache_location, $this->get_cache_filename($url), 'spc');
    }

    /** Fetches the URL; a page that is not a feed is searched for the feeds it names. */
    private function minn_fetch(string $url): bool
    {
        $file = $this->minn_file ?? $this->minn_get($url);
        if (!$file->success) {
            $this->error = $file->error;
            return false;
        }
        $type = (string) ($file->headers['content-type'] ?? '');
        if ($this->force_feed || FeedLocator::looksLikeFeed((string) $file->body, $type)) {
            return $this->minn_read($url, (string) $file->body, $type, (array) $file->headers);
        }
        $candidates = $this->autodiscovery ? FeedLocator::alternates((string) $file->body, $url) : [];
        $this->all_discovered_feeds = array_map(static fn (string $found): File => File::minn_named($found), $candidates);
        foreach (array_slice($candidates, 0, max(1, (int) $this->max_checked_feeds)) as $candidate) {
            $found = $this->minn_get($candidate);
            if ($found->success && FeedLocator::looksLikeFeed((string) $found->body, (string) ($found->headers['content-type'] ?? ''))) {
                $this->feed_url = $candidate;
                return $this->minn_read($candidate, (string) $found->body, (string) ($found->headers['content-type'] ?? ''), (array) $found->headers);
            }
        }
        $this->error = sprintf('A feed could not be found at `%s`; the status code is `%s` and content-type is `%s`', $url, $file->status_code, $type);
        return false;
    }

    private function minn_get(string $url): File
    {
        $class = $this->minn_classes['file'];
        $file = new $class($url, $this->timeout, 5, null, $this->useragent, $this->force_fsockopen, $this->curl_options);
        if ($file->success && ($file->status_code < 200 || $file->status_code > 299)) {
            $file->success = false;
            $file->error = sprintf('Retrieved unsupported status code "%d"', $file->status_code);
        }
        $this->status_code = (int) $file->status_code;
        return $file;
    }

    /** @param array<string, mixed> $headers */
    private function minn_read(string $url, string $body, string $type, array $headers): bool
    {
        try {
            $document = Document::parse($body, $this->input_encoding ? 'charset=' . $this->input_encoding : $type);
        } catch (FeedError $error) {
            $this->error = $url . ' is invalid XML, likely due to invalid characters. ' . $error->getMessage();
            return false;
        }
        if ($document->type === Document::NONE) {
            $this->error = sprintf('A feed could not be found at `%s`; the status code is `%s` and content-type is `%s`', $url, $this->status_code, $type);
            return false;
        }
        $this->minn_document = $document;
        $this->raw_data = $body;
        $this->data = ['child' => $document->tree['child'] ?? [], 'type' => $document->type, 'headers' => $headers, 'build' => SIMPLEPIE_BUILD, 'url' => $url];
        return true;
    }

    /** Each URL read as a feed of its own with this one's settings; the errors are collected. */
    private function minn_init_multifeed(): bool
    {
        $this->multifeed_objects = array_map(function (string $url): SimplePie {
            $feed = clone $this;
            [$feed->multifeed_url, $feed->multifeed_objects, $feed->data] = [[], [], []];
            $feed->set_feed_url($url);
            $feed->init();
            return $feed;
        }, $this->multifeed_url);
        $errors = array_values(array_filter(array_map(static fn (SimplePie $feed) => $feed->error(), $this->multifeed_objects), static fn ($error) => $error !== null));
        $this->error = $errors === [] ? null : $errors;
        return true;
    }

    /** @return list<object> */
    private function minn_items(): array
    {
        if ($this->minn_ordered !== null) {
            return $this->minn_ordered;
        }
        if ($this->multifeed_objects !== []) {
            return $this->minn_ordered = self::merge_items($this->multifeed_objects, 0, 0, (int) $this->item_limit);
        }
        $class = $this->minn_classes['item'];
        $items = array_map(fn (array $node) => new $class($this, $node), $this->minn_document?->items() ?? []);
        return $this->minn_ordered = $this->order_by_date ? self::minn_sort($items) : $items;
    }

    /** Undated items first, as they came; the dated ones newest first. */
    private static function minn_sort(array $items): array
    {
        $undated = array_values(array_filter($items, static fn ($item) => $item->get_date('U') === null));
        $dated = array_values(array_filter($items, static fn ($item) => $item->get_date('U') !== null));
        usort($dated, [self::class, 'sort_items']);
        return [...$undated, ...$dated];
    }

    /** The channel's own alternate link, resolved but not escaped. */
    private function minn_channel_link(): ?string
    {
        foreach ([Tree::ATOM_10, Tree::ATOM_03] as $ns) {
            foreach ($this->get_channel_tags($ns, 'link') ?? [] as $node) {
                if (in_array($node['attribs']['']['rel'] ?? 'alternate', ['alternate', SIMPLEPIE_IANA_LINK_RELATIONS_REGISTRY . 'alternate'], true) && isset($node['attribs']['']['href'])) {
                    return Iri::resolve($this->minn_raw_base($node, true), (string) $node['attribs']['']['href']);
                }
            }
        }
        foreach ([Tree::RSS_10, Tree::RSS_090, ''] as $ns) {
            $node = $this->get_channel_tags($ns, 'link')[0] ?? null;
            if ($node !== null && trim((string) $node['data']) !== '') {
                return Iri::resolve($this->minn_raw_base($node, true), (string) $node['data']) ?? trim((string) $node['data']);
            }
        }
        return null;
    }

    private function minn_image_text(string $tag, int $type): ?string
    {
        foreach ([Tree::RSS_10, Tree::RSS_090, ''] as $ns) {
            $node = $this->get_image_tags($ns, $tag)[0] ?? null;
            if ($node !== null && trim((string) $node['data']) !== '') {
                return $this->sanitize((string) $node['data'], $type, $this->minn_raw_base($node));
            }
        }
        return null;
    }

    private function minn_itunes_image(): ?string
    {
        $href = $this->get_channel_tags(SIMPLEPIE_NAMESPACE_ITUNES, 'image')[0]['attribs']['']['href'] ?? null;
        return $href === null ? null : $this->sanitize((string) $href, SIMPLEPIE_CONSTRUCT_IRI, $this->minn_item_base());
    }

    private function minn_coordinate(int $index): ?float
    {
        $point = $this->get_channel_tags(SIMPLEPIE_NAMESPACE_GEORSS, 'point')[0]['data'] ?? null;
        if ($point !== null && preg_match('/^\s*(-?\d+(?:\.\d+)?)[\s,]+(-?\d+(?:\.\d+)?)\s*$/', (string) $point, $m)) {
            return (float) $m[$index + 1];
        }
        $single = $this->get_channel_tags(SIMPLEPIE_NAMESPACE_W3C_BASIC_GEO, $index === 0 ? 'lat' : 'long')[0]['data'] ?? null;
        return $single === null ? null : (float) $single;
    }
}
