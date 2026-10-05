<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Db;

/**
 * The addresses a single answers to besides its own, as the reference
 * treats them: a slug the post used to have (alone, with a page number, or
 * with an embed or trackback suffix), and its comment-page-N addresses.
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
     * A single's comment-page-N address: served as the single when the site
     * pages its comments, sent to the single's own address when it does
     * not, as the reference does. (The engine does not page the comment
     * list itself yet; contracts/front.)
     */
    public function commentPage(?Resolution $single, Redirects $redirects): Resolution
    {
        if ($single === null || $single->kind === Kind::Redirect || !$single->record instanceof PostRecord) {
            return $single ?? Resolution::notFound();
        }
        if (!(bool) $this->db->option('page_comments') && $redirects->follows()) {
            return Resolution::redirect($this->permalinks->forPost($single->record));
        }
        return $single;
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
