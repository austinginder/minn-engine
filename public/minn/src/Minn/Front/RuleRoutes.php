<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Runtime\Runtime;

/**
 * What a matched rewrite rule's query vars name, as the reference's main
 * query reads them (suites permalinks, plugin-rules, request-vars): a post
 * or page by id, an attachment by name, a page (or an attachment) by its
 * path, a post by name (of the type the vars give), a registered type's
 * item by its own var; else a search, a category's, tag's, post
 * format's or registered taxonomy's archive, an author's, a date's, a
 * post type's; else the front. A rule that set error is a 404, as is a single
 * the reader may not read. Under a name the date or category the rule also
 * captured does not narrow the find: where the post really lives is the
 * canonical redirect's business.
 */
final readonly class RuleRoutes
{
    /** @param Closure(PostRecord): bool $readable whether the reader may read a post */
    public function __construct(
        private Posts $posts,
        private ArchiveAddresses $archives,
        private int $frontPageId,
        private int $postsPageId,
        private Closure $readable,
    ) {
    }

    /** The resolution the vars stand for. @param array<string, string> $vars */
    public function resolve(array $vars): Resolution
    {
        if (($vars['error'] ?? '') !== '') {
            return Resolution::notFound();
        }
        $paged = max(1, (int) ($vars['paged'] ?? 1));
        $type = (string) ($vars['post_type'] ?? '');
        return $this->single($vars, $type, $paged) ?? $this->archive($vars, $type, $paged);
    }

    /** A single the vars name; null when they name none. @param array<string, string> $vars */
    private function single(array $vars, string $type, int $paged): ?Resolution
    {
        if (($vars['p'] ?? '') !== '' || ($vars['page_id'] ?? '') !== '') {
            $id = (int) (($vars['p'] ?? '') !== '' ? $vars['p'] : $vars['page_id']);
            return $this->found($id > 0 ? $this->posts->find($id) : null, $paged);
        }
        if (($vars['attachment'] ?? '') !== '') {
            return $this->found($this->posts->findByNameAnyStatus($vars['attachment'], ['attachment']), $paged);
        }
        if (($vars['pagename'] ?? '') !== '') {
            return $this->found($this->posts->byTypedPath(self::segments($vars['pagename']), [$type !== '' ? $type : 'page', 'attachment']), $paged);
        }
        if (($vars['name'] ?? '') !== '') {
            return $this->found($this->posts->findByNameAnyStatus($vars['name'], [$type !== '' ? $type : 'post']), $paged);
        }
        foreach (self::registered('postTypes') as $name => $var) {
            if (($vars[$var] ?? '') !== '') {
                $segments = self::segments($vars[$var]);
                $hierarchical = !empty(Runtime::registry()->postType($name)['hierarchical']);
                return $this->found($hierarchical ? $this->posts->byTypedPath($segments, [$name]) : $this->posts->findByNameAnyStatus((string) end($segments), [$name]), $paged);
            }
        }
        return null;
    }

    /**
     * The archive the vars name, the front when they name none: a search
     * first (whatever else they name, as the reference's templates
     * choose), a term's, an author's, a date's, a post type's when they
     * give one and nothing else.
     *
     * @param array<string, string> $vars
     */
    private function archive(array $vars, string $type, int $paged): Resolution
    {
        if (isset($vars['s'])) {
            return $this->archives->search($vars['s'], $paged);
        }
        if (($vars['category_name'] ?? '') !== '') {
            return $this->archives->term('category', self::segments($vars['category_name']), $paged);
        }
        if (($vars['tag'] ?? '') !== '') {
            return $this->archives->term('post_tag', [$vars['tag']], $paged);
        }
        // A format's address names its term without the prefix the term's slug carries.
        if (($vars['post_format'] ?? '') !== '') {
            return $this->archives->taxonomy('post_format', ['post-format-' . $vars['post_format']], $paged);
        }
        $taxonomies = self::registered('taxonomies');
        if (($vars['taxonomy'] ?? '') !== '' && ($vars['term'] ?? '') !== '') {
            $taxonomies = [$vars['taxonomy'] => 'term'];
        }
        foreach ($taxonomies as $name => $var) {
            if (($vars[$var] ?? '') !== '') {
                return $this->archives->taxonomy($name, self::segments($vars[$var]), $paged);
            }
        }
        if (($vars['author_name'] ?? '') !== '') {
            return $this->archives->author($vars['author_name'], $paged);
        }
        if (($vars['year'] ?? '') !== '') {
            return $this->archives->date(array_values(array_filter([$vars['year'], $vars['monthnum'] ?? '', $vars['day'] ?? ''], static fn ($part) => $part !== '')), $paged);
        }
        return $type !== '' ? $this->typeArchive($type, $paged) : $this->archives->home($paged);
    }

    /** A post type's archive, when it has one; the front's listing (of that type) when it has none. */
    private function typeArchive(string $name, int $paged): Resolution
    {
        $type = Runtime::registry()->postType($name);
        return $type === null || empty($type['has_archive']) ? $this->archives->home($paged) : Resolution::postTypeArchive(['name' => $name] + $type, $paged);
    }

    /**
     * A found post as the reader may see it: the static front page, the
     * posts page (the blog's listing), any other post or page; a 404 when
     * there is none or the reader may not read it (an attachment as its
     * parent is).
     */
    private function found(?PostRecord $post, int $paged): Resolution
    {
        if ($post === null || !$this->readable($post)) {
            return Resolution::notFound();
        }
        if ($post->id === $this->frontPageId && $post->isPage()) {
            return Resolution::frontPage($post, $paged);
        }
        if ($post->id === $this->postsPageId && $post->isPage()) {
            return Resolution::postsPage($post, $paged);
        }
        return Resolution::single($post, $paged);
    }

    /** Whether the reader may read a post; an attachment as its parent is, a loose one as it stands. */
    private function readable(PostRecord $post): bool
    {
        if ($post->type !== 'attachment' || $post->status !== 'inherit') {
            return ($this->readable)($post);
        }
        $parent = $post->parentId > 0 ? $this->posts->find($post->parentId) : null;
        return $post->parentId === 0 || ($parent !== null && ($this->readable)($parent));
    }

    /**
     * The registered post types or taxonomies that answer to a var of their
     * own: name => var.
     *
     * @return array<string, string>
     */
    private static function registered(string $kind): array
    {
        $out = [];
        foreach ($kind === 'postTypes' ? Runtime::registry()->postTypes() : Runtime::registry()->taxonomies() as $name => $row) {
            $var = $row['query_var'] ?? false;
            if (empty($row['_builtin']) && is_string($var) && $var !== '') {
                $out[(string) $name] = $var;
            }
        }
        return $out;
    }

    /** @return list<string> */
    private static function segments(string $path): array
    {
        return array_values(array_filter(explode('/', $path), static fn (string $s) => $s !== ''));
    }
}
