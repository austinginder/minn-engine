<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The shortcode registry plugin code fills with add_shortcode, and the
 * expansion do_shortcode performs: [tag attrs], [tag attrs/], [tag]…[/tag]
 * (the first closing tag wins; content is not expanded again), [[tag]] as
 * the literal, unregistered tags left as written.
 */
final class Shortcodes
{
    /** @var array<string, callable> */
    private array $tags = [];

    /**
     * The registry itself, by reference, so the $shortcode_tags global plugin
     * code reads and copies is this array and not a snapshot of it.
     *
     * @return array<string, callable>
     */
    public function &tags(): array
    {
        return $this->tags;
    }

    /** Registers a shortcode. */
    public function add(string $tag, callable $callback): void
    {
        $this->tags[$tag] = $callback;
    }

    /** Forgets a shortcode. */
    public function remove(string $tag): void
    {
        unset($this->tags[$tag]);
    }

    /** Forgets every shortcode. */
    public function removeAll(): void
    {
        $this->tags = [];
    }

    /** Whether a shortcode is registered. */
    public function has(string $tag): bool
    {
        return isset($this->tags[$tag]);
    }

    /**
     * Every shortcode with its callback.
     *
     * @return array<string, callable> every registered tag and its handler, to restore after a narrowed run
     */
    public function all(): array
    {
        return $this->tags;
    }

    /**
     * Replaces the registry, after a save-and-restore.
     *
     * @param array<string, callable> $tags
     */
    public function restore(array $tags): void
    {
        $this->tags = $tags;
    }

    /**
     * Every shortcode name.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->tags);
    }

    /** The regex matching the registered shortcodes, or null for none. */
    public function pattern(?array $tags = null): ?string
    {
        $names = $tags ?? array_keys($this->tags);
        if ($names === []) {
            return null;
        }
        $alternatives = implode('|', array_map(static fn (string $t) => preg_quote($t, '/'), $names));
        return '/\[(\[?)(' . $alternatives . ')(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]*+(?:\[(?!\/\2\])[^\[]*+)*+)\[\/\2\])?)(\]?)/s';
    }

    /**
     * Content with its shortcodes run (do_shortcode, probe shortcode-run):
     * only the registered names the text holds; inside HTML tags first
     * (inTags: run, or with 'escape' as with ignore_html, left alone),
     * then in the text, each through run(); the brackets set aside as
     * entities come back at the end. Images rendered meanwhile see the
     * do_shortcode context.
     */
    public function apply(string $content, string $tags = 'run'): string
    {
        $names = $this->present($content, array_keys($this->tags));
        if ($names === []) {
            return $content;
        }
        $hooks = Runtime::hooks();
        $context = $hooks->has('wp_get_attachment_image_context', '_filter_do_shortcode_context') === false;
        if ($context) {
            $hooks->add('wp_get_attachment_image_context', '_filter_do_shortcode_context');
        }
        $pattern = (string) $this->pattern($names);
        $content = (string) preg_replace_callback($pattern, fn (array $m): string => $this->run($m), $this->inTags($content, $tags, $names));
        if ($context) {
            $hooks->remove('wp_get_attachment_image_context', '_filter_do_shortcode_context');
        }
        return strtr($content, self::RESTORE);
    }

    /**
     * One matched shortcode (do_shortcode_tag): [[tag]] gives the tag as
     * written; otherwise pre_do_shortcode_tag may answer, or the callback
     * runs with the attributes, the content ("" when it closes itself) and
     * the name, and do_shortcode_tag sees the output in its brackets.
     *
     * @param array<int, string> $m the pattern's match
     */
    public function run(array $m): string
    {
        if ($m[1] === '[' && ($m[6] ?? '') === ']') {
            return substr($m[0], 1, -1);
        }
        $tag = $m[2];
        if (!isset($this->tags[$tag])) {
            return $m[0];
        }
        $attributes = self::parse($m[3] ?? '');
        $hooks = Runtime::hooks();
        $early = $hooks->filter('pre_do_shortcode_tag', [false, $tag, $attributes, $m]);
        if ($early !== false) {
            return $early;
        }
        $output = $m[1] . ($this->tags[$tag])($attributes, $m[5] ?? null, $tag) . ($m[6] ?? '');
        return $hooks->filter('do_shortcode_tag', [$output, $tag, $attributes, $m]);
    }

    /**
     * Shortcodes inside HTML tags (do_shortcodes_in_html_tags), run before
     * the text: a quoted attribute value's shortcode runs and the attribute
     * is judged again by kses, kept when anything is left; an unquoted one
     * (or one standing as a name or as the tag) runs as written. Comments,
     * CDATA and, with 'escape', every tag are left alone. Brackets left in a
     * tag (and entities already written for them) are set aside so the text
     * pass cannot reach them.
     *
     * @param list<string> $names
     */
    public function inTags(string $content, string $tags, array $names): string
    {
        $pattern = (string) $this->pattern($names);
        $run = function (string $text, ?int &$count = null) use ($pattern): string {
            return (string) preg_replace_callback($pattern, fn (array $m): string => $this->run($m), $text, -1, $count);
        };
        $pieces = \Minn\Support\Html::split(strtr($content, ['&#91;' => '&#091;', '&#93;' => '&#093;']));
        foreach ($pieces as $i => $piece) {
            if ($piece === '' || $piece[0] !== '<') {
                continue;
            }
            $open = str_contains($piece, '[');
            if (!$open || !str_contains($piece, ']')) {
                $pieces[$i] = $open || str_contains($piece, ']') ? strtr($piece, self::SET_ASIDE) : $piece;
                continue;
            }
            $parts = $tags === 'escape' || str_starts_with($piece, '<!--') || str_starts_with($piece, '<![CDATA[') ? null : \Minn\Support\KsesAttributes::elementParts($piece);
            if ($parts === null) {
                $runs = $tags !== 'escape' && !str_starts_with($piece, '<!') && preg_match('%^<\s*\[\[?[^\[\]]+\]%', $piece) === 1;
                $pieces[$i] = strtr($runs ? $run($piece) : $piece, self::SET_ASIDE);
                continue;
            }
            $pieces[$i] = strtr(self::attributesRun($parts, $run), self::SET_ASIDE);
        }
        return implode('', $pieces);
    }

    /**
     * Content with its shortcodes taken out (strip_shortcodes): the names
     * strip_shortcodes_tagnames leaves, never inside a tag; [[tag]] gives
     * the tag as written.
     */
    public function strip(string $content): string
    {
        $wanted = (array) Runtime::hooks()->filter('strip_shortcodes_tagnames', [array_keys($this->tags), $content]);
        $names = $this->present($content, $wanted);
        if ($names === []) {
            return $content;
        }
        $content = (string) preg_replace_callback((string) $this->pattern($names), [self::class, 'stripped'], $this->inTags($content, 'escape', $names));
        return strtr($content, self::RESTORE);
    }

    /** One matched shortcode taken out (strip_shortcode_tag): its outer brackets stay, [[tag]] gives the tag. @param array<int, string> $m */
    public static function stripped(array $m): string
    {
        return $m[1] === '[' && ($m[6] ?? '') === ']' ? substr($m[0], 1, -1) : $m[1] . ($m[6] ?? '');
    }

    /**
     * Shortcode attribute text as an array (shortcode_parse_atts): no-break
     * and zero-width spaces read as spaces; name=value pairs (names lower
     * case) and bare values in order, each a token followed by whitespace
     * or the end, backslash escapes undone; a value holding "<" that is not
     * whole tags becomes "".
     *
     * @return array<int|string, string>
     */
    public static function parse(string $text): array
    {
        $text = (string) preg_replace('/[\x{00a0}\x{200b}]+/u', ' ', $text);
        preg_match_all(self::ATTRIBUTES, $text, $matches, PREG_SET_ORDER);
        $out = [];
        foreach ($matches as $m) {
            $named = ($m[1] ?? '') !== '' ? 1 : (($m[3] ?? '') !== '' ? 3 : (($m[5] ?? '') !== '' ? 5 : 0));
            if ($named > 0) {
                $out[strtolower($m[$named])] = stripcslashes($m[$named + 1]);
                continue;
            }
            foreach ([7, 8, 9] as $group) {
                if (isset($m[$group]) && $m[$group] !== '') {
                    $out[] = stripcslashes($m[$group]);
                    break;
                }
            }
        }
        return array_map(static fn (string $value): string => str_contains($value, '<') && preg_match('/^[^<]*+(?:<[^>]*+>[^<]*+)*+$/', $value) !== 1 ? '' : $value, $out);
    }

    /** The attribute pattern shortcode_parse_atts reads by (get_shortcode_atts_regex). */
    public const ATTRIBUTES = '/([\w-]+)\s*=\s*"([^"]*)"(?:\s|$)|([\w-]+)\s*=\s*\'([^\']*)\'(?:\s|$)|([\w-]+)\s*=\s*([^\s\'"]+)(?:\s|$)|"([^"]*)"(?:\s|$)|\'([^\']*)\'(?:\s|$)|(\S+)(?:\s|$)/';

    private const SET_ASIDE = ['[' => '&#91;', ']' => '&#93;'];
    private const RESTORE = ['&#91;' => '[', '&#93;' => ']'];

    /**
     * The names among those given that the text opens a bracket with.
     *
     * @param list<string> $names
     * @return list<string>
     */
    private function present(string $content, array $names): array
    {
        if (!str_contains($content, '[') || $this->tags === []) {
            return [];
        }
        preg_match_all('@\[([^<>&/\[\]\x00-\x20=]++)@', $content, $found);
        return array_values(array_intersect($names, $found[1]));
    }

    /**
     * A tag's attributes with their shortcodes run, from elementParts().
     *
     * @param list<string> $parts
     */
    private static function attributesRun(array $parts, \Closure $run): string
    {
        $start = array_shift($parts);
        $end = array_pop($parts);
        preg_match('%[a-zA-Z0-9]+%', $start, $element);
        foreach ($parts as $i => $attribute) {
            $open = strpos($attribute, '[');
            if ($open === false || !str_contains($attribute, ']')) {
                continue;
            }
            $double = strpos($attribute, '"');
            $single = strpos($attribute, "'");
            if (($single === false || $open < $single) && ($double === false || $open < $double)) {
                $parts[$i] = $run($attribute);
                continue;
            }
            $ran = $run($attribute, $count);
            $judged = $count > 0 ? (string) \wp_kses_one_attr($ran, $element[0]) : '';
            $parts[$i] = trim($judged) === '' ? $attribute : $judged;
        }
        return $start . implode('', $parts) . $end;
    }
}
