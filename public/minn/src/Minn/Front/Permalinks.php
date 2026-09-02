<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\TermRecord;
use Minn\Content\UserRecord;
use Minn\Content\PostRecord;
use Closure;
use Minn\Runtime\Registry;
use Minn\Runtime\Runtime;
use Minn\Content\Posts;
use Minn\Content\Terms;
use Minn\Db;

/**
 * Builds public URLs from the site's permalink structure. With an empty
 * structure every link is a query-string form (?p=, ?cat=); with a
 * structure, published and private posts get their pretty form and every
 * other status keeps the query form, which is what the reference emits.
 */
final readonly class Permalinks
{
    /** Core taxonomies with no front-end archive: their terms link by query only. */
    private const QUERY_ONLY = ['wp_pattern_category', 'wp_theme', 'wp_template_part_area'];

    public function __construct(
        private Posts $posts,
        private Terms $terms,
        public string $home,
        public string $structure,
        public int $frontPageId = 0,
        public int $postsPageId = 0,
        /** @var (Closure(): ?Registry)|null the plugin registrations, once the runtime has them */
        private ?Closure $registry = null,
    ) {
    }

    /** The rewrite slug a plugin gave its post type, else the type's name. */
    public function typeSlug(string $type): string
    {
        $row = $this->registry === null ? null : ($this->registry)()?->postType($type);
        $slug = $row['rewrite']['slug'] ?? null;
        return is_string($slug) && $slug !== '' ? trim($slug, '/') : $type;
    }

    /** The rewrite slug a plugin gave its taxonomy; null for one the engine does not know. */
    public function taxonomySlug(string $taxonomy): ?string
    {
        $row = $this->registry === null ? null : ($this->registry)()?->taxonomy($taxonomy);
        $slug = $row['rewrite']['slug'] ?? null;
        return is_string($slug) && $slug !== '' ? trim($slug, '/') : ($row === null ? null : $taxonomy);
    }

    /** A post type archive's address: its has_archive slug, or the type's slug when has_archive is true. */
    public function forPostTypeArchive(array $type): string
    {
        $archive = $type['has_archive'] ?? false;
        $slug = is_string($archive) && $archive !== '' ? trim($archive, '/') : $this->typeSlug((string) $type['name']);
        return $this->isPretty() ? $this->url('/' . $slug . '/') : $this->url('/?post_type=' . rawurlencode((string) $type['name']));
    }

    /** Link building from the site's own settings. */
    public static function fromDb(Db $db): self
    {
        return new self(
            new Posts($db),
            new Terms($db),
            rtrim($db->option('home') ?? '', '/'),
            $db->option('permalink_structure') ?? '',
            ($db->option('show_on_front') ?? 'posts') === 'page' ? (int) ($db->option('page_on_front') ?? 0) : 0,
            ($db->option('show_on_front') ?? 'posts') === 'page' ? (int) ($db->option('page_for_posts') ?? 0) : 0,
            static fn (): ?Registry => Runtime::booted() ? Runtime::registry() : null,
        );
    }

    /** Whether the site uses a permalink structure. */
    public function isPretty(): bool
    {
        return $this->structure !== '';
    }

    /** A URL under the home. */
    public function url(string $path = ''): string
    {
        return $this->home . $path;
    }

    /** A post's permalink. */
    public function forPost(PostRecord $post): string
    {
        $id = $post->id;
        if ($post->isPage()) {
            return $this->forPage($post);
        }
        // A navigation menu is not publicly queryable and gets no type
        // prefix: the reference fills the plain post structure for it,
        // category token and all.
        if ($post->type !== 'post' && $post->type !== 'wp_navigation' && $post->type !== 'wp_block') {
            if ($this->isPretty() && $this->hasPrettyLink($post)) {
                return $this->url('/' . $this->typeSlug($post->type) . '/' . $post->slug . '/');
            }
            return $this->url('/?p=' . $id);
        }
        if (!$this->isPretty() || !$this->hasPrettyLink($post)) {
            return $this->url('/?p=' . $id);
        }
        return $this->url('/' . ltrim($this->fill($post), '/'));
    }

    /** A page's permalink, the home for the front page. */
    public function forPage(PostRecord $page): string
    {
        if ($this->frontPageId > 0 && $page->id === $this->frontPageId) {
            return $this->url('/');
        }
        return $this->pagePath($page);
    }

    /** A page's own pretty path, even for the static front page (its comments feed lives there). */
    public function pagePath(PostRecord $page): string
    {
        if (!$this->isPretty() || !$this->hasPrettyLink($page)) {
            return $this->url('/?page_id=' . $page->id);
        }
        return $this->url('/' . $this->posts->pathOf($page) . '/');
    }

    /**
     * An attachment's public link: its slug under the parent's permalink
     * when attached, at the root when not, or the query form under plain
     * permalinks.
     */
    public function forAttachment(PostRecord $attachment): string
    {
        $id = $attachment->id;
        if (!$this->isPretty() || $attachment->slug === '') {
            return $this->url('/?attachment_id=' . $id);
        }
        $parent = $attachment->parentId > 0 ? $this->posts->find($attachment->parentId) : null;
        if ($parent !== null) {
            return rtrim($this->forPost($parent), '/') . '/' . $attachment->slug . '/';
        }
        return $this->url('/' . $attachment->slug . '/');
    }

    /** A term's archive URL. */
    public function forTerm(TermRecord $term): string
    {
        $taxonomy = (string) $term['taxonomy'];
        if (in_array($taxonomy, self::QUERY_ONLY, true)) {
            // No rewrite rule serves these, pretty permalinks or not.
            return $this->url('/?taxonomy=' . $taxonomy . '&term=' . $term['slug']);
        }
        if (!$this->isPretty()) {
            return $taxonomy === 'category'
                ? $this->url('/?cat=' . (int) $term['term_id'])
                : $this->url('/?tag=' . $term['slug']);
        }
        $base = match ($taxonomy) {
            'category' => 'category',
            'post_tag' => 'tag',
            default => $this->taxonomySlug($taxonomy) ?? $taxonomy,
        };
        return $this->url("/{$base}/" . $this->terms->pathOf($term) . '/');
    }

    /** An author's archive URL. */
    public function forAuthor(UserRecord $user): string
    {
        return $this->isPretty()
            ? $this->url('/author/' . $user->nicename . '/')
            : $this->url('/?author=' . $user->id);
    }

    /** A date archive's URL. */
    public function forDate(int $year, ?int $month = null, ?int $day = null): string
    {
        if (!$this->isPretty()) {
            return $this->url('/?m=' . $year . ($month ? sprintf('%02d', $month) : '') . ($day ? sprintf('%02d', $day) : ''));
        }
        $path = '/' . $year . '/';
        if ($month !== null) {
            $path .= sprintf('%02d/', $month);
        }
        if ($day !== null) {
            $path .= sprintf('%02d/', $day);
        }
        return $this->url($path);
    }

    /** A search's URL. */
    public function forSearch(string $term): string
    {
        return $this->url('/?s=' . rawurlencode($term));
    }

    /** A listing URL for a page number. */
    public function forPaged(string $baseUrl, int $page): string
    {
        return $page <= 1 ? $baseUrl : rtrim($baseUrl, '/') . "/page/{$page}/";
    }

    /**
     * A regex over the structure's tokens, so an incoming path can be
     * matched back to the post it names. Null when the structure has no
     * identifying token.
     */
    public function structureRegex(): ?string
    {
        if (!$this->isPretty() || !preg_match('/%(postname|post_id)%/', $this->structure)) {
            return null;
        }
        $tokens = [
            '%year%' => '(?P<year>\d{4})',
            '%monthnum%' => '(?P<monthnum>\d{2})',
            '%day%' => '(?P<day>\d{2})',
            '%hour%' => '(?P<hour>\d{2})',
            '%minute%' => '(?P<minute>\d{2})',
            '%second%' => '(?P<second>\d{2})',
            '%post_id%' => '(?P<post_id>\d+)',
            '%postname%' => '(?P<postname>[^/]+)',
            '%category%' => '(?P<category>.+?)',
            '%author%' => '(?P<author>[^/]+)',
        ];
        $regex = strtr(preg_quote(trim($this->structure, '/'), '#'), $tokens);
        return "#^{$regex}$#";
    }

    private function hasPrettyLink(PostRecord $post): bool
    {
        return in_array($post->status, ['publish', 'private'], true) && $post->slug !== '';
    }

    private function fill(PostRecord $post): string
    {
        $time = strtotime($post->date) ?: 0;
        $author = null;
        $category = null;
        if (str_contains($this->structure, '%author%')) {
            $author = (string) (Db::current()->value(
                "SELECT user_nicename FROM " . Db::current()->table('users') . " WHERE ID = ? LIMIT 1",
                [$post->authorId],
            ) ?? '');
        }
        if (str_contains($this->structure, '%category%')) {
            $category = $this->posts->firstCategorySlug($post->id) ?? 'uncategorized';
        }
        return strtr($this->structure, [
            '%year%' => date('Y', $time),
            '%monthnum%' => date('m', $time),
            '%day%' => date('d', $time),
            '%hour%' => date('H', $time),
            '%minute%' => date('i', $time),
            '%second%' => date('s', $time),
            '%post_id%' => $post->id,
            '%postname%' => $post->slug,
            '%category%' => (string) $category,
            '%author%' => (string) $author,
        ]);
    }
}
