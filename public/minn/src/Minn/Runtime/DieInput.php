<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\I18n\Gettext;
use Minn\Support\Lists;

/**
 * wp_die's message, title and arguments made whole, as the die handlers
 * read them on the reference (probes functions-remaining,
 * functions-formatting-doubts): an error's first message is the message and
 * every other one, each with its code's data, an additional error; the
 * first error's status is the response unless the caller names one, and of
 * its data only wp_die's own arguments carry over; the caller's code wins
 * over the error's. A missing response is 500, a missing title the
 * translated "WordPress &rsaquo; Error", a missing code wp_die, and the
 * charset the site's unless the caller names one, in its usual spelling.
 */
final class DieInput
{
    private const DEFAULTS = ['response' => 0, 'code' => '', 'exit' => true, 'back_link' => false, 'link_url' => '', 'link_text' => '', 'text_direction' => 'ltr', 'charset' => 'utf-8', 'additional_errors' => []];

    /**
     * The message, title and arguments.
     *
     * @param list<array{code: int|string, message: string, data: mixed}>|null $errors the error's messages in order, or null for a plain message
     * @param Closure(string): string $charset a charset's usual spelling
     * @return array{0: mixed, 1: mixed, 2: array<string, mixed>}
     */
    public static function process(mixed $message, ?array $errors, mixed $title, mixed $args, string $siteCharset, Closure $charset): array
    {
        $original = $args;
        $defaults = self::DEFAULTS;
        if ($errors !== null) {
            $data = $errors[0]['data'] ?? [];
            if (is_array($data) && isset($data['status'])) {
                $defaults['response'] = $data['status'];
            }
            $args = Lists::args($args, array_intersect_key(is_array($data) ? $data : [], $defaults) + $defaults);
            $args['code'] = $args['code'] ?: ($errors[0]['code'] ?? '');
            $message = $errors[0]['message'] ?? '';
            $title = $title === '' && is_array($data) && isset($data['title']) ? $data['title'] : $title;
            $args['additional_errors'] = array_slice($errors, 1);
        } else {
            $args = Lists::args($args, $defaults);
        }
        $args['response'] = $args['response'] === 0 ? 500 : $args['response'];
        $args['code'] = empty($args['code']) ? 'wp_die' : $args['code'];
        $args['charset'] = $charset(array_key_exists('charset', (array) $original) ? (string) $args['charset'] : $siteCharset);
        return [$message, $title === '' ? Gettext::text('WordPress &rsaquo; Error') : $title, $args];
    }
}
