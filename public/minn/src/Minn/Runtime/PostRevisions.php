<?php

declare(strict_types=1);

namespace Minn\Runtime;

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
    /** The new revision's id, or null when none was called for. */
    public static function save(\WP_Post $post): ?int
    {
        if (!\post_type_supports($post->post_type, 'revisions') || $post->post_status === 'auto-draft' || !\wp_revisions_enabled($post)) {
            return null;
        }
        $latest = null;
        foreach (\wp_get_post_revisions($post->ID) as $revision) {
            if (str_contains((string) $revision->post_name, "{$revision->post_parent}-revision")) {
                $latest = $revision;
                break;
            }
        }
        if ($latest !== null && \apply_filters('wp_save_post_revision_check_for_changes', true, $latest, $post) && !self::changed($latest, $post)) {
            return null;
        }
        $revisionId = self::put($post);
        self::prune($post);
        return $revisionId;
    }

    /** Whether the post differs from its latest revision in the revision fields, or a plugin says so. */
    private static function changed(\WP_Post $latest, \WP_Post $post): bool
    {
        $changed = false;
        foreach (array_keys(\_wp_post_revision_fields($post)) as $field) {
            if (\normalize_whitespace((string) $post->{$field}) !== \normalize_whitespace((string) $latest->{$field})) {
                $changed = true;
                break;
            }
        }
        return (bool) \apply_filters('wp_save_post_revision_post_has_changed', $changed, $latest, $post);
    }

    /** The revision written through wp_insert_post, then _wp_put_post_revision; its id. */
    public static function put(\WP_Post $post): ?int
    {
        $data = \_wp_post_revision_data($post);
        $revisionId = \wp_insert_post(\wp_slash($data), true);
        if (!is_int($revisionId) || $revisionId < 1) {
            return null;
        }
        \do_action('_wp_put_post_revision', $revisionId, $data['post_parent']);
        return $revisionId;
    }

    /** The oldest revisions past wp_revisions_to_keep removed (autosaves stay). */
    private static function prune(\WP_Post $post): void
    {
        $keep = \wp_revisions_to_keep($post);
        if ($keep < 0) {
            return;
        }
        $revisions = (array) \apply_filters('wp_save_post_revision_revisions_before_deletion', \wp_get_post_revisions($post->ID, ['order' => 'ASC']), $post->ID);
        foreach (array_slice(array_values($revisions), 0, max(0, count($revisions) - $keep)) as $revision) {
            if (!str_contains((string) $revision->post_name, 'autosave')) {
                \wp_delete_post_revision($revision->ID);
            }
        }
    }
}
