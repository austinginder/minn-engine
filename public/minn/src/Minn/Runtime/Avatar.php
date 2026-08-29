<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * Avatars the way get_avatar_data and get_avatar decide them: the argument
 * defaults, the email an id, user, post, or comment names, the Gravatar URL,
 * and the <img> attributes. Behaviour pinned by contracts/fixtures/api/functions.json.
 */
final class Avatar
{
    /** The data arguments normalised: sizes to integers, the default token, the rating lowercased. */
    public static function dataArgs(array $args): array
    {
        $args['size'] = (int) $args['size'] ?: 96;
        $args['height'] = (int) ($args['height'] ?? 0) ?: $args['size'];
        $args['width'] = (int) ($args['width'] ?? 0) ?: $args['size'];
        $args['default'] = match ((string) $args['default']) {
            'mystery', 'mysteryman', 'mm' => 'mm',
            'gravatar_default' => false,
            default => (string) $args['default'],
        };
        $args['force_default'] = (bool) $args['force_default'];
        $args['rating'] = strtolower((string) $args['rating']);
        $args['found_avatar'] = false;
        return $args;
    }

    /** The Gravatar hash an email yields; '' for no email. */
    public static function hash(string $email): string
    {
        return $email === '' ? '' : hash('sha256', strtolower(trim($email)));
    }

    /** A hash given as an md5.gravatar.com address, else null. */
    public static function hashFromAddress(string $address): ?string
    {
        return str_contains($address, '@md5.gravatar.com') ? strtolower(str_replace('@md5.gravatar.com', '', $address)) : null;
    }

    /** The Gravatar URL's query arguments for the data arguments. @return array<string, string|int|false> */
    public static function urlArgs(array $args): array
    {
        return array_filter(['s' => $args['size'], 'd' => $args['default'], 'f' => $args['force_default'] ? 'y' : false, 'r' => $args['rating']]);
    }

    /** The <img> classes: the size class, a default marker, the caller's own. @return list<string> */
    public static function classes(int $size, bool $isDefault, mixed $extra): array
    {
        $class = ['avatar', 'avatar-' . $size, 'photo'];
        if ($isDefault) {
            $class[] = 'avatar-default';
        }
        if ($extra) {
            $class = array_merge($class, is_array($extra) ? $extra : [$extra]);
        }
        return $class;
    }

    /** The extra attribute string with loading and decoding added when the caller did not set them. */
    public static function extraAttributes(string $extra, mixed $loading, mixed $decoding): string
    {
        if (in_array($loading, ['lazy', 'eager'], true) && !preg_match('/\bloading\s*=/', $extra)) {
            $extra .= ($extra !== '' ? ' ' : '') . "loading='{$loading}'";
        }
        if (in_array($decoding, ['async', 'sync', 'auto'], true) && !preg_match('/\bdecoding\s*=/', $extra)) {
            $extra .= ($extra !== '' ? ' ' : '') . "decoding='{$decoding}'";
        }
        return $extra;
    }
}
