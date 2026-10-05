<?php
/** The HTML API's character reference decoder; the work is Minn\Html\Decoder's. */

use Minn\Html\Decoder;

class WP_HTML_Decoder
{
    public static function attribute_starts_with($haystack, $search_text, $case_sensitivity = 'case-sensitive')
    {
        $decoded = substr(Decoder::attribute((string) $haystack), 0, strlen((string) $search_text));
        return $case_sensitivity === 'ascii-case-insensitive' ? strcasecmp($decoded, (string) $search_text) === 0 : $decoded === (string) $search_text;
    }

    public static function decode_text_node($text)
    {
        return Decoder::text((string) $text);
    }

    public static function decode_attribute($text)
    {
        return Decoder::attribute((string) $text);
    }

    public static function decode($context, $text)
    {
        return $context === 'attribute' ? Decoder::attribute((string) $text) : Decoder::text((string) $text);
    }

    public static function read_character_reference($context, $text, $at = 0, &$match_byte_length = null)
    {
        $found = Decoder::reference((string) $context, (string) $text, (int) $at);
        if ($found === null) {
            return null;
        }
        $match_byte_length = $found[1];
        return $found[0];
    }

    public static function code_point_to_utf8_bytes($code_point)
    {
        return Decoder::char((int) $code_point);
    }
}
