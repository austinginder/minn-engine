<?php
/**
 * HTTP body compression as WP_Http reads and asks for it (probe
 * plugin-queue3): deflate out; in, whichever of raw deflate, a gzip or
 * zlib wrapper, or gzip with its header fields the body turns out to be,
 * else the body as it came.
 */
#[AllowDynamicProperties]
class WP_Http_Encoding
{
    public static function compress($raw, $level = 9, $supports = null)
    {
        return gzdeflate($raw, $level);
    }

    public static function decompress($compressed, $length = null)
    {
        if (empty($compressed)) {
            return $compressed;
        }
        foreach ([static fn ($data) => @gzinflate($data), [self::class, 'compatible_gzinflate'], static fn ($data) => @gzuncompress($data), static fn ($data) => @gzdecode($data)] as $read) {
            $decompressed = $read($compressed);
            if ($decompressed !== false) {
                return $decompressed;
            }
        }
        return $compressed;
    }

    /** A gzip body past its header, or a zlib one past its two bytes, inflated; false when neither inflates (Minn\\Http\\RawResponse). */
    public static function compatible_gzinflate($gz_data)
    {
        return Minn\Http\RawResponse::inflateLoose((string) $gz_data);
    }

    /** The Accept-Encoding a request sends: none when decompressing is off, the body streams to a file, or only part of it is asked for. */
    public static function accept_encoding($url, $args)
    {
        $enabled = self::is_available() && !empty($args['decompress']) && empty($args['stream']) && !isset($args['limit_response_size']);
        $types = $enabled ? ['deflate;q=1.0', 'compress;q=0.5', 'gzip;q=0.5'] : [];
        return implode(', ', (array) apply_filters('wp_http_accept_encoding', $types, $url, $args));
    }

    public static function content_encoding()
    {
        return 'deflate';
    }

    /** Whether a response says its body is encoded, by its headers as an array or as raw text. */
    public static function should_decode($headers)
    {
        if (is_array($headers)) {
            return !empty($headers['content-encoding']);
        }
        return is_string($headers) && stripos($headers, 'content-encoding:') !== false;
    }

    public static function is_available()
    {
        return function_exists('gzuncompress') || function_exists('gzdeflate') || function_exists('gzinflate');
    }
}
