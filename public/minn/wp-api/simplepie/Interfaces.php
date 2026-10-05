<?php
/**
 * The interfaces the SimplePie classes implement (a cache, an HTTP
 * response, a part that takes the registry), the static helpers plugins
 * call on SimplePie\Misc, and the pre-namespace autoloader class.
 */

namespace SimplePie\Cache {
    interface Base
    {
        public const TYPE_FEED = 'spc';
        public const TYPE_IMAGE = 'spi';

        public function __construct(string $location, string $name, $type);

        public function save($data);

        public function load();

        public function mtime();

        public function touch();

        public function unlink();
    }
}

namespace SimplePie\HTTP {
    interface Response
    {
        public function get_permanent_uri(): string;

        public function get_final_requested_uri(): string;

        public function get_status_code(): int;

        public function get_headers(): array;

        public function has_header(string $name): bool;

        public function get_header(string $name): array;

        public function with_header(string $name, $value);

        public function get_header_line(string $name): string;

        public function get_body_content(): string;
    }
}

namespace SimplePie {
    use Minn\Feed\Dates;
    use Minn\Feed\Iri;

    interface RegistryAware
    {
        public function set_registry(Registry $registry);
    }

    /** The static helpers plugins reach for; the rest answer null. */
    class Misc
    {
        public static function absolutize_url($relative, $base)
        {
            return Iri::resolve((string) $base, (string) $relative) ?? false;
        }

        public static function is_remote_uri($uri)
        {
            return (bool) preg_match('#^https?://#i', (string) $uri);
        }

        public static function fix_protocol($url, $http = 1)
        {
            $url = trim((string) $url);
            if (preg_match('#^feed:(//)?#i', $url, $m)) {
                $url = substr($url, strlen($m[0]));
            }
            return $url === '' || preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) ? $url : 'http://' . $url;
        }

        public static function time_hms($seconds)
        {
            $seconds = (int) $seconds;
            $time = sprintf('%d:%02d', intdiv($seconds % 3600, 60), $seconds % 60);
            return $seconds >= 3600 ? intdiv($seconds, 3600) . ':' . str_pad($time, 5, '0', STR_PAD_LEFT) : $time;
        }

        public static function change_encoding($data, $input, $output)
        {
            try {
                return mb_convert_encoding((string) $data, (string) $output, (string) $input);
            } catch (\ValueError) {
                return false;
            }
        }

        public static function windows_1252_to_utf8($string)
        {
            return mb_convert_encoding((string) $string, 'UTF-8', 'Windows-1252');
        }

        public static function encoding($charset)
        {
            return (string) $charset;
        }

        public static function strip_comments($data)
        {
            return (string) preg_replace('/<!--.*?-->/s', '', (string) $data);
        }

        public static function parse_date($dt)
        {
            return Dates::parse((string) $dt) ?? false;
        }

        public static function entities_decode($data)
        {
            return html_entity_decode((string) $data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        public static function codepoint_to_utf8($codepoint)
        {
            $codepoint = (int) $codepoint;
            return $codepoint < 0 || $codepoint > 0x10FFFF ? false : mb_chr($codepoint, 'UTF-8');
        }

        public static function space_separated_tokens($string)
        {
            return preg_split('/[\x09\x0A\x0B\x0C\x0D\x20]+/', trim((string) $string), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        public static function url_remove_credentials($url)
        {
            return (string) preg_replace('#^([a-z][a-z0-9+.-]*://)[^/@]*@#i', '$1', (string) $url);
        }

        public static function get_build()
        {
            return SIMPLEPIE_BUILD;
        }

        public static function get_default_useragent()
        {
            return SIMPLEPIE_USERAGENT;
        }

        public static function __callStatic($method, $args)
        {
            \Minn\Runtime\PlaceholderTrace::hit('SimplePie\\Misc::' . $method);
            return null;
        }
    }
}

namespace {
    /** The pre-namespace autoloader: the engine's own loads the classes, so this one only forwards. */
    class SimplePie_Autoloader
    {
        public function __construct()
        {
        }

        public function autoload($class)
        {
            return class_exists((string) $class);
        }
    }
}
