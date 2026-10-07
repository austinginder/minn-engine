<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Http\Request;

/**
 * The single a query string asks for, as the reference's request parse and
 * canonical redirect answer it: by id (?p=, ?page_id=; an attachment's goes
 * to its own page), an attachment by id or slug (?attachment_id=,
 * ?attachment=), a post by slug (?name=), a page by path (?pagename=).
 * With canonical redirects on, an address that is not the single's own
 * moves there, the other query arguments along; without, each var is
 * strict about the type it finds.
 */
final readonly class SingleQueries
{
    public function __construct(
        private Posts $posts,
        private Permalinks $permalinks,
        private AttachmentAddresses $attachments,
        private SingleAddresses $elsewhere,
        /** @var Closure(PostRecord): bool whether the reader may read a post */
        private Closure $readable,
    ) {
    }

    /** The resolution the query's single vars amount to; null when it has none of them. */
    public function find(Request $request, Redirects $redirects): ?Resolution
    {
        foreach (['p', 'page_id'] as $key) {
            if ($request->has($key)) {
                return $this->byId($request, $key, $redirects);
            }
        }
        $attachment = $this->attachments->fromQuery($request, $redirects);
        if ($attachment !== null) {
            return $attachment;
        }
        if ($request->has('name')) {
            $post = $this->posts->findByName((string) $request->query('name'), ['post']);
            return $post === null ? $this->elsewhere->formerSlug((string) $request->query('name')) ?? Resolution::notFound() : $this->singleOrRedirect($post, $redirects);
        }
        return $request->has('pagename') ? $this->byPath((string) $request->query('pagename'), $redirects) : null;
    }

    /** ?p= or ?page_id=: the post or page (an attachment to its own page), moving to its pretty address. */
    private function byId(Request $request, string $key, Redirects $redirects): Resolution
    {
        [$canonical, $pretty] = [$redirects->follows(), $this->permalinks->isPretty()];
        $post = $this->posts->find((int) $request->query($key, '0'));
        $attachment = $post === null ? null : $this->attachments->asPost($post, $request, $key, $redirects);
        if ($attachment !== null) {
            return $attachment;
        }
        if ($post === null || !in_array($post->type, ['post', 'page'], true) || !($this->readable)($post)) {
            return Resolution::notFound();
        }
        if (!$canonical) {
            // Without the canonical pass each var is strict about type: ?p= finds only posts, ?page_id= only pages.
            return $post->type === ($key === 'p' ? 'post' : 'page') ? Resolution::single($post) : Resolution::notFound();
        }
        $link = $this->permalinks->forPost($post);
        // The other arguments go along (?embed=true, a campaign's tags), as the reference's canonical redirect keeps them.
        return $pretty && !str_contains($link, '?') ? Resolution::redirect($link . $request->queryStringWithout($key)) : Resolution::single($post);
    }

    /** ?pagename=: the page at that path (the front page moves to the root), else one by the last slug. */
    private function byPath(string $path, Redirects $redirects): Resolution
    {
        $canonical = $redirects->follows();
        $segments = array_values(array_filter(explode('/', $path), static fn (string $s) => $s !== ''));
        $page = $this->posts->pageByPath($segments);
        if ($page !== null) {
            if ($page->id === $this->permalinks->frontPageId) {
                return $canonical ? Resolution::redirect($this->permalinks->url('/')) : Resolution::frontPage($page, 1);
            }
            return Resolution::single($page);
        }
        $bySlug = $segments === [] || !$canonical ? null : $this->posts->findByName(end($segments), ['page']);
        return $bySlug === null ? Resolution::notFound() : Resolution::redirect($this->permalinks->forPost($bySlug));
    }

    private function singleOrRedirect(PostRecord $post, Redirects $redirects): Resolution
    {
        if (!($this->readable)($post)) {
            return Resolution::notFound();
        }
        $link = $this->permalinks->forPost($post);
        return $redirects->follows() && $this->permalinks->isPretty() && !str_contains($link, '?') ? Resolution::redirect($link) : Resolution::single($post);
    }
}
