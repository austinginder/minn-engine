<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Errors;
use Minn\Objects\Post;
use Minn\Support\Slashes;
use Minn\Support\Text;

/**
 * An autosave as the reference's REST autosave makes it (probes
 * rest-autosaves, hook-inputs-2; tests/tools/side-effects.php): the
 * author's own draft is saved in place through wp_update_post; anyone
 * else's post gets the author's one autosave slot, made through
 * wp_insert_post and _wp_put_post_revision, or updated through
 * wp_update_post after wp_creating_autosave. A slot already there is left
 * alone when every field given matches the post. DOING_AUTOSAVE is defined
 * for the rest of the request, so plugins' save hooks can tell, and no
 * revision is saved while it is.
 */
final class Autosaves
{
    /**
     * Saves the given revision fields (post_title, post_content,
     * post_excerpt) as the user's autosave of the post: the post's own id
     * when the draft itself was saved, the slot's otherwise, or the save's
     * refusal.
     *
     * @param array<string, string> $fields
     */
    public static function save(Post $post, int $userId, array $fields): int|Errors
    {
        \defined('DOING_AUTOSAVE') || \define('DOING_AUTOSAVE', true);
        if (self::inPlace($post, $userId)) {
            $saved = PostWrites::update(Slashes::add(['ID' => $post->ID] + $fields), true);
            return $saved instanceof Errors ? $saved : (int) $saved;
        }
        return self::saveSlot($post, $userId, $fields);
    }

    /**
     * Saves the given revision fields in the user's autosave slot of the
     * post (create_post_autosave): the slot's id, or the save's refusal.
     *
     * @param array<string, string> $fields
     */
    public static function saveSlot(Post $post, int $userId, array $fields): int|Errors
    {
        \defined('DOING_AUTOSAVE') || \define('DOING_AUTOSAVE', true);
        $slot = self::slot($post->ID, $userId);
        if ($slot === null) {
            $data = PostRevisions::data(['ID' => $post->ID] + $fields, true) + ['post_author' => $userId];
            return PostRevisions::put($data) ?? 0;
        }
        if (self::matchesPost($post, $fields)) {
            return $slot;
        }
        $data = PostRevisions::data(['ID' => $post->ID] + $fields, true) + ['ID' => $slot, 'post_author' => $userId];
        Runtime::hooks()->action('wp_creating_autosave', [$data, true]);
        $saved = PostWrites::update(Slashes::add($data), true);
        return $saved instanceof Errors ? $saved : (int) $saved;
    }

    /** Whether an autosave saves the post itself: the author's own draft (or auto-draft). */
    public static function inPlace(Post $post, int $userId): bool
    {
        return in_array($post->post_status, ['draft', 'auto-draft'], true) && (int) $post->post_author === $userId;
    }

    /** The id of the user's autosave of a post, or null. */
    private static function slot(int $postId, int $userId): ?int
    {
        foreach (PostRevisions::all($postId, ['check_enabled' => false]) as $revision) {
            if ($revision->post_name === "{$postId}-autosave-v1" && (int) $revision->post_author === $userId) {
                return (int) $revision->ID;
            }
        }
        return null;
    }

    /** Whether every field given matches the post's own, whitespace evened out. @param array<string, string> $fields */
    private static function matchesPost(Post $post, array $fields): bool
    {
        foreach ($fields as $field => $value) {
            if (Text::normalizeWhitespace($value) !== Text::normalizeWhitespace((string) $post->{$field})) {
                return false;
            }
        }
        return true;
    }
}
