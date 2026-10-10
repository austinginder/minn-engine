<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Html\Tags;

/**
 * A page's details as the editor's link previews read them (probe
 * rest-embed-details), from the document's head: everything up to its
 * closer, the whole page when it has none. The first title, as text with
 * its tags gone; the first link whose rel names "icon" (not an
 * apple-touch-icon); the first meta named description; the first meta
 * whose property is og:image or og:image:url. Twitter tags, og:title and
 * og:description count for nothing. Each is trimmed. An address with no
 * host hangs off the page's root whatever its own path, and one with no
 * scheme takes the page's.
 */
final class UrlDetails
{
    /** The page's four details, read from its markup; addresses made whole against the page's own. @return array{title: string, icon: string, description: string, image: string} */
    public static function of(string $html, string $url): array
    {
        $found = ['title' => null, 'icon' => null, 'description' => null, 'image' => null];
        $tags = new Tags($html);
        while ($tags->nextTag(['tag_closers' => 'visit'])) {
            if ($tags->isCloser()) {
                if ($tags->tag() === 'HEAD') {
                    break;
                }
                continue;
            }
            [$key, $value] = self::read($tags);
            if ($key !== null && $found[$key] === null) {
                $found[$key] = $value;
            }
        }
        return [
            'title' => self::text($found['title']),
            'icon' => self::absolute(trim((string) $found['icon']), $url),
            'description' => self::text($found['description']),
            'image' => self::absolute(trim((string) $found['image']), $url),
        ];
    }

    /** Which detail the tag holds, and its value. @return array{0: ?string, 1: string} */
    private static function read(Tags $tags): array
    {
        $attribute = static fn (string $name): string => is_string($value = $tags->attribute($name)) ? $value : '';
        return match ($tags->tag()) {
            'TITLE' => ['title', $tags->modifiableText()],
            'LINK' => in_array('icon', preg_split('/\s+/', strtolower($attribute('rel')), -1, PREG_SPLIT_NO_EMPTY) ?: [], true) ? ['icon', $attribute('href')] : [null, ''],
            'META' => match (true) {
                strtolower($attribute('name')) === 'description' => ['description', $attribute('content')],
                in_array(strtolower($attribute('property')), ['og:image', 'og:image:url'], true) => ['image', $attribute('content')],
                default => [null, ''],
            },
            default => [null, ''],
        };
    }

    /** Text as a preview shows it: tags gone, trimmed. */
    private static function text(?string $value): string
    {
        return trim(strip_tags((string) $value));
    }

    /** An address made whole against the page's root. */
    private static function absolute(string $value, string $url): string
    {
        if ($value === '' || preg_match('#^[a-z][a-z0-9+.\-]*:#i', $value)) {
            return $value;
        }
        $page = parse_url($url) ?: [];
        $scheme = (string) ($page['scheme'] ?? 'https');
        if (str_starts_with($value, '//')) {
            return $scheme . ':' . $value;
        }
        $port = isset($page['port']) ? ':' . $page['port'] : '';
        return $scheme . '://' . ($page['host'] ?? '') . $port . '/' . ltrim($value, '/');
    }
}
