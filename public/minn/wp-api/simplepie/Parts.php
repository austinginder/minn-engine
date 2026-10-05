<?php
/**
 * The smaller SimplePie objects an item hands out (authors, categories,
 * enclosures and their captions, credits, copyrights, ratings and
 * restrictions, an entry's source), the fetched file, the sanitizer, and
 * the registry plugins reach through get_registry().
 */

namespace SimplePie;

use Minn\Feed\Iri;
use Minn\Feed\Tree;
use ReflectionClass;

class Author
{
    public $name;
    public $link;
    public $email;

    public function __construct($name = null, $link = null, $email = null)
    {
        $this->name = $name;
        $this->link = $link;
        $this->email = $email;
    }

    public function __toString()
    {
        return md5(serialize($this));
    }

    public function get_name()
    {
        return $this->name;
    }

    public function get_link()
    {
        return $this->link;
    }

    public function get_email()
    {
        return $this->email;
    }
}

class Category
{
    public $term;
    public $scheme;
    public $label;
    public $type;

    public function __construct($term = null, $scheme = null, $label = null, $type = null)
    {
        $this->term = $term;
        $this->scheme = $scheme;
        $this->label = $label;
        $this->type = $type;
    }

    public function __toString()
    {
        return md5(serialize($this));
    }

    public function get_term()
    {
        return $this->term;
    }

    public function get_scheme()
    {
        return $this->scheme;
    }

    /** The label, or the term when there is none (unless $strict). */
    public function get_label($strict = false)
    {
        return $this->label ?? ($strict ? null : $this->get_term());
    }

    public function get_type()
    {
        return $this->type;
    }
}

class Enclosure
{
    private const TYPES = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/x-m4a', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'wav' => 'audio/wav', 'aac' => 'audio/aac', 'mp4' => 'video/mp4', 'm4v' => 'video/x-m4v', 'mov' => 'video/quicktime', 'webm' => 'video/webm', 'ogv' => 'video/ogg', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml', 'pdf' => 'application/pdf', 'swf' => 'application/x-shockwave-flash'];

    public $bitrate;
    public $captions;
    public $categories;
    public $channels;
    public $copyright;
    public $credits;
    public $description;
    public $duration;
    public $expression;
    public $framerate;
    public $handler;
    public $hashes;
    public $height;
    public $javascript;
    public $keywords;
    public $lang;
    public $length;
    public $link;
    public $medium;
    public $player;
    public $ratings;
    public $restrictions;
    public $samplingrate;
    public $thumbnails;
    public $title;
    public $type;
    public $width;

    public function __construct($link = null, $type = null, $length = null, $javascript = null, $bitrate = null, $captions = null, $categories = null, $channels = null, $copyright = null, $credits = null, $description = null, $duration = null, $expression = null, $framerate = null, $hashes = null, $height = null, $keywords = null, $lang = null, $medium = null, $player = null, $ratings = null, $restrictions = null, $samplingrate = null, $thumbnails = null, $title = null, $width = null)
    {
        foreach (get_defined_vars() as $name => $value) {
            $this->{$name} = $value;
        }
        $this->handler = $this->get_handler();
    }

    public function __toString()
    {
        return md5(serialize($this));
    }

    public function get_bitrate()
    {
        return $this->bitrate;
    }

    public function get_caption($key = 0)
    {
        return $this->captions[$key] ?? null;
    }

    public function get_captions()
    {
        return $this->captions;
    }

    public function get_category($key = 0)
    {
        return $this->categories[$key] ?? null;
    }

    public function get_categories()
    {
        return $this->categories;
    }

    public function get_channels()
    {
        return $this->channels;
    }

    public function get_copyright()
    {
        return $this->copyright;
    }

    public function get_credit($key = 0)
    {
        return $this->credits[$key] ?? null;
    }

    public function get_credits()
    {
        return $this->credits;
    }

    public function get_description()
    {
        return $this->description;
    }

    /** Seconds, or "H:MM:SS" when converted. */
    public function get_duration($convert = false)
    {
        if ($this->duration === null || !$convert) {
            return $this->duration;
        }
        $seconds = (int) $this->duration;
        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }

    public function get_expression()
    {
        return $this->expression ?? 'full';
    }

    /** The file extension of the enclosure's URL path. */
    public function get_extension()
    {
        $path = (string) parse_url(html_entity_decode((string) $this->link, ENT_QUOTES, 'UTF-8'), PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return $extension === '' ? null : $extension;
    }

    public function get_framerate()
    {
        return $this->framerate;
    }

    public function get_handler()
    {
        return $this->get_real_type(true);
    }

    public function get_hash($key = 0)
    {
        return $this->hashes[$key] ?? null;
    }

    public function get_hashes()
    {
        return $this->hashes;
    }

    public function get_height()
    {
        return $this->height;
    }

    public function get_language()
    {
        return $this->lang;
    }

    public function get_keyword($key = 0)
    {
        return $this->keywords[$key] ?? null;
    }

    public function get_keywords()
    {
        return $this->keywords;
    }

    public function get_length()
    {
        return $this->length;
    }

    public function get_link()
    {
        return $this->link;
    }

    public function get_medium()
    {
        return $this->medium;
    }

    public function get_player()
    {
        return $this->player;
    }

    public function get_rating($key = 0)
    {
        return $this->ratings[$key] ?? null;
    }

    public function get_ratings()
    {
        return $this->ratings;
    }

    public function get_restriction($key = 0)
    {
        return $this->restrictions[$key] ?? null;
    }

    public function get_restrictions()
    {
        return $this->restrictions;
    }

    public function get_sampling_rate()
    {
        return $this->samplingrate;
    }

    /** The length in megabytes, two decimals. */
    public function get_size()
    {
        return $this->length === null ? null : round((int) $this->length / 1048576, 2);
    }

    public function get_thumbnail($key = 0)
    {
        return $this->thumbnails[$key] ?? null;
    }

    public function get_thumbnails()
    {
        return $this->thumbnails;
    }

    public function get_title()
    {
        return $this->title;
    }

    public function get_type()
    {
        return $this->type;
    }

    public function get_width()
    {
        return $this->width;
    }

    public function native_embed($options = '')
    {
        return $this->embed($options, true);
    }

    /** No player is printed for an enclosure: the plugins that embedded them used Flash. */
    public function embed($options = '', $native = false)
    {
        return '';
    }

    /** The MIME type, from the enclosure's type or its extension; with $find_handler, the player family. */
    public function get_real_type($find_handler = false)
    {
        $type = strtolower(trim((string) $this->type));
        if ($type === '') {
            $type = self::TYPES[strtolower((string) $this->get_extension())] ?? '';
        }
        if (!$find_handler) {
            return $type === '' ? null : $type;
        }
        return match (true) {
            $type === 'application/x-shockwave-flash' => 'flash',
            in_array($type, ['audio/mpeg', 'audio/mp3', 'audio/x-mp3', 'audio/mpeg3', 'audio/x-mpeg'], true) => 'mp3',
            str_starts_with($type, 'video/x-ms-') || str_starts_with($type, 'audio/x-ms-') => 'wmedia',
            str_starts_with($type, 'video/') || str_starts_with($type, 'audio/') => 'quicktime',
            default => null,
        };
    }
}

class Caption
{
    public $type;
    public $lang;
    public $startTime;
    public $endTime;
    public $text;

    public function __construct($type = null, $lang = null, $startTime = null, $endTime = null, $text = null)
    {
        $this->type = $type;
        $this->lang = $lang;
        $this->startTime = $startTime;
        $this->endTime = $endTime;
        $this->text = $text;
    }

    public function __toString()
    {
        return md5(serialize($this));
    }

    public function get_endtime()
    {
        return $this->endTime;
    }

    public function get_language()
    {
        return $this->lang;
    }

    public function get_starttime()
    {
        return $this->startTime;
    }

    public function get_text()
    {
        return $this->text;
    }

    public function get_type()
    {
        return $this->type;
    }
}

class Credit
{
    public $role;
    public $scheme;
    public $name;

    public function __construct($role = null, $scheme = null, $name = null)
    {
        $this->role = $role;
        $this->scheme = $scheme;
        $this->name = $name;
    }

    public function __toString()
    {
        return md5(serialize($this));
    }

    public function get_role()
    {
        return $this->role;
    }

    public function get_scheme()
    {
        return $this->scheme;
    }

    public function get_name()
    {
        return $this->name;
    }
}

class Copyright
{
    public $url;
    public $label;

    public function __construct($url = null, $label = null)
    {
        $this->url = $url;
        $this->label = $label;
    }

    public function __toString()
    {
        return md5(serialize($this));
    }

    public function get_url()
    {
        return $this->url;
    }

    public function get_attribution()
    {
        return $this->label;
    }
}

class Rating
{
    public $scheme;
    public $value;

    public function __construct($scheme = null, $value = null)
    {
        $this->scheme = $scheme;
        $this->value = $value;
    }

    public function __toString()
    {
        return md5(serialize($this));
    }

    public function get_scheme()
    {
        return $this->scheme;
    }

    public function get_value()
    {
        return $this->value;
    }
}

class Restriction
{
    public $relationship;
    public $type;
    public $value;

    public function __construct($relationship = null, $type = null, $value = null)
    {
        $this->relationship = $relationship;
        $this->type = $type;
        $this->value = $value;
    }

    public function __toString()
    {
        return md5(serialize($this));
    }

    public function get_relationship()
    {
        return $this->relationship;
    }

    public function get_type()
    {
        return $this->type;
    }

    public function get_value()
    {
        return $this->value;
    }
}

/** The feed an Atom entry was copied from (atom:source), read like a small feed. */
class Source implements RegistryAware
{
    use MinnFields;

    public $item;
    public $data = [];
    protected $registry;

    public function __construct($item, $data)
    {
        $this->item = $item;
        $this->data = $data;
    }

    public function set_registry($registry)
    {
        $this->registry = $registry;
    }

    public function __toString()
    {
        return md5(serialize($this->data));
    }

    public function get_source_tags($namespace, $tag)
    {
        return $this->data['child'][$namespace][$tag] ?? null;
    }

    public function get_base($element = [])
    {
        return $this->item->get_base($element);
    }

    public function sanitize($data, $type, $base = '')
    {
        return $this->item->sanitize($data, $type, $base);
    }

    public function get_item()
    {
        return $this->item;
    }

    public function get_title()
    {
        return $this->minn_text([[Tree::ATOM_10, 'title', 'atom'], [Tree::ATOM_03, 'title', 'atom']]);
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
        return $this->minn_people('author') ?: null;
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
        return $this->minn_link_map([])[$rel] ?? null;
    }

    public function get_description()
    {
        return $this->minn_text([[Tree::ATOM_10, 'subtitle', 'atom'], [Tree::ATOM_03, 'tagline', 'atom']]);
    }

    public function get_copyright()
    {
        return $this->minn_text([[Tree::ATOM_10, 'rights', 'atom'], [Tree::ATOM_03, 'copyright', 'atom']]);
    }

    public function get_language()
    {
        return isset($this->data['xml_lang']) ? $this->sanitize((string) $this->data['xml_lang'], SIMPLEPIE_CONSTRUCT_TEXT) : null;
    }

    public function get_latitude()
    {
        return null;
    }

    public function get_longitude()
    {
        return null;
    }

    public function get_image_url()
    {
        return $this->minn_text([[Tree::ATOM_10, 'logo', SIMPLEPIE_CONSTRUCT_IRI], [Tree::ATOM_10, 'icon', SIMPLEPIE_CONSTRUCT_IRI]]);
    }

    protected function minn_tags(string $namespace, string $tag): ?array
    {
        return $this->get_source_tags($namespace, $tag);
    }

    protected function minn_children(): array
    {
        return (array) ($this->data['child'] ?? []);
    }

    protected function minn_feed(): SimplePie
    {
        return $this->item->get_feed();
    }

    protected function minn_raw_base(array $node, bool $forLink = false): string
    {
        return !empty($node['xml_base_explicit']) && isset($node['xml_base']) ? (string) $node['xml_base'] : $this->minn_feed()->minn_item_base();
    }
}

/** A fetched URL: the response's status, headers and body, through the WordPress HTTP API. */
class File implements HTTP\Response
{
    public $url;
    public $useragent;
    public $success = true;
    public $headers = [];
    public $body;
    public $status_code = 0;
    public $redirects = 0;
    public $error;
    public $method = SIMPLEPIE_FILE_SOURCE_REMOTE;
    public $permanent_url;

    public function __construct($url, $timeout = 10, $redirects = 5, $headers = null, $useragent = null, $force_fsockopen = false, $curl_options = [])
    {
        $this->url = $url;
        $this->permanent_url = $url;
        $this->useragent = $useragent;
        $args = ['timeout' => $timeout, 'redirection' => $redirects, 'reject_unsafe_urls' => true];
        if ($useragent !== null) {
            $args['user-agent'] = $useragent;
        }
        if (is_array($headers)) {
            $args['headers'] = $headers;
        }
        $response = wp_safe_remote_request((string) $url, $args);
        if (is_wp_error($response)) {
            $this->success = false;
            $this->error = 'WP HTTP Error: ' . $response->get_error_message();
            return;
        }
        foreach (wp_remote_retrieve_headers($response) as $name => $value) {
            $this->headers[strtolower((string) $name)] = is_array($value) ? implode(', ', $value) : (string) $value;
        }
        $this->status_code = (int) wp_remote_retrieve_response_code($response);
        $this->body = wp_remote_retrieve_body($response);
    }

    public function get_permanent_uri(): string
    {
        return (string) $this->permanent_url;
    }

    public function get_final_requested_uri(): string
    {
        return (string) $this->url;
    }

    public function get_status_code(): int
    {
        return (int) $this->status_code;
    }

    public function get_headers(): array
    {
        return array_map(static fn ($value): array => [(string) $value], (array) $this->headers);
    }

    public function has_header(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function get_header(string $name): array
    {
        return $this->has_header($name) ? [(string) $this->headers[strtolower($name)]] : [];
    }

    public function with_header(string $name, $value)
    {
        $copy = clone $this;
        $copy->headers[strtolower($name)] = is_array($value) ? implode(', ', $value) : (string) $value;
        return $copy;
    }

    public function get_header_line(string $name): string
    {
        return implode(', ', $this->get_header($name));
    }

    public function get_body_content(): string
    {
        return (string) $this->body;
    }

    /** @internal a file known by its URL only (a feed autodiscovery found), not fetched */
    public static function minn_named(string $url): static
    {
        $file = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        $file->url = $url;
        $file->permanent_url = $url;
        return $file;
    }
}

/**
 * Values as the feed hands them out: text escaped, URLs resolved against
 * their base and escaped, HTML through kses' post rules.
 */
class Sanitize implements RegistryAware
{
    public $remove_div = true;
    public $strip_comments = false;
    public $encode_instead_of_strip = false;
    public $strip_htmltags = [];
    public $strip_attributes = [];
    public $output_encoding = 'UTF-8';

    public function remove_div($enable = true)
    {
        $this->remove_div = (bool) $enable;
    }

    public function strip_htmltags($tags = '')
    {
        $this->strip_htmltags = is_array($tags) ? $tags : array_filter(explode(',', (string) $tags));
    }

    public function encode_instead_of_strip($encode = false)
    {
        $this->encode_instead_of_strip = (bool) $encode;
    }

    public function strip_attributes($attribs = '')
    {
        $this->strip_attributes = is_array($attribs) ? $attribs : array_filter(explode(',', (string) $attribs));
    }

    public function strip_comments($strip = false)
    {
        $this->strip_comments = (bool) $strip;
    }

    public function set_output_encoding($encoding = 'UTF-8')
    {
        $this->output_encoding = (string) $encoding;
    }

    public function set_registry($registry)
    {
    }

    public function pass_cache_data($enable_cache = true, $cache_location = './cache', $cache_name_function = 'md5', $cache_class = null)
    {
    }

    public function pass_file_data($file_class = null, $timeout = 10, $useragent = '', $force_fsockopen = false)
    {
    }

    public function set_image_handler($page = false)
    {
    }

    public function set_url_replacements($element_attribute = null)
    {
    }

    public function set_https_domains($domains)
    {
    }

    public function add_attributes($attribs = '')
    {
    }

    public function rename_attributes($attribs = '')
    {
    }

    /** One value by construct type (SIMPLEPIE_CONSTRUCT_* bits). */
    public function sanitize($data, $type, $base = '')
    {
        $type = (int) $type;
        $data = (string) $data;
        if ($type & SIMPLEPIE_CONSTRUCT_BASE64) {
            $data = (string) base64_decode($data);
        }
        if ($type & SIMPLEPIE_CONSTRUCT_MAYBE_HTML) {
            $type |= preg_match('/<[a-zA-Z\/!]|&(?:#[0-9]+|#x[0-9a-fA-F]+|[a-zA-Z][a-zA-Z0-9]*);/', $data) ? SIMPLEPIE_CONSTRUCT_HTML : SIMPLEPIE_CONSTRUCT_TEXT;
        }
        if ($type & (SIMPLEPIE_CONSTRUCT_HTML | SIMPLEPIE_CONSTRUCT_XHTML)) {
            return $this->minn_html($data);
        }
        if ($type & SIMPLEPIE_CONSTRUCT_IRI) {
            $data = trim($data) === '' ? '' : (Iri::resolve((string) $base, $data) ?? Iri::clean($data));
            return htmlspecialchars($data, ENT_COMPAT, 'UTF-8');
        }
        if ($type & SIMPLEPIE_CONSTRUCT_TEXT) {
            return htmlspecialchars($data, ENT_COMPAT, 'UTF-8');
        }
        return $data;
    }

    protected function minn_html(string $data): string
    {
        return wp_kses_post($data);
    }
}

class Cache
{
    /** The cache a feed stores itself in: site transients. */
    public static function get_handler($location, $filename, $extension)
    {
        return new \WP_Feed_Cache_Transient($location, $filename, $extension);
    }
}

class Locator
{
}

class Parser
{
}

/** The class map a feed builds its parts from, as get_registry() exposes it. */
class Registry
{
    private const KINDS = ['Cache' => 'cache', 'Locator' => 'locator', 'Parser' => 'parser', 'File' => 'file', 'Sanitize' => 'sanitize', 'Item' => 'item', 'Author' => 'author', 'Category' => 'category', 'Enclosure' => 'enclosure', 'Caption' => 'caption', 'Copyright' => 'copyright', 'Credit' => 'credit', 'Rating' => 'rating', 'Restriction' => 'restriction', 'Source' => 'source', 'Content_Type_Sniffer' => 'content_type_sniffer'];

    private $feed;

    public function __construct($feed = null)
    {
        $this->feed = $feed;
    }

    public function register($type, $class, $legacy = false)
    {
        $method = 'set_' . self::kind($type) . '_class';
        return $this->feed !== null && method_exists($this->feed, $method) ? $this->feed->{$method}($class) : false;
    }

    public function get_class($type)
    {
        return $this->feed?->minn_class(self::kind($type));
    }

    public function create($type, $parameters = [])
    {
        $class = $this->get_class($type);
        return $class === null ? null : new $class(...array_values((array) $parameters));
    }

    public function call($type, $method, $parameters = [])
    {
        $class = $this->get_class($type);
        return $class === null ? null : $class::$method(...array_values((array) $parameters));
    }

    private static function kind($type): string
    {
        $short = str_replace(['SimplePie\\Content\\Type\\Sniffer', 'SimplePie\\', 'SimplePie_'], ['Content_Type_Sniffer', '', ''], ltrim((string) $type, '\\'));
        return self::KINDS[$short] ?? strtolower($short);
    }
}
