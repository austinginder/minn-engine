<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Content\Terms;
use Minn\Db;

/**
 * The addresses a single answers to besides its own, as the reference
 * treats them: a slug the post used to have (alone, with a page number, or
 * with an embed or trackback suffix), and an address that puts it under a
 * category it is not in.
 */
final readonly class SingleAddresses
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Permalinks $permalinks,
    ) {
    }

    /**
     * A slug a post used to have redirects to where the post lives now,
     * keeping the page number and dropping the query string; the lookup
     * ignores status because the reference does (unreadable posts land on
     * their `?p=` form and answer 404 there).
     */
    public function formerSlug(string $slug, int $paged = 1): ?Resolution
    {
        $former = $this->posts->byOldSlug($slug, ['post']);
        if ($former === null) {
            return null;
        }
        // Only a published post is sent to its pretty address; any other
        // status (private included) goes to its ?p= form.
        $link = $former->isPublished() ? $this->permalinks->forPost($former) : $this->permalinks->url('/?p=' . $former->id);
        if ($paged > 1 && !str_contains($link, '?')) {
            $link = rtrim($link, '/') . "/page/{$paged}/";
        }
        return Resolution::redirect($link);
    }

    /**
     * Whether a post was asked for under a category it is not in, where
     * the structure names the category: the category the address names is
     * none at that path, or not one of the post's. The reference finds the
     * post by its name whatever the category, then moves to its own
     * address (a post in two categories answers under either).
     *
     * @param array<string, string> $vars
     */
    public function misplaced(PostRecord $post, array $vars): bool
    {
        $path = array_values(array_filter(explode('/', (string) ($vars['category_name'] ?? '')), static fn (string $s) => $s !== ''));
        if ($path === [] || $post->type === 'page' || $post->type === 'attachment' || !str_contains($this->permalinks->structure, '%category%')) {
            return false;
        }
        $terms = new Terms($this->db);
        $category = $terms->findBySlug('category', (string) end($path));
        if ($category === null || strcasecmp($terms->pathOf($category), implode('/', $path)) !== 0) {
            return true;
        }
        return !in_array($category->id, array_column($this->posts->terms($post->id, 'category'), 0), true);
    }

    /**
     * A former slug asked for with a suffix: embed follows the post to its
     * new embed address, trackback goes to the post itself. The front
     * page's redirect to the root keeps its own handling.
     */
    public function formerSuffix(Resolution $redirect, string $suffix): Resolution
    {
        $location = (string) $redirect->location;
        if ($suffix === 'embed' && $location !== $this->permalinks->url('/') && !str_contains($location, '?')) {
            return Resolution::redirect(rtrim($location, '/') . '/embed/');
        }
        return $redirect;
    }
}
