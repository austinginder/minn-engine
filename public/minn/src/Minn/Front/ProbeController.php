<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Content\Site;
use Minn\Content\SiteIcon;
use Minn\Cron\Cron;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Runtime\Runtime;
use Minn\Theme\MainQueryBridge;

/**
 * The surface monitors, crawlers, and hosting checks hit that is not a
 * page: robots.txt, the XML-RPC and cron endpoints, the admin entry point,
 * and the favicon. Feeds and sitemaps have controllers of their own.
 */
final readonly class ProbeController
{
    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private SiteIcon $icon,
        private ?Cron $cron = null,
        private ?MainQueryBridge $bridge = null,
    ) {
    }

    /**
     * robots.txt: with plugins loaded, the reference's (the main query a
     * robots request, then do_robots, under do_robotstxt and robots_txt);
     * otherwise the same lines, the sitemap's only on a public site.
     */
    #[Route(Method::Get, '/robots.txt', policy: new Policy(Access::Public))]
    public function robots(Request $request): Response
    {
        if (Runtime::booted() && $this->bridge !== null) {
            $printed = PrintedResponse::stand($this->bridge, ['robots' => 1], static fn () => \do_action('do_robots'));
            if ($printed !== null) {
                return $printed;
            }
        }
        $public = ($this->site->option('blog_public') ?? '1') !== '0';
        $body = "User-agent: *\nDisallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\n" . ($public ? "\nSitemap: " . $this->permalinks->url('/wp-sitemap.xml') . "\n" : '');
        return new Response(200, ['Content-Type' => 'text/plain; charset=utf-8'], $body);
    }

    /** XML-RPC is not served; GET answers the way the reference does, POST is refused outright. */
    #[Route(Method::Any, '/xmlrpc.php', policy: new Policy(Access::Public))]
    public function xmlrpc(Request $request): Response
    {
        if ($request->method === Method::Post) {
            return new Response(403, ['Content-Type' => 'text/plain;charset=UTF-8'], 'XML-RPC services are disabled on this site.');
        }
        return new Response(405, ['Content-Type' => 'text/plain;charset=UTF-8', 'Allow' => 'POST'], 'XML-RPC server accepts POST requests only.');
    }

    /** wp-cron.php: runs what is due. */
    #[Route(Method::Any, '/wp-cron.php', policy: new Policy(Access::Public))]
    public function cron(Request $request): Response
    {
        if ($this->cron !== null && !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON)) {
            $this->cron->run();
        }
        return Response::html('');
    }

    /** The admin is Minn Admin; the reference's admin path lands there. */
    #[Route(Method::Get, '/wp-admin', policy: new Policy(Access::Public))]
    #[Route(Method::Get, '/wp-admin/{rest*}', policy: new Policy(Access::Public))]
    public function admin(Request $request): Response
    {
        return Response::redirect($this->permalinks->url('/minn-admin/'), 302);
    }

    /** The site icon, or the reference's default. */
    #[Route(Method::Get, '/favicon.ico', policy: new Policy(Access::Public))]
    public function favicon(Request $request): Response
    {
        $url = $this->icon->url();
        return $url === '' ? new Response(404) : Response::redirect($url, 302);
    }
}
