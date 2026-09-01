<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Auth\TypeCapabilities;
use Minn\Content\Blocks;
use Minn\Content\Excerpt;
use Minn\Content\PostStatus;
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
    /** The one post type whose REST shape is not post-shaped. */
    private const NAVIGATION = 'wp_navigation';

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
            'wp_navigation' => 'navigation',
            default => str_replace('_', '-', $type),
        };
    }

    /**
     * A navigation menu is a post with almost nothing on it: no author, no
     * excerpt, no featured image, no comments, no taxonomies, and so no
     * class list either. What it does carry is a template field and the
     * rendered menu.
     */
    private function navigationView(PostRecord $p): array
    {
        $protected = $p->isProtected();
        return [
            'id' => $p->id,
            'date' => self::date($p->date),
            'date_gmt' => self::date($p->dateGmt),
            'guid' => ['rendered' => $p->guid],
            'modified' => self::date($p->modified),
            'modified_gmt' => self::date($p->modifiedGmt),
            'slug' => $p->slug,
            'status' => $p->status,
            'type' => $p->type,
            'link' => $this->permalinks->forPost($p),
            'title' => ['rendered' => Texturize::html($p->title)],
            'content' => [
                'rendered' => $protected ? '' : Blocks::render($p->content),
                'protected' => $protected,
            ],
            'template' => '',
            '_links' => $this->links($p),
        ];
    }

    /** The view-context object: the shared fields, then the type's own, then class_list and _links. */
    public function view(PostRecord $p): array
    {
        if ($p->type === self::NAVIGATION) {
            return $this->navigationView($p);
        }
        $protected = $p->isProtected();
        $object = [
            'id' => $p->id,
            'date' => self::date($p->date),
            'date_gmt' => self::date($p->dateGmt),
            'guid' => ['rendered' => $p->guid],
            'modified' => self::date($p->modified),
            'modified_gmt' => self::date($p->modifiedGmt),
            'slug' => $p->slug,
            'status' => $p->status,
            'type' => $p->type,
            'link' => $this->permalinks->forPost($p),
            'title' => ['rendered' => Texturize::html($p->title)],
            'content' => ['rendered' => $protected ? '' : Blocks::render($p->content), 'protected' => $protected],
            'excerpt' => ['rendered' => $protected ? '' : Excerpt::render($p), 'protected' => $protected],
            'author' => $p->authorId,
            'featured_media' => (int) ($this->posts->meta($p->id, '_thumbnail_id') ?? 0),
        ];
        $terms = $p->type === 'page' ? [] : $this->viewTerms($p);
        $object = [...$object, ...$this->typeFields($p, $terms)];
        $object['class_list'] = $this->classList($p, $object['featured_media'] > 0, $terms);
        $object['_links'] = $this->links($p);
        return $object;
    }

    /**
     * The taxonomies a non-page carries, each as [term_id, slug] pairs; the
     * format terms only for posts.
     *
     * @return array<string, list<array{0: int, 1: string}>>
     */
    private function viewTerms(PostRecord $p): array
    {
        return [
            'category' => $this->posts->terms($p->id, 'category'),
            'post_tag' => $this->posts->terms($p->id, 'post_tag'),
            'post_format' => $p->type === 'post' ? $this->posts->terms($p->id, 'post_format') : [],
        ];
    }

    /**
     * Pages carry parent and menu_order first; posts carry their term ids,
     * and only the post type has sticky and format.
     *
     * @param array<string, list<array{0: int, 1: string}>> $terms
     * @return array<string, mixed>
     */
    private function typeFields(PostRecord $p, array $terms): array
    {
        $shared = [
            'comment_status' => $p->commentStatus,
            'ping_status' => $p->pingStatus,
            'template' => '',
            'meta' => ['footnotes' => $this->posts->meta($p->id, 'footnotes') ?? ''],
        ];
        if ($p->type === 'page') {
            return ['parent' => $p->parentId, 'menu_order' => $p->menuOrder, ...$shared];
        }
        $fields = [
            ...$shared,
            'categories' => array_map(static fn (array $t) => $t[0], $terms['category']),
            'tags' => array_map(static fn (array $t) => $t[0], $terms['post_tag']),
        ];
        if ($p->type === 'post') {
            $fields['sticky'] = in_array($p->id, Serialized::intList($this->db->option('sticky_posts')), true);
            $fields['format'] = self::format($terms['post_format']);
        }
        return $fields;
    }

    /**
     * The reference's class_list order: identity, type, status, format (posts
     * only), password, thumbnail, hentry, then one class per term.
     *
     * @param array<string, list<array{0: int, 1: string}>> $terms
     * @return list<string>
     */
    private static function classList(PostRecord $p, bool $thumbnail, array $terms): array
    {
        $classes = ['post-' . $p->id, $p->type, 'type-' . $p->type, 'status-' . $p->status];
        if ($p->type === 'post') {
            $classes[] = 'format-' . self::format($terms['post_format']);
        }
        if ($p->isProtected()) {
            $classes[] = 'post-password-required';
        }
        if ($thumbnail) {
            $classes[] = 'has-post-thumbnail';
        }
        $classes[] = 'hentry';
        foreach (['category' => 'category-', 'post_tag' => 'tag-', 'post_format' => 'post_format-'] as $taxonomy => $prefix) {
            foreach ($terms[$taxonomy] ?? [] as $term) {
                $classes[] = $prefix . $term[1];
            }
        }
        return $classes;
    }

    /** @param list<array{0: int, 1: string}> $formatTerms */
    private static function format(array $formatTerms): string
    {
        return $formatTerms === [] ? 'standard' : str_replace('post-format-', '', $formatTerms[0][1]);
    }

    public function links(PostRecord $p): array
    {
        $id = $p->id;
        $type = $p->type;
        $base = '/wp/v2/' . self::restBase($type);
        $author = $p->authorId;
        $predecessor = $this->posts->latestRevisionId($id);
        $featured = (int) ($this->posts->meta($id, '_thumbnail_id') ?? 0);

        $links = [
            'self' => [['href' => $this->url->to("{$base}/{$id}"), 'targetHints' => ['allow' => $this->allow($id)]]],
            'collection' => [['href' => $this->url->to($base)]],
            'about' => [['href' => $this->url->to('/wp/v2/types/' . $type)]],
            // An authorless post (post_author 0) carries no author link at
            // all, and a navigation menu never carries one: it has no author
            // field to link from.
            'author' => $author > 0 && $type !== self::NAVIGATION ? [['embeddable' => true, 'href' => $this->url->to('/wp/v2/users/' . $author)]] : null,
            'replies' => $type === self::NAVIGATION
                ? null
                : [['embeddable' => true, 'href' => $this->url->to('/wp/v2/comments', ['post' => $id])]],
            'version-history' => [['count' => $this->posts->revisionCount($id), 'href' => $this->url->to("{$base}/{$id}/revisions")]],
            'predecessor-version' => $predecessor > 0
                ? [['id' => $predecessor, 'href' => $this->url->to("{$base}/{$id}/revisions/{$predecessor}")]]
                : null,
            'up' => $type === 'page' && $p->parentId > 0
                ? [['embeddable' => true, 'href' => $this->url->to($base . '/' . $p->parentId)]]
                : null,
            'wp:featuredmedia' => $featured > 0
                ? [['embeddable' => true, 'href' => $this->url->to('/wp/v2/media/' . $featured)]]
                : null,
            'wp:attachment' => [['href' => $this->url->to('/wp/v2/media', ['parent' => $id])]],
            'wp:term' => $type !== 'page' && $type !== self::NAVIGATION ? [
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
    public function edit(PostRecord $p, int $userId): array
    {
        $id = $p->id;
        $view = $this->view($p);
        $protected = $p->isProtected();

        $view['guid'] = ['rendered' => $p->guid, 'raw' => $p->guid];
        $view['title'] = ['raw' => $p->title, 'rendered' => Texturize::html($p->title)];
        $view['content'] = [
            'raw' => $p->content,
            'rendered' => PostStatus::of($p) === PostStatus::Trash ? '' : Blocks::render($p->content),
            'protected' => $protected,
            'block_version' => str_contains($p->content, '<!-- wp:') ? 1 : 0,
        ];
        if (isset($view['excerpt'])) {
            $view['excerpt'] = [
                'raw' => $p->excerpt,
                'rendered' => Excerpt::render($p),
                'protected' => $protected,
            ];
        }

        // The reference's edit-context key order: password before slug,
        // permalink_template and generated_slug just before class_list.
        $ordered = [];
        foreach ($view as $key => $value) {
            if ($key === 'slug') {
                $ordered['password'] = $p->password;
            }
            if ($key === 'class_list') {
                $ordered['permalink_template'] = $this->permalinkTemplate($p);
                $ordered['generated_slug'] = Slug::sanitize($p->title);
            }
            $ordered[$key] = $value;
        }
        // _links stays last: re-assigning a key it already holds would keep
        // the place the view object gave it.
        unset($ordered['_links']);
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
    public function permalinkTemplate(PostRecord $p): string
    {
        $id = $p->id;
        if (!$this->permalinks->isPretty()) {
            return $this->url->home('/?' . ($p->isPage() ? 'page_id' : 'p') . '=' . $id);
        }
        if ($p->isPage()) {
            $parent = $p->parentId > 0 ? $this->posts->find($p->parentId) : null;
            $prefix = $parent === null ? '' : '/' . $this->posts->pathOf($parent);
            return $this->url->home($prefix . '/%pagename%/');
        }
        if ($p->type !== 'post') {
            return $this->url->home('/' . $p->type . '/%pagename%/');
        }
        return $this->url->home('/' . trim($this->permalinks->structure, '/') . '/');
    }

    /** Whether a live post carries an autosave newer than its saved revision. */
    public function modifiedUnsaved(PostRecord $p, int $userId): bool
    {
        $id = $p->id;
        if (!$this->caller->capabilities()->can($userId, 'edit_post', $id)) {
            return false;
        }
        if (!in_array($p->status, ['publish', 'future', 'private'], true)) {
            return false;
        }
        return $this->posts->hasNewerAutosave($id, $p->modifiedGmt);
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
    public function editLinks(PostRecord $p, int $userId): array
    {
        $id = $p->id;
        $type = $p->type;
        $can = fn (string $cap, ?int $postId = null): bool => $this->caller->capabilities()->can($userId, $cap, $postId);
        $links = $this->links($p);

        $links['self'][0]['targetHints']['allow'] = $this->allow($id, $userId);

        $self = '/wp/v2/' . self::restBase($type) . '/' . $id;
        $others = TypeCapabilities::editOthers($type);
        $publish = TypeCapabilities::publish($type);

        $actions = [];
        if ($can($publish)) {
            $actions['wp:action-publish'] = true;
        }
        if ($can('unfiltered_html')) {
            $actions['wp:action-unfiltered-html'] = true;
        }
        if ($can($others) && $type !== self::NAVIGATION) {
            if ($type === 'post') {
                $actions['wp:action-sticky'] = true;
            }
            $actions['wp:action-assign-author'] = true;
        }
        // Taxonomy actions belong to types with taxonomies (posts, not pages);
        // assign is broadly held, create is gated per taxonomy.
        if ($type !== 'page' && $type !== self::NAVIGATION) {
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
        $curies = $links['curies'];
        unset($links['curies']);
        foreach ($order as $rel) {
            if (!empty($actions[$rel])) {
                $links[$rel] = [['href' => $this->url->to($self)]];
            }
        }
        // The reference closes the set with curies, after the actions.
        $links['curies'] = $curies;
        return $links;
    }

    /**
     * What the caller may do to this post, which the reference reports in
     * every context: a reader sees GET, an editor of the post sees the
     * write verbs, and DELETE only when they may delete it too.
     *
     * @return list<string>
     */
    private function allow(int $id, ?int $userId = null): array
    {
        $userId ??= $this->caller->id();
        $can = fn (string $cap): bool => $this->caller->capabilities()->can($userId, $cap, $id);
        if (!$can('edit_post')) {
            return ['GET'];
        }
        $allow = ['GET', 'POST', 'PUT', 'PATCH'];
        if ($can('delete_post')) {
            $allow[] = 'DELETE';
        }
        return $allow;
    }

    public static function date(string $mysql): string
    {
        return str_replace(' ', 'T', $mysql);
    }
}
