<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Support\Escape;

/**
 * A post's fields put in a context as WordPress's sanitize_post_field puts
 * them: raw as stored; for the database through the pre_ filters; for
 * editing through the edit_ filters and made safe for a form; for display
 * through the field's own filter, escaped for an attribute or a script when
 * asked. The integer columns stay integers in every context.
 */
final class PostFields
{
    /** One field in a context (sanitize_post_field). */
    public static function value(mixed $field, mixed $value, mixed $postId, mixed $context = 'display'): mixed
    {
        $field = (string) $field;
        if (in_array($field, ['ID', 'post_parent', 'menu_order'], true)) {
            return (int) self::contextual($field, (int) $value, $postId, (string) $context);
        }
        return self::contextual($field, $value, $postId, (string) $context);
    }

    /** Every field of a post object or array in a context (sanitize_post), unless it is in that context already. */
    public static function sanitize(mixed $post, mixed $context = 'display'): mixed
    {
        if (is_object($post)) {
            if (isset($post->filter) && $post->filter === $context) {
                return $post;
            }
            $id = (int) ($post->ID ?? 0);
            foreach (array_keys(get_object_vars($post)) as $field) {
                if ($field !== 'filter') {
                    $post->{$field} = self::value($field, $post->{$field}, $id, $context);
                }
            }
            $post->filter = $context;
            return $post;
        }
        if (!is_array($post)) {
            return $post;
        }
        if (($post['filter'] ?? null) === $context) {
            return $post;
        }
        $id = (int) ($post['ID'] ?? 0);
        foreach ($post as $field => $value) {
            $post[$field] = $field === 'filter' ? $value : self::value($field, $value, $id, $context);
        }
        $post['filter'] = $context;
        return $post;
    }

    /** Text made ready for an edit field (format_to_edit): through format_to_edit, encoded for a textarea unless rich. */
    public static function formatToEdit(mixed $content, mixed $richText = false): mixed
    {
        $content = Runtime::hooks()->filter('format_to_edit', [$content]);
        return $richText ? $content : Escape::textarea($content);
    }

    /** Whether the user edits in the rich editor (user_can_richedit): their preference, and a browser that can. */
    public static function richEdit(): bool
    {
        $pref = MetaData::userOption('rich_editing');
        $wants = $pref === false || $pref === 'true' || $pref === true;
        $agent = (string) (Runtime::current()->request?->header('user-agent') ?? '');
        return (bool) Runtime::hooks()->filter('user_can_richedit', [$wants && stripos($agent, 'mozilla') !== false]);
    }

    private static function contextual(string $field, mixed $value, mixed $postId, string $context): mixed
    {
        $bare = str_starts_with($field, 'post_') ? substr($field, 5) : null;
        if ($context === 'raw') {
            return $value;
        }
        $hooks = Runtime::hooks();
        if ($context === 'db') {
            return $bare === null
                ? $hooks->filter("{$field}_pre", [$hooks->filter("pre_post_{$field}", [$value])])
                : $hooks->filter("{$bare}_save_pre", [$hooks->filter("pre_{$field}", [$value])]);
        }
        if ($context === 'edit') {
            $value = $bare === null
                ? $hooks->filter("edit_post_{$field}", [$value, $postId])
                : $hooks->filter("{$bare}_edit_pre", [$hooks->filter("edit_{$field}", [$value, $postId]), $postId]);
            if (!is_scalar($value)) {
                return $value;
            }
            if ($field === 'post_content') {
                return self::formatToEdit((string) $value, self::richEdit());
            }
            return in_array($field, ['post_title', 'post_excerpt', 'post_password'], true) ? self::formatToEdit((string) $value) : Escape::attr((string) $value);
        }
        $value = $hooks->filter($bare === null ? "post_{$field}" : $field, [$value, $postId, $context]);
        if ($context === 'attribute') {
            return Escape::attr((string) $value);
        }
        return $context === 'js' ? Escape::js((string) $value) : $value;
    }
}
