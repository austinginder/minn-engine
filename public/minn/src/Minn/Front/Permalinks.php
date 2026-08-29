<?php

declare(strict_types=1);

namespace Minn\Front;

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
    public function __construct(
        private Posts $posts,
        private Terms $terms,
        public string $home,
        public string $structure,
        public int $frontPageId = 0,
        public int $postsPageId = 0,
    ) {
    }

    public static function fromDb(Db $db): self
    {
        return new self(
            new Posts($db),
            new Terms($db),
            rtrim($db->option('home') ?? '', '/'),
            $db->option('permalink_structure') ?? '',
            ($db->option('show_on_front') ?? 'posts') === 'page' ? (int) ($db->option('page_on_front') ?? 0) : 0,
            ($db->option('show_on_front') ?? 'posts') === 'page' ? (int) ($db->option('page_for_posts') ?? 0) : 0,
        );
    }

    public function isPretty(): bool
    {
        return $this->structure !== '';
    }

    public function url(string $path = ''): string
    {
        return $this->home . $path;
    }

    public function forPost(array $post): string
    {
        $id = (int) $post['ID'];
        if ($post['post_type'] === 'page') {
            return $this->forPage($post);
        }
        if ($post['post_type'] !== 'post') {
            if ($this->isPretty() && $this->hasPrettyLink($post)) {
                return $this->url('/' . $post['post_type'] . '/' . $post['post_name'] . '/');
            }
            return $this->url('/?p=' . $id);
        }
        if (!$this->isPretty() || !$this->hasPrettyLink($post)) {
            return $this->url('/?p=' . $id);
        }
        return $this->url('/' . ltrim($this->fill($post), '/'));
    }

    public function forPage(array $page): string
    {
        if ($this->frontPageId > 0 && (int) $page['ID'] === $this->frontPageId) {
            return $this->url('/');
        }
        return $this->pagePath($page);
    }

    /** A page's own pretty path, even for the static front page (its comments feed lives there). */
    public function pagePath(array $page): string
    {
        if (!$this->isPretty() || !$this->hasPrettyLink($page)) {
            return $this->url('/?page_id=' . (int) $page['ID']);
        }
        return $this->url('/' . $this->posts->pathOf($page) . '/');
    }

    /**
     * An attachment's public link: its slug under the parent's permalink
     * when attached, at the root when not, or the query form under plain
     * permalinks.
     */
    public function forAttachment(array $attachment): string
    {
        $id = (int) $attachment['ID'];
        if (!$this->isPretty() || $attachment['post_name'] === '') {
            return $this->url('/?attachment_id=' . $id);
        }
        $parent = (int) $attachment['post_parent'] > 0 ? $this->posts->find((int) $attachment['post_parent']) : null;
        if ($parent !== null) {
            return rtrim($this->forPost($parent), '/') . '/' . $attachment['post_name'] . '/';
        }
        return $this->url('/' . $attachment['post_name'] . '/');
    }

    public function forTerm(array $term): string
    {
        $taxonomy = (string) $term['taxonomy'];
        if (!$this->isPretty()) {
            return $taxonomy === 'category'
                ? $this->url('/?cat=' . (int) $term['term_id'])
                : $this->url('/?tag=' . $term['slug']);
        }
        $base = $taxonomy === 'category' ? 'category' : 'tag';
        return $this->url("/{$base}/" . $this->terms->pathOf($term) . '/');
    }

    public function forAuthor(array $user): string
    {
        return $this->isPretty()
            ? $this->url('/author/' . $user['user_nicename'] . '/')
            : $this->url('/?author=' . (int) $user['ID']);
    }

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

    public function forSearch(string $term): string
    {
        return $this->url('/?s=' . rawurlencode($term));
    }

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

    private function hasPrettyLink(array $post): bool
    {
        return in_array($post['post_status'], ['publish', 'private'], true) && $post['post_name'] !== '';
    }

    private function fill(array $post): string
    {
        $time = strtotime((string) $post['post_date']) ?: 0;
        $author = null;
        $category = null;
        if (str_contains($this->structure, '%author%')) {
            $author = (string) (Db::shared()->value(
                "SELECT user_nicename FROM " . Db::shared()->table('users') . " WHERE ID = ? LIMIT 1",
                [(int) $post['post_author']],
            ) ?? '');
        }
        if (str_contains($this->structure, '%category%')) {
            $category = $this->posts->firstCategorySlug((int) $post['ID']) ?? 'uncategorized';
        }
        return strtr($this->structure, [
            '%year%' => date('Y', $time),
            '%monthnum%' => date('m', $time),
            '%day%' => date('d', $time),
            '%hour%' => date('H', $time),
            '%minute%' => date('i', $time),
            '%second%' => date('s', $time),
            '%post_id%' => (string) $post['ID'],
            '%postname%' => (string) $post['post_name'],
            '%category%' => (string) $category,
            '%author%' => (string) $author,
        ]);
    }
}
