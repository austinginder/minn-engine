<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Objects\Post;
use Minn\Support\Lists;
use Minn\Support\Slashes;
use Minn\Support\Text;

/**
 * A post's revisions as the reference's wp_save_post_revision keeps them
 * (probe revisions): only for a type that supports revisions and while
 * wp_revisions_to_keep allows any; compared with the latest revision
 * (wp_save_post_revision_check_for_changes, the revision fields with their
 * whitespace evened out, wp_save_post_revision_post_has_changed, whose
 * default also compares the revisioned meta); saved through wp_insert_post
 * and _wp_put_post_revision (whose default copies the revisioned meta);
 * then the oldest dropped past the limit, after
 * wp_save_post_revision_revisions_before_deletion.
 */
final class PostRevisions
{
    /** The revision fields' defaults: the title, the content and the excerpt. */
    private const FIELDS = ['post_title' => 'Title', 'post_content' => 'Content', 'post_excerpt' => 'Excerpt'];

    /**
     * A post's revisions (wp_get_post_revisions), newest first unless asked
     * otherwise, keyed by id (or a list of ids); none while its revisions are
     * switched off, unless check_enabled is false.
     *
     * @return array<int, mixed>
     */
    public static function all(mixed $post = 0, mixed $args = null): array
    {
        $post = Records::post($post);
        $args = Lists::args($args, ['order' => 'DESC', 'orderby' => 'date ID', 'check_enabled' => true]);
        if ($post === null || ($args['check_enabled'] && !self::enabled($post))) {
            return [];
        }
        $rows = (new PostLookup(Runtime::current()->db))->revisionsOf($post->ID);
        $rows = strtoupper((string) $args['order']) === 'ASC' ? array_reverse($rows) : $rows;
        if (($args['fields'] ?? '') === 'ids') {
            return array_map(static fn (array $row): int => (int) $row['ID'], array_values($rows));
        }
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['ID']] = Handoff::newPost((object) $row);
        }
        return $out;
    }

    /**
     * How many revisions a post keeps (wp_revisions_to_keep): WP_POST_REVISIONS
     * (all, -1, when unset or true), none for a type without revision
     * support, then wp_revisions_to_keep and wp_{type}_revisions_to_keep.
     */
    public static function toKeep(mixed $post): int
    {
        $post = Records::post($post);
        $num = defined('WP_POST_REVISIONS') ? WP_POST_REVISIONS : true;
        $num = $num === true ? -1 : (int) $num;
        if ($post === null || !Registered::supports($post->post_type, 'revisions')) {
            $num = 0;
        }
        $num = Runtime::hooks()->filter('wp_revisions_to_keep', [$num, $post]);
        return (int) Runtime::hooks()->filter("wp_{$post?->post_type}_revisions_to_keep", [$num, $post]);
    }

    /** Whether a post keeps any revisions (wp_revisions_enabled). */
    public static function enabled(mixed $post): bool
    {
        return self::toKeep($post) !== 0;
    }

    /** A revision deleted (wp_delete_post_revision); anything but a revision is left alone, and nothing comes back. */
    public static function delete(mixed $revision): mixed
    {
        $revision = Records::post($revision);
        if ($revision === null || $revision->post_type !== 'revision') {
            return null;
        }
        $removed = PostWrites::delete($revision->ID);
        if ($removed) {
            Runtime::hooks()->action('wp_delete_post_revision', [$revision->ID, $revision]);
        }
        return $removed;
    }

    /**
     * The fields a revision keeps (_wp_post_revision_fields), through
     * _wp_post_revision_fields; the filtered list is what the next call
     * starts from, as on the reference.
     *
     * @return array<string, mixed>
     */
    public static function fields(mixed $post = [], mixed $deprecated = false): array
    {
        $post = is_array($post) ? $post : (Records::post($post)?->to_array() ?? []);
        $runtime = Runtime::current();
        $fields = (array) $runtime->get('revision_fields', self::FIELDS);
        $fields = (array) Runtime::hooks()->filter('_wp_post_revision_fields', [$fields, $post]);
        $runtime->set('revision_fields', $fields);
        return array_diff_key($fields, array_flip(['ID', 'post_name', 'post_parent', 'post_date', 'post_date_gmt', 'post_status', 'post_type', 'comment_count', 'post_author']));
    }

    /**
     * A revision's row (_wp_post_revision_data): the post's revision fields,
     * its parent, inherit, revision, its name, and the post's modified time
     * as its date.
     *
     * @return array<string, mixed>
     */
    public static function data(mixed $post = [], mixed $autosave = false): array
    {
        $post = self::source($post);
        $data = array_intersect_key($post, self::fields($post));
        $id = (int) ($post['ID'] ?? 0);
        ['post_modified' => $modified, 'post_modified_gmt' => $modifiedGmt] = $post + ['post_modified' => null, 'post_modified_gmt' => null];
        return $data + ['post_parent' => $id, 'post_status' => 'inherit', 'post_type' => 'revision', 'post_name' => $autosave ? "{$id}-autosave-v1" : "{$id}-revision-v1", 'post_date' => $modified ?? '', 'post_date_gmt' => $modifiedGmt ?? ''];
    }

    /**
     * A post as a revision's row is made from: its own fields, as an (array)
     * cast gives them.
     *
     * @return array<string, mixed>
     */
    public static function source(mixed $post): array
    {
        if (is_array($post)) {
            return $post;
        }
        $post = is_object($post) ? $post : Records::post($post);
        return is_object($post) ? get_object_vars($post) : [];
    }

    /** The new revision's id, or null when none was called for. */
    public static function save(Post $post): ?int
    {
        if (!Registered::supports($post->post_type, 'revisions') || $post->post_status === 'auto-draft' || !self::enabled($post)) {
            return null;
        }
        $latest = null;
        foreach (self::all($post->ID) as $revision) {
            if (str_contains((string) $revision->post_name, "{$revision->post_parent}-revision")) {
                $latest = $revision;
                break;
            }
        }
        if ($latest !== null && Runtime::hooks()->filter('wp_save_post_revision_check_for_changes', [true, $latest, $post]) && !self::changed($latest, $post)) {
            return null;
        }
        $revisionId = self::put(self::data($post));
        self::prune($post);
        return $revisionId;
    }

    /** Whether the post differs from its latest revision in the revision fields, or a plugin says so. */
    private static function changed(Post $latest, Post $post): bool
    {
        $changed = false;
        foreach (array_keys(self::fields($post)) as $field) {
            if (Text::normalizeWhitespace((string) $post->{$field}) !== Text::normalizeWhitespace((string) $latest->{$field})) {
                $changed = true;
                break;
            }
        }
        return (bool) Runtime::hooks()->filter('wp_save_post_revision_post_has_changed', [$changed, $latest, $post]);
    }

    /**
     * The revision (or autosave) row _wp_post_revision_data made, written
     * through wp_insert_post, then _wp_put_post_revision; its id.
     *
     * @param array<string, mixed> $data
     */
    public static function put(array $data): ?int
    {
        $revisionId = PostWrites::insert(Slashes::add($data), true);
        if (!is_int($revisionId) || $revisionId < 1) {
            return null;
        }
        Runtime::hooks()->action('_wp_put_post_revision', [$revisionId, $data['post_parent']]);
        return $revisionId;
    }

    /** The oldest revisions past wp_revisions_to_keep removed (autosaves stay). */
    private static function prune(Post $post): void
    {
        $keep = self::toKeep($post);
        if ($keep < 0) {
            return;
        }
        $revisions = (array) Runtime::hooks()->filter('wp_save_post_revision_revisions_before_deletion', [self::all($post->ID, ['order' => 'ASC']), $post->ID]);
        foreach (array_slice(array_values($revisions), 0, max(0, count($revisions) - $keep)) as $revision) {
            if (!str_contains((string) $revision->post_name, 'autosave')) {
                self::delete($revision->ID);
            }
        }
    }
}
