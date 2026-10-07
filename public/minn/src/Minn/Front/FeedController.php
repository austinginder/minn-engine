<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Blocks\Context;
use Minn\Blocks\RenderState;
use Minn\Content\Blocks;
use Minn\Content\Site;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Theme\MainQueryBridge;
use Minn\Theme\NotModified;

/**
 * The feeds, wherever a request asks for one: the feed var of the rule its
 * address matched (the site's at /feed/, the comments' at
 * /comments/feed/, a post's or an archive's after its address, a plugin's
 * own) or ?feed= on any page. Each is WordPress's feed lifecycle: the main
 * query with the feed asked for, then do_feed, whose handler prints the
 * feed and its content type. A feed of something the address names but
 * the site does not have is an empty feed, as the reference serves it.
 */
final readonly class FeedController
{
    public function __construct(
        private Site $site,
    ) {
    }

    /** Whether a request asks for a feed of what it resolved to (the query form only of something that exists). */
    public static function asked(Request $request, Resolution $resolution): bool
    {
        return ($resolution->vars['feed'] ?? '') !== '' || ($request->has('feed') && $resolution->kind !== Kind::NotFound);
    }

    /** The feed a request asks for, of what its address resolved to. */
    public function serve(Request $request, Resolution $resolution): Response
    {
        $asked = array_filter([
            'feed' => $resolution->vars['feed'] ?? $request->query('feed'),
            'withcomments' => $resolution->vars['withcomments'] ?? $request->query('withcomments'),
            'withoutcomments' => $request->query('withoutcomments'),
        ], static fn ($value) => $value !== null && $value !== '');
        return $this->served($resolution, $asked);
    }

    /**
     * A feed as the reference serves it: the main query with the feed asked
     * for (send_headers, wp and template_redirect around it), then do_feed,
     * whose handler prints the feed and its content type.
     *
     * @param array<string, mixed> $vars what the request asks of the feed: its type and its comments
     */
    private function served(Resolution $resolution, array $vars): Response
    {
        $level = ob_get_level();
        ob_start();
        try {
            $page = (new MainQueryBridge($this->perFeed()))->stand($resolution, $vars);
            // Content renders against the feed's own queried object (a category feed marks its category current), images by the page rules.
            RenderState::current()->reset();
            Blocks::renderer()->withContext(new Context($resolution, $page->posts, count($page->posts), $this->perFeed(), true));
            \do_feed();
        } catch (\Throwable $failure) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            if ($failure instanceof NotModified) {
                return new Response(304, [], '');
            }
            throw $failure;
        }
        $code = http_response_code();
        return new Response(is_int($code) && $code > 0 ? $code : 200, [], (string) ob_get_clean());
    }

    /** How many items a feed carries (posts_per_rss). */
    private function perFeed(): int
    {
        return max(1, (int) ($this->site->option('posts_per_rss') ?? 10));
    }
}
