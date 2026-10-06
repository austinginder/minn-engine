<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Content\PostWriter;

/**
 * The decisions behind wp_insert_post: which columns a postarr fills, when
 * the post counts as empty, the status a publish request lands in, the dates
 * and the slug, the categories a new post gets. The rows are written by
 * Content\PostWriter; the facade fires the hooks around each step.
 */
final readonly class PostInsert
{
    private const COLUMNS = ['post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_title', 'post_excerpt', 'post_status', 'comment_status', 'ping_status', 'post_password', 'post_name', 'to_ping', 'pinged', 'post_modified', 'post_modified_gmt', 'post_content_filtered', 'post_parent', 'guid', 'menu_order', 'post_type', 'post_mime_type'];

    /**
     * @param Closure(string, string): string $discussion a new post's comment or ping status by type, as get_default_comment_status has it
     * @param Closure(string, string): bool $supports whether a post type supports a feature
     * @param Closure(string): bool $canPublish whether the current user may publish the type
     * @param Closure(string): string $gmtFromDate the site-local date as GMT
     * @param Closure(bool): string $now the current local (or GMT) MySQL time
     */
    public function __construct(private PostWriter $writer, private int $userId, private Closure $discussion, private Closure $supports, private Closure $canPublish, private Closure $gmtFromDate, private Closure $now)
    {
    }

    /** The columns the posts table takes, filled from a postarr, with a new post's defaults. @param array<string, mixed>|null $existing @return array<string, string> */
    public function columns(array $postarr, ?array $existing): array
    {
        $columns = [];
        foreach (self::COLUMNS as $column) {
            if (array_key_exists($column, $postarr)) {
                $columns[$column] = is_array($postarr[$column]) ? implode("\n", $postarr[$column]) : (string) $postarr[$column];
            }
        }
        if ($existing === null) {
            $columns += [
                'post_author' => (string) $this->userId,
                'post_content' => '',
                'post_title' => '',
                'post_excerpt' => '',
                'post_status' => 'draft',
                'post_password' => '',
                'post_name' => '',
                'to_ping' => '',
                'pinged' => '',
                'post_content_filtered' => '',
                'post_parent' => '0',
                'guid' => '',
                'menu_order' => '0',
                'post_type' => 'post',
                'post_mime_type' => '',
            ];
        }
        // A missing or empty comment or ping status means the default (probe insert-defaults): the type's for a
        // new post (a page, or a type without comments or trackbacks, starts closed), closed for an update.
        foreach (['comment_status' => 'comment', 'ping_status' => 'pingback'] as $column => $kind) {
            if (($columns[$column] ?? '') === '' && ($existing === null || isset($columns[$column]))) {
                $columns[$column] = $existing === null ? ($this->discussion)($this->type($columns, null), $kind) : 'closed';
            }
        }
        return $columns;
    }

    /** A post with nothing in title, content, and excerpt is empty when its type supports all three. @param array<string, mixed>|null $existing */
    public function isEmpty(array $columns, ?array $existing): bool
    {
        $type = $this->type($columns, $existing);
        $title = (string) ($columns['post_title'] ?? $existing['post_title'] ?? '');
        $content = (string) ($columns['post_content'] ?? $existing['post_content'] ?? '');
        $excerpt = (string) ($columns['post_excerpt'] ?? $existing['post_excerpt'] ?? '');
        return $title === '' && $content === '' && $excerpt === '' && ($this->supports)($type, 'editor') && ($this->supports)($type, 'title') && ($this->supports)($type, 'excerpt');
    }

    /**
     * The columns as they will be written: the status a request lands in
     * (attachments inherit, unprivileged publishes pend, a future date
     * schedules), the dates, the slug (sanitized; not yet free), and the
     * integer columns.
     *
     * @param array<string, mixed>|null $existing
     * @return array<string, string>
     */
    public function resolve(array $columns, ?array $existing): array
    {
        $update = $existing !== null;
        $type = $this->type($columns, $existing);
        $status = (string) ($columns['post_status'] ?? $existing['post_status'] ?? 'draft');
        if ($type === 'attachment' && !in_array($status, ['inherit', 'private', 'trash', 'auto-draft'], true)) {
            $status = 'inherit';
        }
        $now = ($this->now)(false);
        $date = (string) ($columns['post_date'] ?? '');
        if ($date === '' || str_starts_with($date, '0000-00-00')) {
            $date = $update && !str_starts_with((string) $existing['post_date'], '0000') ? (string) $existing['post_date'] : $now;
        }
        $dateGmt = (string) ($columns['post_date_gmt'] ?? '');
        if ($dateGmt === '' || str_starts_with($dateGmt, '0000-00-00')) {
            if (in_array($status, ['draft', 'pending', 'auto-draft'], true)) {
                $dateGmt = '0000-00-00 00:00:00';
            } elseif ($update && !str_starts_with((string) $existing['post_date_gmt'], '0000')) {
                // The reference keeps a stored GMT date across an update that names only post_date.
                $dateGmt = (string) $existing['post_date_gmt'];
            } else {
                $dateGmt = ($this->gmtFromDate)($date);
            }
        }
        if ($status === 'publish' && strtotime($dateGmt . ' UTC') > time() + 60) {
            $status = 'future';
        }
        $columns['post_status'] = $status;
        // A private post keeps no password (probe placeholders-admin).
        $columns = $status === 'private' ? array_replace($columns, ['post_password' => '']) : $columns;
        $columns['post_date'] = $date;
        $columns['post_date_gmt'] = $dateGmt;
        $columns['post_modified'] = $now;
        $columns['post_modified_gmt'] = ($this->now)(true);
        $title = (string) ($columns['post_title'] ?? $existing['post_title'] ?? '');
        $slug = (string) ($columns['post_name'] ?? $existing['post_name'] ?? '');
        if ($slug === '' && !in_array($status, ['draft', 'pending', 'auto-draft'], true)) {
            $slug = (string) \sanitize_title($title);
        } elseif ($slug !== '' && (!$update || $slug !== (string) $existing['post_name'])) {
            $slug = (string) \sanitize_title($slug);
        }
        // A pending post from someone who may not publish it keeps no slug (probe insert-defaults); a live
        // post's is made free in its scope by the slug filters that follow.
        if ($status === 'pending' && !($this->canPublish)($type)) {
            $slug = '';
        }
        $columns['post_name'] = $slug;
        $columns['post_parent'] = (string) (int) ($columns['post_parent'] ?? $existing['post_parent'] ?? 0);
        $columns['menu_order'] = (string) (int) ($columns['menu_order'] ?? $existing['menu_order'] ?? 0);
        $columns['post_author'] = (string) (int) ($columns['post_author'] ?? $existing['post_author'] ?? $this->userId);
        return $columns;
    }

    /** Writes the resolved columns; a new post without a guid gets the ?p= form. @return int the post id */
    public function persist(array $columns, ?int $existingId, Closure $guid): int
    {
        if ($existingId !== null) {
            $this->writer->update($existingId, $columns);
            return $existingId;
        }
        $id = $this->writer->insert($columns);
        if (($columns['guid'] ?? '') === '') {
            $this->writer->update($id, ['guid' => $guid($id)]);
        }
        return $id;
    }

    /**
     * The categories a saved post should carry: the ones given, or the
     * default for a new post of a type that has categories; null when
     * nothing should change.
     *
     * @param list<string> $taxonomies the type's taxonomies
     * @return list<int>|null
     */
    public static function categories(array $postarr, string $type, string $status, bool $update, array $taxonomies, int $default): ?array
    {
        if (empty($postarr['post_category']) && ($update || $type !== 'post' || $status === 'auto-draft')) {
            return null;
        }
        if (!in_array('category', $taxonomies, true)) {
            return null;
        }
        $categories = array_values(array_filter(array_map('intval', (array) ($postarr['post_category'] ?? []))));
        if ($categories === [] && $type === 'post') {
            $categories = [$default];
        }
        return $categories === [] ? null : $categories;
    }

    private function type(array $columns, ?array $existing): string
    {
        return (string) ($columns['post_type'] ?? $existing['post_type'] ?? 'post');
    }
}
