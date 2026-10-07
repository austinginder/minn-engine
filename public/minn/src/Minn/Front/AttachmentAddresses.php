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
 * other address answers as typed; with them on, one that is not its own
 * moves there. An attachment is readable as its parent is.
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
     * The page as typed, or (attachment pages on, the address not its own)
     * a move to its own: the other query arguments along, ?attachment=
     * among them; an embed stays where it was asked for.
     */
    public function answer(Resolution $resolution, Request $request, Redirects $redirects): Resolution
    {
        if (!$redirects->follows() || $request->has('embed') || $this->db->option('wp_attachment_pages_enabled') !== '1' || !$resolution->record instanceof PostRecord) {
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
