<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Content\PostWriter;
use Minn\Db;
use Minn\Objects\Errors;
use Minn\Support\Sanitize;

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
        // A missing or empty comment or ping status means the default (probe insert-defaults): the type's (a page,
        // or a type without comments or trackbacks, starts closed), except that an update's comments close.
        foreach (['comment_status' => 'comment', 'ping_status' => 'pingback'] as $column => $kind) {
            if (($columns[$column] ?? '') === '') {
                $columns[$column] = $existing !== null && $column === 'comment_status' ? 'closed' : ($this->discussion)($this->type($columns, $existing), $kind);
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
        // Published and scheduled are one choice: the GMT date over a minute ahead schedules, any other publishes.
        if (in_array($status, ['publish', 'future'], true)) {
            $status = strtotime($dateGmt . ' UTC') > time() + 60 ? 'future' : 'publish';
        }
        $columns['post_status'] = $status;
        // A private post keeps no password (probe placeholders-admin).
        $columns = $status === 'private' ? array_replace($columns, ['post_password' => '']) : $columns;
        $columns['post_date'] = $date;
        $columns['post_date_gmt'] = $dateGmt;
        // A new post was last modified when it is dated (a draft's floating GMT date and all); an update, now.
        $columns['post_modified'] = $update ? $now : $date;
        $columns['post_modified_gmt'] = $update ? ($this->now)(true) : $dateGmt;
        $title = (string) ($columns['post_title'] ?? $existing['post_title'] ?? '');
        $slug = (string) ($columns['post_name'] ?? $existing['post_name'] ?? '');
        if ($slug === '' && !in_array($status, ['draft', 'pending', 'auto-draft'], true)) {
            $slug = (string) Sanitize::title($title);
        } elseif ($slug !== '' && (!$update || $slug !== (string) $existing['post_name'])) {
            $slug = (string) Sanitize::title($slug);
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

    /** Writes the resolved columns. @return int the post id */
    public function persist(array $columns, ?int $existingId): int
    {
        if ($existingId !== null) {
            return $this->writer->update($existingId, $columns) ? $existingId : 0;
        }
        return $this->writer->insert($columns);
    }

    /**
     * The error for a post the database door would not write, its text not
     * what the table holds (probe refused-writes): an insert's or an
     * update's (a post that already has an id), naming the first such column.
     *
     * @param array<string, mixed> $columns
     */
    public static function unwritten(array $columns, ?int $existingId): Errors
    {
        $data = Db::fieldRefusal((string) Db::invalidColumn($columns));
        return $existingId !== null ? Handoff::newError('db_update_error', 'Could not update post in the database.', $data) : Handoff::newError('db_insert_error', 'Could not insert post into the database.', $data);
    }

    /** Gives a new post written without a guid its permalink as one, once its slug, terms and meta are in. */
    public function fillGuid(int $id, array $columns, Closure $guid): void
    {
        if (($columns['guid'] ?? '') === '') {
            $this->writer->update($id, ['guid' => $guid($id)]);
        }
    }

    /**
     * The categories a saved post hands wp_set_post_categories: none when
     * its type has no categories (null); an update that names none keeps
     * the ones it has; otherwise the ones given, each empty value dropped.
     * None left is the default category for a post that is not an
     * auto-draft, else an empty list (which wp_set_post_categories decides).
     *
     * @param array<string, mixed> $postarr
     * @param list<string> $taxonomies the type's taxonomies
     * @return array<mixed>|null
     */
    public static function categories(array $postarr, int $id, string $type, string $status, bool $update, array $taxonomies): ?array
    {
        if (!in_array('category', $taxonomies, true)) {
            return null;
        }
        $given = $postarr['post_category'] ?? [];
        $categories = empty($given) ? ($update ? (array) PostReads::categories($id) : []) : array_filter((array) $given);
        if ($categories === [] && $type === 'post' && $status !== 'auto-draft') {
            return [(int) Runtime::options()->filtered('default_category')];
        }
        return $categories;
    }

    /**
     * The category ids wp_set_post_categories sets, and whether they add to
     * the post's: none given (null or an empty list) is the default
     * category in place of the post's, for a post of a type that takes one
     * (posts and the types default_category_post_types adds) that is not an
     * auto-draft; a lone blank string is nothing to set (null ids); else
     * each value as an id, any that is none dropped.
     *
     * @return array{0: list<int>|null, 1: bool}
     */
    public static function categoryIds(mixed $categories, mixed $status, mixed $type, mixed $append): array
    {
        $append = (bool) $append;
        $given = (array) $categories;
        if (count($given) === 1 && reset($given) === '') {
            return [null, $append];
        }
        if ($given !== []) {
            return [array_values(array_filter(array_map('intval', $given))), $append];
        }
        $types = ['post', ...(array) Runtime::hooks()->filter('default_category_post_types', [[]])];
        if (in_array($type, $types, true) && $status !== 'auto-draft') {
            return [[(int) Runtime::options()->filtered('default_category')], false];
        }
        return [[], $append];
    }

    private function type(array $columns, ?array $existing): string
    {
        return (string) ($columns['post_type'] ?? $existing['post_type'] ?? 'post');
    }
}
