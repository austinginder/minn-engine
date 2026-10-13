<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\PostRecord;
use Minn\Content\PostSlugs;
use Minn\Http\Request;
use Minn\Rest\RuntimeRoutes;
use Minn\RestError;
use Minn\Support\Sanitize;
use Minn\Support\Slashes;

/**
 * A REST save's columns through the filters the reference's save runs
 * before it writes, in its order (probe post-insert-filters,
 * contracts/runtime.md "Saves plugins can change"): rest_pre_insert_{type}
 * over the prepared post; every column through its db context
 * (pre_post_* and *_save_pre, where kses and the custom-CSS strip sit) on
 * slashed text; wp_insert_post_empty_content, which refuses a post with no
 * content, title or excerpt; wp_insert_post_parent; for a post that is live,
 * the slug, made again from the filtered title when the request gave none,
 * through pre_wp_unique_post_slug, the bad-slug check and
 * wp_unique_post_slug; and wp_insert_post_data over the row. What a filter
 * changes is written. Without plugins loaded the columns are as given.
 */
final class PostSave
{
    /** The row wp_insert_post_data is handed, in its keys. */
    private const DATA = ['post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_parent', 'menu_order', 'post_mime_type', 'guid'];

    /** A REST field and the column it fills in the prepared post. */
    private const FIELDS = ['title' => 'post_title', 'content' => 'post_content', 'excerpt' => 'post_excerpt', 'status' => 'post_status', 'date' => 'post_date', 'date_gmt' => 'post_date_gmt', 'slug' => 'post_name', 'password' => 'post_password', 'author' => 'post_author', 'parent' => 'post_parent', 'menu_order' => 'menu_order', 'comment_status' => 'comment_status', 'ping_status' => 'ping_status'];

    /** The prepared post's columns in the reference's order, each with the REST field that sends it (null: always there). */
    private const PREPARED = ['post_title' => 'title', 'post_content' => 'content', 'post_excerpt' => 'excerpt', 'post_type' => null, 'post_status' => 'status', 'post_date' => 'date', 'post_date_gmt' => 'date', 'edit_date' => 'date', 'post_name' => 'slug', 'post_author' => 'author', 'post_password' => 'password', 'post_parent' => 'parent', 'menu_order' => 'menu_order', 'comment_status' => 'comment_status', 'ping_status' => 'ping_status'];

    private const UNSLUGGED = ['draft', 'pending', 'auto-draft'];

    private const PARENT = 'post_parent';

    /** The new post array wp_insert_post_parent is handed beside the whole one. */
    private const NEW_POSTARR = ['ID', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_content_filtered', 'post_title', 'post_excerpt', 'post_status', 'post_type', 'comment_status', 'ping_status', 'post_password', 'to_ping', 'pinged', 'post_parent', 'menu_order', 'guid', 'import_id'];

    /** wp_insert_post's defaults, in its order (the order its filters run in). */
    private const DEFAULTS = ['post_author' => 0, 'post_content' => '', 'post_content_filtered' => '', 'post_title' => '', 'post_excerpt' => '', 'post_status' => 'draft', 'post_type' => 'post', 'comment_status' => '', 'ping_status' => '', 'post_password' => '', 'to_ping' => '', 'pinged' => '', 'post_parent' => 0, 'menu_order' => 0, 'guid' => '', 'import_id' => 0, 'context' => '', 'post_date' => '', 'post_date_gmt' => ''];

    /**
     * The columns to write after the filters.
     *
     * @param array<string, mixed> $columns what the engine settled: a new post's whole row, or an update's changes
     * @param array<string, mixed> $body the request's fields
     * @param PostSlugs $slugs the rules a live post's slug is settled by
     * @return array<string, mixed>
     */
    public static function filter(array $columns, ?PostRecord $before, array $body, Request $request, string $type, PostSlugs $slugs): array
    {
        if (!Runtime::booted()) {
            return $columns;
        }
        $id = $before?->id ?? 0;
        $columns = self::prepared($columns, $before, $body, $request, $type);
        // What the request gave, as wp_insert_post_data's third argument has it: a new post's own fields and type.
        $given = array_intersect_key($columns, array_flip(array_values(array_intersect_key(self::FIELDS, $body)))) + ['post_type' => $type];
        $postarr = $before === null ? $columns : $columns + self::stored($id);
        $sanitized = self::sanitized(Slashes::add($postarr));
        if (self::refusesEmpty($sanitized, $type)) {
            throw new RestError('empty_content', 'Content, title, and excerpt are empty.', 400);
        }
        $value = static fn (string $column): string => (string) Slashes::strip($sanitized[$column] ?? '');
        $row = array_intersect_key((array) Slashes::strip($sanitized), array_flip(self::DATA));
        if ($before !== null) {
            // An update keeps the guid it had, in its display form (probe insert-defaults).
            $row = array_replace($row, ['guid' => (string) \get_post_field('guid', $id)]);
        }
        // A private post keeps no password (probe placeholders-admin).
        $row = $value('post_status') === 'private' ? array_replace($row, ['post_password' => '']) : $row;
        $parent = self::parent($sanitized, $id);
        $slug = $value('post_name');
        if (!self::keepsSlug($value('post_status'), $type)) {
            // A slug the request did not give is made from the title the filters left.
            $slug = $slug === '' || (!isset($body['slug']) && $id === 0) ? (string) Sanitize::title($value('post_title')) : $slug;
            $slug = self::slugFilters($slug, $id, $value('post_status'), $type, $parent, $slugs);
        }
        $data = self::data(['post_name' => $slug, 'post_parent' => $parent] + $row, $sanitized, Slashes::add($before === null ? $given : $postarr), $id);
        return self::changed($columns, $data, $before);
    }

    /**
     * A direct save's changed columns through every column's db context,
     * as an update runs them over the whole post (pre_post_* and
     * *_save_pre, where kses and the global styles strip sit): a global
     * styles save writes its post this way (probe global-styles-unfiltered).
     * Without a booted runtime the changes are as given.
     *
     * @param array<string, string> $changes column => value
     * @return array<string, string>
     */
    public static function throughSaveFilters(int $id, array $changes): array
    {
        if (!Runtime::booted()) {
            return $changes;
        }
        $sanitized = self::sanitized(Slashes::add($changes + self::stored($id)));
        $out = [];
        foreach (array_keys($changes) as $column) {
            $out[$column] = (string) Slashes::strip($sanitized[$column] ?? '');
        }
        return $out;
    }

    /** The post as stored, its columns by name. @return array<string, mixed> */
    private static function stored(int $id): array
    {
        return \get_post($id)->to_array();
    }

    /**
     * wp_insert_post's array with its defaults filled in (ID 0 for a new
     * post), through sanitize_post's db context: slashed in, slashed out.
     *
     * @param array<string, mixed> $postarr
     * @return array<string, mixed>
     */
    public static function sanitized(array $postarr): array
    {
        $postarr = array_merge(self::DEFAULTS, ['post_author' => \get_current_user_id()], $postarr);
        unset($postarr['filter']);
        return (array) \sanitize_post(['ID' => 0] + $postarr, 'db');
    }

    /** wp_insert_post_empty_content over whether a type with an editor, a title and an excerpt has none of them. @param array<string, mixed> $sanitized */
    public static function refusesEmpty(array $sanitized, string $type): bool
    {
        $none = static fn (string $column): bool => (string) Slashes::strip($sanitized[$column] ?? '') === '';
        $empty = $type !== 'attachment' && $none('post_content') && $none('post_title') && $none('post_excerpt')
            && Registered::supports($type, 'editor') && Registered::supports($type, 'title') && Registered::supports($type, 'excerpt');
        return (bool) Runtime::hooks()->filter('wp_insert_post_empty_content', [$empty, $sanitized]);
    }

    /** The parent through wp_insert_post_parent (whose default refuses a loop). @param array<string, mixed> $sanitized */
    public static function parent(array $sanitized, int $id): int
    {
        $parent = (int) ($sanitized[self::PARENT] ?? 0);
        return (int) Runtime::hooks()->filter('wp_insert_post_parent', [$parent, $id, array_intersect_key($sanitized, array_flip(self::NEW_POSTARR)), $sanitized]);
    }

    /**
     * A live post's slug through the reference's three filters:
     * pre_wp_unique_post_slug may settle it; otherwise it is made free in
     * its type's scope, the bad-slug filter judging the slug asked for when
     * nothing else has taken it; and wp_unique_post_slug has the last word.
     */
    public static function slugFilters(string $slug, int $id, string $status, string $type, int $parent, PostSlugs $slugs): string
    {
        $override = Runtime::hooks()->filter('pre_wp_unique_post_slug', [null, $slug, $id, $status, $type, $parent]);
        if ($override !== null) {
            return (string) $override;
        }
        $bad = static fn (string $candidate): bool => (bool) match (true) {
            $type === 'attachment' => Runtime::hooks()->filter('wp_unique_post_slug_is_bad_attachment_slug', [false, $candidate]),
            Registered::hierarchicalType($type) => Runtime::hooks()->filter('wp_unique_post_slug_is_bad_hierarchical_slug', [false, $candidate, $type, $parent]),
            default => Runtime::hooks()->filter('wp_unique_post_slug_is_bad_flat_slug', [false, $candidate, $type]),
        };
        return (string) Runtime::hooks()->filter('wp_unique_post_slug', [$slugs->unique($slug, $id, $type, $parent, $bad), $id, $status, $type, $parent, $slug]);
    }

    /**
     * The row's guid and slug as wp_insert_post settles them (probe
     * insert-defaults): an update keeps the guid it had, in its display
     * form, ignoring a new one asked for; a live post's slug goes through
     * the slug filters.
     *
     * @param array<string, string> $columns
     * @return array<string, string>
     */
    public static function guidAndSlug(array $columns, int $postId, string $type, PostSlugs $slugs): array
    {
        ['post_status' => $status, 'post_name' => $name, 'post_parent' => $parent] = $columns;
        $settled = $postId > 0 ? ['guid' => (string) \get_post_field('guid', $postId)] : [];
        if (!self::keepsSlug((string) $status, $type)) {
            $settled += ['post_name' => self::slugFilters((string) $name, $postId, (string) $status, $type, (int) $parent, $slugs)];
        }
        return array_replace($columns, $settled);
    }

    /** Whether a save leaves the slug as it is: a draft, a pending post, an auto-draft, a revision, a personal data request. */
    public static function keepsSlug(string $status, string $type): bool
    {
        return in_array($status, self::UNSLUGGED, true) || ($status === 'inherit' && $type === 'revision') || $type === 'user_request';
    }

    /**
     * The row through wp_insert_post_data (wp_insert_attachment_data for an
     * attachment), handed slashed as the reference hands it and unslashed
     * back.
     *
     * @param array<string, mixed> $row the row, unslashed
     * @param array<string, mixed> $sanitized
     * @param array<string, mixed> $unsanitized
     * @param int $postId the post an update saves, 0 for a new one
     * @return array<string, mixed>
     */
    public static function data(array $row, array $sanitized, array $unsanitized, int $postId): array
    {
        $data = array_replace(array_fill_keys(self::DATA, ''), array_intersect_key($row, array_flip(self::DATA)));
        $filter = ($row['post_type'] ?? '') === 'attachment' ? 'wp_insert_attachment_data' : 'wp_insert_post_data';
        return (array) Slashes::strip(Runtime::hooks()->filter((string) $filter, [Slashes::add($data), $sanitized, $unsanitized, $postId > 0]));
    }

    /**
     * rest_pre_insert_{type} over the prepared post, built as the
     * reference's controller builds it: its id when it edits, the fields
     * the request named in the reference's order, the type always, both
     * dates and edit_date when either date was sent, page_template null.
     * rest_block_hooks_post_types sees it first. What
     * the filter changes or adds is kept; an error refuses the save.
     *
     * @param array<string, mixed> $columns
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private static function prepared(array $columns, ?PostRecord $before, array $body, Request $request, string $type): array
    {
        $prepared = new \stdClass();
        if ($before !== null) {
            $prepared->ID = $before->id;
        }
        // Each column the request sent, in the reference's order: the type always, both dates and edit_date for either date.
        $dated = isset($body['date']) || isset($body['date_gmt']);
        $values = $columns + ['post_type' => $type, 'edit_date' => true];
        foreach (self::PREPARED as $column => $field) {
            $sent = match ($field) {
                null => true,
                'date' => $dated,
                default => array_key_exists($field, $body),
            };
            if ($sent && array_key_exists($column, $values)) {
                $prepared->{$column} = $values[$column];
            }
        }
        // The template is set after the save; the prepared post names it null, sent or not.
        $prepared->page_template = null;
        \Minn\Rest\RuntimePrepare::savedWithHookedBlocks($prepared, $type);
        $filtered = Runtime::hooks()->filter("rest_pre_insert_{$type}", [$prepared, RuntimeRoutes::wpRequest($request)]);
        if ($filtered instanceof \WP_Error) {
            $status = $filtered->get_error_data();
            throw new RestError($filtered->get_error_code(), $filtered->get_error_message(), is_array($status) ? (int) ($status['status'] ?? 400) : 400);
        }
        foreach (is_object($filtered) ? get_object_vars($filtered) : [] as $column => $value) {
            if ($column !== 'ID' && in_array($column, self::DATA, true)) {
                $columns[$column] = $value;
            }
        }
        return $columns;
    }

    /**
     * The columns to write: a new post's whole row as the filters left it;
     * an update's own changes plus whatever a filter changed from the row.
     *
     * @param array<string, mixed> $columns
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private static function changed(array $columns, array $data, ?PostRecord $before): array
    {
        if ($before === null) {
            return array_replace($columns, array_intersect_key($data, $columns + array_fill_keys(self::DATA, '')));
        }
        $row = $before->row();
        foreach ($data as $column => $value) {
            if (array_key_exists($column, $columns) || (string) ($row[$column] ?? '') !== (string) $value) {
                $columns[$column] = $value;
            }
        }
        return $columns;
    }
}
