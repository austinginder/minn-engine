<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\I18n\Gettext;
use Minn\Runtime\Handoff;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteUrls;
use Minn\Support\Escape;
use Minn\Theme\MainQueryBridge;
use Minn\Theme\Printed;

/**
 * The reference's other stand-alone scripts at the webroot, answered as it
 * answers them (contracts/front/probes.md, "Entry scripts"): the links
 * OPML and post by email load the site and nothing more; signup,
 * activation and trackback run the main query on their own address first,
 * which finds nothing, as the reference's do.
 */
final readonly class EntryScriptsController
{
    public function __construct(private Resolver $resolver, private MainQueryBridge $bridge)
    {
    }

    /** wp-links-opml.php: the site's links as OPML. */
    #[Route(Method::Any, '/wp-links-opml.php', policy: new Policy(Access::Public))]
    public function linksOpml(Request $request): Response
    {
        $charset = (string) Runtime::options()->filtered('blog_charset');
        return new Response(200, ['Content-Type' => "text/xml; charset={$charset}"], LinksOpml::page($request->query));
    }

    /**
     * wp-mail.php: post by email. Refused (403) while the configuration is
     * turned off or names no mail server; a configured POP3 server is not
     * read, so it is refused the same way (the engine carries no POP3
     * client: the open items register).
     */
    #[Route(Method::Any, '/wp-mail.php', policy: new Policy(Access::Public))]
    public function mail(Request $request): Response
    {
        Runtime::hooks()->filter('enable_post_by_email_configuration', [true]);
        Runtime::options()->filtered('mailserver_url');
        return $this->stopped(Gettext::text('This action has been disabled by the administrator.'), 403);
    }

    /** wp-signup.php and wp-activate.php: a single site sends both to registration. */
    #[Route(Method::Any, '/wp-signup.php', policy: new Policy(Access::Public))]
    #[Route(Method::Any, '/wp-activate.php', policy: new Policy(Access::Public))]
    public function signup(Request $request): Response
    {
        $this->bridge->run($this->resolver->resolve($request));
        Handoff::redirect(SiteUrls::registration());
        return RecordedRedirect::response();
    }

    /** wp-trackback.php asked directly: the address names no post, so the answer is the reference's "need an ID". */
    #[Route(Method::Any, '/wp-trackback.php', policy: new Policy(Access::Public))]
    public function trackback(Request $request): Response
    {
        $this->bridge->run($this->resolver->resolve($request));
        $charset = (string) Runtime::options()->filtered('blog_charset');
        $body = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<response>\n<error>1</error>\n<message>"
            . Escape::forXml(Gettext::text('I really need an ID for this to work.')) . "</message>\n</response>";
        return new Response(404, ['Content-Type' => "text/xml; charset={$charset}"], $body);
    }

    /** What wp_die printed for a message, under its status, where the reference exits. */
    private function stopped(string $message, int $status): Response
    {
        $level = ob_get_level();
        ob_start();
        try {
            Handoff::stop($message, '', ['response' => $status, 'exit' => false]);
        } catch (Printed) {
            // The handler ended the request the way the reference exits.
        }
        $body = '';
        while (ob_get_level() > $level) {
            $body = (string) ob_get_clean() . $body;
        }
        $code = http_response_code();
        return new Response(is_int($code) && $code >= 400 ? $code : $status, ['Content-Type' => 'text/html; charset=UTF-8'], $body);
    }
}
