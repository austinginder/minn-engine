<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Content\PostFilter;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Runtime\Runtime;

/**
 * What a rewrite rule's query vars stand for, when a plugin's rule (its
 * own, or one it changed) decided the address rather than the engine's
 * reading of the path (suite plugin-rules): a post by id, by name (of a
 * type the vars name), an attachment by name, a page by path, a plugin
 * type's item by its own var; else a category's, tag's or plugin
 * taxonomy's archive, an author's, a date's, a post type's, a search; else
 * the front. A rule that set error is a 404.
 */
final readonly class RuleRoutes
{
    /**
     * @param Closure(list<string>, int): ?Resolution $page a page or post by its path, as the resolver finds one
     * @param Closure(PostRecord): bool $readable whether the reader may read a post
     */
    public function __construct(
        private Posts $posts,
        private ArchiveAddresses $archives,
        private Closure $page,
        private Closure $readable,
    ) {
    }

    /** The resolution the vars stand for. @param array<string, string> $vars */
    public function resolve(array $vars): Resolution
    {
        if (isset($vars['error'])) {
            return Resolution::notFound();
        }
        $paged = max(1, (int) ($vars['paged'] ?? 1));
        ['post_type' => $type] = $vars + ['post_type' => ''];
        return $this->single($vars, $type, $paged) ?? $this->archive($vars, $type, $paged);
    }

    /** A single the vars name (a post by name of the type they give, if any); null when they name none. @param array<string, string> $vars */
    private function single(array $vars, string $type, int $paged): ?Resolution
    {
        if (isset($vars['p']) || isset($vars['page_id'])) {
            $id = (int) ($vars['p'] ?? $vars['page_id']);
            return $this->found($id > 0 ? $this->posts->find($id) : null, $paged);
        }
        if (($vars['attachment'] ?? '') !== '') {
            return $this->found($this->posts->findByNameAnyStatus($vars['attachment'], ['attachment']), $paged);
        }
        if (($vars['name'] ?? '') !== '') {
            return $this->found($this->posts->findByNameAnyStatus($vars['name'], [$type !== '' ? $type : 'post']), $paged);
        }
        if (($vars['pagename'] ?? '') !== '') {
            return ($this->page)(self::segments($vars['pagename']), $paged) ?? Resolution::notFound();
        }
        foreach (self::registered('postTypes') as $name => $var) {
            if (($vars[$var] ?? '') !== '') {
                $segments = self::segments($vars[$var]);
                return $this->found($this->posts->findByNameAnyStatus((string) end($segments), [$name]), $paged);
            }
        }
        return null;
    }

    /** The archive the vars name (a post type's when they give one and nothing else), the front when they name none. @param array<string, string> $vars */
    private function archive(array $vars, string $type, int $paged): Resolution
    {
        if (($vars['category_name'] ?? '') !== '') {
            return $this->archives->term('category', self::segments($vars['category_name']), $paged);
        }
        if (($vars['tag'] ?? '') !== '') {
            return $this->archives->term('post_tag', [$vars['tag']], $paged);
        }
        $taxonomies = self::registered('taxonomies');
        if (($vars['taxonomy'] ?? '') !== '' && ($vars['term'] ?? '') !== '') {
            $taxonomies = [$vars['taxonomy'] => 'term'];
        }
        foreach ($taxonomies as $name => $var) {
            if (($vars[$var] ?? '') !== '') {
                $types = (array) (Runtime::registry()->taxonomy($name)['object_type'] ?? []);
                return $this->archives->taxonomy($name, array_values(array_map('strval', $types)), self::segments($vars[$var]), $paged);
            }
        }
        if (($vars['author_name'] ?? '') !== '') {
            return $this->archives->author($vars['author_name'], $paged);
        }
        if (($vars['year'] ?? '') !== '') {
            return $this->archives->date(array_values(array_filter([$vars['year'], $vars['monthnum'] ?? '', $vars['day'] ?? ''], static fn ($part) => $part !== '')), $paged);
        }
        if ($type !== '') {
            return $this->typeArchive($type, $paged);
        }
        return ($vars['s'] ?? '') !== '' ? $this->archives->search($vars['s'], $paged) : $this->archives->home($paged);
    }

    /** A post type's archive, when it has one; a 404 when it has none or is paged past its end. */
    private function typeArchive(string $name, int $paged): Resolution
    {
        $type = Runtime::registry()->postType($name);
        if ($type === null || empty($type['has_archive'])) {
            return Resolution::notFound();
        }
        $total = $this->posts->count(PostFilter::types($name));
        return $paged > 1 && $paged > $this->archives->pages($total) ? Resolution::notFound() : Resolution::postTypeArchive(['name' => $name] + $type, $paged);
    }

    private function found(?PostRecord $post, int $paged): Resolution
    {
        return $post !== null && ($this->readable)($post) ? Resolution::single($post, $paged) : Resolution::notFound();
    }

    /**
     * The plugin post types or taxonomies that answer to a var of their own:
     * name => var.
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
