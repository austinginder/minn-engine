<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Json;
use Minn\Support\Kses;
use Minn\Support\KsesAttributes;
use Minn\Support\KsesPolicy;
use Minn\Support\KsesProtocols;

/**
 * The HTML WordPress's kses functions let through: the elements and
 * attributes allowed in a context (data/kses.json, as wp_kses_allowed_html
 * leaves them), content filtered to them, one attribute judged, and the
 * null bytes taken out.
 */
final class KsesRules
{
    /** @var array<string, mixed>|null the allowed-HTML tables, read once */
    private static ?array $table = null;

    /**
     * The tables the contexts start from: post, data (the comment and
     * description rules) and entities.
     *
     * @return array<string, mixed>
     */
    public static function table(): array
    {
        return self::$table ??= (array) Json::file(Runtime::current()->engineDir . '/data/kses.json');
    }

    /**
     * The elements and attributes allowed in a context
     * (wp_kses_allowed_html): post, a user's description, strip, entities,
     * the comment rules for any other, or an explicit list; as
     * wp_kses_allowed_html leaves them.
     */
    public static function allowedHtml(mixed $context = ''): mixed
    {
        $hooks = Runtime::hooks();
        if (is_array($context)) {
            return $hooks->filter('wp_kses_allowed_html', [$context, 'explicit']);
        }
        $table = self::table();
        return match ($context) {
            'post' => $hooks->filter('wp_kses_allowed_html', [$table['post'], 'post']),
            'user_description', 'pre_user_description' => $hooks->filter('wp_kses_allowed_html', [$table['data'] + ['a' => $table['data']['a'] + ['rel' => true]], $context]),
            'strip' => $hooks->filter('wp_kses_allowed_html', [[], 'strip']),
            'entities' => $hooks->filter('wp_kses_allowed_html', [$table['entities'], 'entities']),
            default => $hooks->filter('wp_kses_allowed_html', [$table['data'], $context]),
        };
    }

    /**
     * Content filtered to the HTML allowed (wp_kses), with the site's
     * protocols unless others are given. A context given as another scalar
     * is read as text, as wp_kses_data's current_filter() is false outside
     * a filter.
     */
    public static function filter(mixed $content, mixed $allowedHtml, mixed $allowedProtocols = []): string
    {
        $allowedHtml = is_scalar($allowedHtml) && !is_string($allowedHtml) ? (string) $allowedHtml : $allowedHtml;
        return Kses::allowing((string) $content, $allowedHtml, $allowedProtocols === [] ? KsesProtocols::allowed() : $allowedProtocols, static fn (string $context): array => (array) self::allowedHtml($context));
    }

    /** Content filtered to what a post may hold (wp_kses_post). */
    public static function post(mixed $data): string
    {
        return self::filter((string) $data, 'post');
    }

    /** One attribute judged as a post's element would carry it (wp_kses_one_attr). */
    public static function oneAttribute(mixed $attr, mixed $element): string
    {
        return KsesAttributes::oneAttribute((string) $attr, (string) $element, self::policy(self::allowedHtml('post'), KsesProtocols::allowed()));
    }

    /** Content without its null bytes (wp_kses_no_null), or without any control characters when slash_zero is kept. */
    public static function withoutNull(mixed $content, mixed $options = null): string
    {
        return ($options['slash_zero'] ?? 'remove') === 'keep' ? Kses::withoutControls((string) $content) : Kses::withoutNulls((string) $content);
    }

    /**
     * A caller's allowed HTML in any of its shapes, with the protocols and
     * URI attributes in force. For content, the URI attributes are asked
     * only when an opening tag of an allowed element that may carry
     * attributes is there, as the reference asks them of each such tag;
     * otherwise none are consulted.
     *
     * @param array<string, mixed>|string $allowedHtml
     * @param array<int, string> $allowedProtocols
     */
    public static function policy(array|string $allowedHtml, array $allowedProtocols, ?string $content = null): KsesPolicy
    {
        if (is_string($allowedHtml)) {
            $allowedHtml = self::allowedHtml($allowedHtml);
        }
        $asked = $content === null;
        if (!$asked && preg_match_all('/<([a-zA-Z][a-zA-Z0-9:-]*)/', $content, $tags) > 0) {
            foreach (array_unique(array_map('strtolower', $tags[1])) as $tag) {
                if (!empty($allowedHtml[$tag])) {
                    $asked = true;
                    break;
                }
            }
        }
        return KsesPolicy::fromAllowlist((array) $allowedHtml, array_values($allowedProtocols), $asked ? (array) Kses::uriAttributes() : []);
    }
}
