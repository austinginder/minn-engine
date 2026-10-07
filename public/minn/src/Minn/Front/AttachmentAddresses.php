<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Db;
use Minn\Http\Request;

/**
 * The addresses an attachment's page answers to besides those its rules
 * find (Front\RuleTable: a loose one by its slug at the top, any by its
 * slug under another address, "attachment/" between or not): by id or
 * slug in the query (?attachment_id=, ?attachment=), and as a post (?p=,
 * ?page_id=), which moves to its own page. With
 * attachment pages off (wp_attachment_pages_enabled, the default) every
 * address moves to the file itself; with them on, one that is not its own
 * moves to its page. An attachment is readable as its parent is.
 */
final readonly class AttachmentAddresses
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Permalinks $permalinks,
        /** @var Closure(PostRecord): bool whether the reader may read a post */
        private Closure $readable,
    ) {
    }

    /** Whether a resolution is an attachment's page. */
    public static function names(Resolution $resolution): bool
    {
        return $resolution->kind === Kind::Single && $resolution->record instanceof PostRecord && $resolution->record->type === 'attachment';
    }

    /** ?attachment_id= or ?attachment=: the attachment's page, or a 404; null when the request names neither. */
    public function fromQuery(Request $request, Redirects $redirects): ?Resolution
    {
        if (!$request->has('attachment_id') && !$request->has('attachment')) {
            return null;
        }
        $attachment = $request->has('attachment_id')
            ? $this->posts->find((int) $request->query('attachment_id', '0'))
            : $this->posts->findByNameAnyStatus((string) $request->query('attachment'), ['attachment']);
        return $attachment !== null && $attachment->type === 'attachment' && $this->readable($attachment)
            ? $this->answer(Resolution::single($attachment), $request, $redirects)
            : Resolution::notFound();
    }

    /** An attachment asked for as a post (?p=, ?page_id=): it moves to its own page whatever the setting; null for any other post. */
    public function asPost(PostRecord $post, Request $request, string $key, Redirects $redirects): ?Resolution
    {
        if ($post->type !== 'attachment' || !$this->readable($post)) {
            return null;
        }
        $link = $this->permalinks->forAttachment($post);
        return $redirects->follows() && $this->permalinks->isPretty() && !str_contains($link, '?') ? Resolution::redirect($link . $request->queryStringWithout($key)) : Resolution::single($post);
    }

    /**
     * With attachment pages off (the default), a move to the file itself,
     * from every address, its embed and feed among them: the query along,
     * though an ?attachment_id= address leaves its id behind unless it asked
     * for an embed. With them on, the page as typed, or (the address not its
     * own) a move to its own: the other query arguments along, ?attachment=
     * among them; an embed stays where it was asked for.
     */
    public function answer(Resolution $resolution, Request $request, Redirects $redirects): Resolution
    {
        if (!$redirects->follows() || !$resolution->record instanceof PostRecord) {
            return $resolution;
        }
        if ($this->db->option('wp_attachment_pages_enabled') !== '1') {
            $file = (string) $this->posts->meta($resolution->record->id, '_wp_attached_file');
            if ($file === '') {
                return $resolution;
            }
            $query = $request->has('attachment_id') && !$request->has('embed') ? $request->queryStringWithout('attachment_id') : $request->queryStringWithout();
            return Resolution::redirect($this->permalinks->url('/wp-content/uploads/' . ltrim($file, '/')) . $query);
        }
        if ($request->has('embed')) {
            return $resolution;
        }
        $link = $this->permalinks->forAttachment($resolution->record) . $request->queryStringWithout('attachment_id');
        $typed = $this->permalinks->url($request->path) . $request->queryStringWithout();
        return $typed === $link ? $resolution : Resolution::redirect($link);
    }

    /** An attachment is readable as its parent is; a loose one as it stands. */
    private function readable(PostRecord $attachment): bool
    {
        if ($attachment->status !== 'inherit') {
            return ($this->readable)($attachment);
        }
        $parent = $attachment->parentId > 0 ? $this->posts->find($attachment->parentId) : null;
        return $attachment->parentId === 0 || ($parent !== null && ($this->readable)($parent));
    }
}
