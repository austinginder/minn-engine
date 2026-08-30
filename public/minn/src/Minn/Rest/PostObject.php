<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Blocks;
use Minn\Content\Excerpt;
use Minn\Content\Posts;
use Minn\Content\Slug;
use Minn\Content\Texturize;
use Minn\Content\Users;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Support\Serialized;

/**
 * Builds the wp/v2 post and page objects in the reference's shape: the
 * view context, the edit context (raw+rendered dual fields, editor-only
 * fields, and cap-gated wp:action-* links), and their _links blocks.
 */
final readonly class PostObject
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Users $users,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    public static function restBase(string $type): string
    {
        return match ($type) {
            'page' => 'pages',
            'post' => 'posts',
            default => str_replace('_', '-', $type),
        };
    }

    public function view(array $p): array
    {
        $id = (int) $p['ID'];
        $type = (string) $p['post_type'];
        $protected = $p['post_password'] !== '';
        $featured = (int) ($this->posts->meta($id, '_thumbnail_id') ?? 0);

        $object = [
            'id' => $id,
            'date' => self::date($p['post_date']),
            'date_gmt' => self::date($p['post_date_gmt']),
            'guid' => ['rendered' => $p['guid']],
            'modified' => self::date($p['post_modified']),
            'modified_gmt' => self::date($p['post_modified_gmt']),
            'slug' => $p['post_name'],
            'status' => $p['post_status'],
            'type' => $type,
            'link' => $this->permalinks->forPost($p),
            'title' => ['rendered' => Texturize::html((string) $p['post_title'])],
            'content' => [
                'rendered' => $protected ? '' : Blocks::render((string) $p['post_content']),
                'protected' => $protected,
            ],
            'excerpt' => [
                'rendered' => $protected ? '' : Excerpt::render($p),
                'protected' => $protected,
            ],
            'author' => (int) $p['post_author'],
            'featured_media' => $featured,
        ];
        $classes = ['post-' . $id, $type, 'type-' . $type, 'status-' . $p['post_status']];

        if ($type === 'page') {
            $object['parent'] = (int) $p['post_parent'];
            $object['menu_order'] = (int) $p['menu_order'];
            $object['comment_status'] = $p['comment_status'];
            $object['ping_status'] = $p['ping_status'];
            $object['template'] = '';
            $object['meta'] = ['footnotes' => $this->posts->meta($id, 'footnotes') ?? ''];
            if ($protected) {
                $classes[] = 'post-password-required';
            }
            if ($featured > 0) {
                $classes[] = 'has-post-thumbnail';
            }
            $classes[] = 'hentry';
        } else {
            $categories = $this->posts->terms($id, 'category');
            $tags = $this->posts->terms($id, 'post_tag');
            $object['comment_status'] = $p['comment_status'];
            $object['ping_status'] = $p['ping_status'];
            $object['template'] = '';
            $object['meta'] = ['footnotes' => $this->posts->meta($id, 'footnotes') ?? ''];
            $object['categories'] = array_map(static fn (array $t) => $t[0], $categories);
            $object['tags'] = array_map(static fn (array $t) => $t[0], $tags);
            $formats = [];
            if ($type === 'post') {
                $formats = $this->posts->terms($id, 'post_format');
                $format = $formats === [] ? 'standard' : str_replace('post-format-', '', $formats[0][1]);
                $sticky = Serialized::intList($this->db->option('sticky_posts'));
                $object['sticky'] = in_array($id, $sticky, true);
                $object['format'] = $format;
                $classes[] = 'format-' . $format;
            }
            if ($protected) {
                $classes[] = 'post-password-required';
            }
            if ($featured > 0) {
                $classes[] = 'has-post-thumbnail';
            }
            $classes[] = 'hentry';
            foreach ($categories as $term) {
                $classes[] = 'category-' . $term[1];
            }
            foreach ($tags as $term) {
                $classes[] = 'tag-' . $term[1];
            }
            foreach ($formats as $term) {
                $classes[] = 'post_format-' . $term[1];
            }
        }

        $object['class_list'] = $classes;
        $object['_links'] = $this->links($p);
        return $object;
    }

    public function links(array $p): array
    {
        $id = (int) $p['ID'];
        $type = (string) $p['post_type'];
        $base = '/wp/v2/' . self::restBase($type);
        $author = (int) $p['post_author'];
        $predecessor = $this->posts->latestRevisionId($id);
        $featured = (int) ($this->posts->meta($id, '_thumbnail_id') ?? 0);

        $links = [
            'self' => [['href' => $this->url->to("{$base}/{$id}"), 'targetHints' => ['allow' => ['GET']]]],
            'collection' => [['href' => $this->url->to($base)]],
            'about' => [['href' => $this->url->to('/wp/v2/types/' . $type)]],
            // An authorless post (post_author 0) carries no author link at all.
            'author' => $author > 0 ? [['embeddable' => true, 'href' => $this->url->to('/wp/v2/users/' . $author)]] : null,
            'replies' => [['embeddable' => true, 'href' => $this->url->to('/wp/v2/comments', ['post' => $id])]],
            'version-history' => [['count' => $this->posts->revisionCount($id), 'href' => $this->url->to("{$base}/{$id}/revisions")]],
            'predecessor-version' => $predecessor > 0
                ? [['id' => $predecessor, 'href' => $this->url->to("{$base}/{$id}/revisions/{$predecessor}")]]
                : null,
            'up' => $type === 'page' && (int) $p['post_parent'] > 0
                ? [['embeddable' => true, 'href' => $this->url->to($base . '/' . (int) $p['post_parent'])]]
                : null,
            'wp:featuredmedia' => $featured > 0
                ? [['embeddable' => true, 'href' => $this->url->to('/wp/v2/media/' . $featured)]]
                : null,
            'wp:attachment' => [['href' => $this->url->to('/wp/v2/media', ['parent' => $id])]],
            'wp:term' => $type !== 'page' ? [
                ['taxonomy' => 'category', 'embeddable' => true, 'href' => $this->url->to('/wp/v2/categories', ['post' => $id])],
                ['taxonomy' => 'post_tag', 'embeddable' => true, 'href' => $this->url->to('/wp/v2/tags', ['post' => $id])],
            ] : null,
            'curies' => RestUrl::curies(),
        ];
        return array_filter($links, static fn ($value) => $value !== null);
    }

    /**
     * The edit-context object for a given caller: raw+rendered dual fields,
     * password, permalink_template, generated_slug, block_version, Minn
     * Admin's registered list fields, and the cap-gated action links.
     */
    public function edit(array $p, int $userId): array
    {
        $id = (int) $p['ID'];
        $view = $this->view($p);
        $protected = $p['post_password'] !== '';

        $view['guid'] = ['rendered' => $p['guid'], 'raw' => $p['guid']];
        $view['title'] = ['raw' => $p['post_title'], 'rendered' => Texturize::html((string) $p['post_title'])];
        $view['content'] = [
            'raw' => $p['post_content'],
            'rendered' => $p['post_status'] === 'trash' ? '' : Blocks::render((string) $p['post_content']),
            'protected' => $protected,
            'block_version' => str_contains((string) $p['post_content'], '<!-- wp:') ? 1 : 0,
        ];
        $view['excerpt'] = [
            'raw' => $p['post_excerpt'],
            'rendered' => Excerpt::render($p),
            'protected' => $protected,
        ];

        // The reference's edit-context key order: password before slug,
        // permalink_template and generated_slug just before class_list.
        $ordered = [];
        foreach ($view as $key => $value) {
            if ($key === 'slug') {
                $ordered['password'] = $p['post_password'];
            }
            if ($key === 'class_list') {
                $ordered['permalink_template'] = $this->permalinkTemplate($p);
                $ordered['generated_slug'] = Slug::sanitize((string) $p['post_title']);
            }
            $ordered[$key] = $value;
        }
        $ordered['minn_modified'] = $this->modifiedUnsaved($p, $userId);
        $ordered['minn_lock'] = $this->lockHolder($id, $userId);
        $ordered['_links'] = $this->editLinks($p, $userId);
        return $ordered;
    }

    /**
     * The editor's sample permalink: the structure with the name token left
     * in place (pages: the parent path plus %pagename%), or the query form
     * when permalinks are plain.
     */
    public function permalinkTemplate(array $p): string
    {
        $id = (int) $p['ID'];
        if (!$this->permalinks->isPretty()) {
            return $this->url->home('/?' . ($p['post_type'] === 'page' ? 'page_id' : 'p') . '=' . $id);
        }
        if ($p['post_type'] === 'page') {
            $parent = (int) $p['post_parent'] > 0 ? $this->posts->find((int) $p['post_parent']) : null;
            $prefix = $parent === null ? '' : '/' . $this->posts->pathOf($parent);
            return $this->url->home($prefix . '/%pagename%/');
        }
        if ($p['post_type'] !== 'post') {
            return $this->url->home('/' . $p['post_type'] . '/%pagename%/');
        }
        return $this->url->home('/' . trim($this->permalinks->structure, '/') . '/');
    }

    /** Whether a live post carries an autosave newer than its saved revision. */
    public function modifiedUnsaved(array $p, int $userId): bool
    {
        $id = (int) $p['ID'];
        if (!$this->caller->capabilities()->can($userId, 'edit_post', $id)) {
            return false;
        }
        if (!in_array($p['post_status'], ['publish', 'future', 'private'], true)) {
            return false;
        }
        return $this->posts->hasNewerAutosave($id, (string) $p['post_modified_gmt']);
    }

    /** The OTHER user holding a live _edit_lock (150 second window), or null. */
    public function lockHolder(int $id, int $userId): ?array
    {
        if (!$this->caller->capabilities()->can($userId, 'edit_post', $id)) {
            return null;
        }
        $lock = $this->posts->meta($id, '_edit_lock');
        if ($lock === null || !str_contains($lock, ':')) {
            return null;
        }
        [$time, $holder] = explode(':', $lock, 2);
        $holder = (int) $holder;
        if ($holder === 0 || $holder === $userId || (int) $time <= time() - 150) {
            return null;
        }
        $user = $this->users->find($holder);
        return ['user' => $holder, 'name' => $user === null ? 'Someone' : $user['display_name']];
    }

    /** The view links plus the caller's verbs and cap-gated wp:action-* entries. */
    public function editLinks(array $p, int $userId): array
    {
        $id = (int) $p['ID'];
        $type = (string) $p['post_type'];
        $can = fn (string $cap, ?int $postId = null): bool => $this->caller->capabilities()->can($userId, $cap, $postId);
        $links = $this->links($p);

        $allow = ['GET'];
        if ($can('edit_post', $id)) {
            $allow = ['GET', 'POST', 'PUT', 'PATCH'];
            if ($can('delete_post', $id)) {
                $allow[] = 'DELETE';
            }
        }
        $links['self'][0]['targetHints']['allow'] = $allow;

        $self = '/wp/v2/' . self::restBase($type) . '/' . $id;
        $others = $type === 'page' ? 'edit_others_pages' : 'edit_others_posts';
        $publish = $type === 'page' ? 'publish_pages' : 'publish_posts';

        $actions = [];
        if ($can($publish)) {
            $actions['wp:action-publish'] = true;
        }
        if ($can('unfiltered_html')) {
            $actions['wp:action-unfiltered-html'] = true;
        }
        if ($can($others)) {
            if ($type === 'post') {
                $actions['wp:action-sticky'] = true;
            }
            $actions['wp:action-assign-author'] = true;
        }
        // Taxonomy actions belong to types with taxonomies (posts, not pages);
        // assign is broadly held, create is gated per taxonomy.
        if ($type !== 'page') {
            if ($can('manage_categories')) {
                $actions['wp:action-create-categories'] = true;
            }
            $actions['wp:action-assign-categories'] = true;
            if ($can('edit_posts')) {
                $actions['wp:action-create-tags'] = true;
            }
            $actions['wp:action-assign-tags'] = true;
        }
        // Emitted in the reference's alphabetical-by-suffix order.
        $order = [
            'wp:action-assign-author', 'wp:action-assign-categories', 'wp:action-assign-tags',
            'wp:action-create-categories', 'wp:action-create-tags', 'wp:action-publish',
            'wp:action-sticky', 'wp:action-unfiltered-html',
        ];
        foreach ($order as $rel) {
            if (!empty($actions[$rel])) {
                $links[$rel] = [['href' => $this->url->to($self)]];
            }
        }
        return $links;
    }

    public static function date(string $mysql): string
    {
        return str_replace(' ', 'T', $mysql);
    }
}
