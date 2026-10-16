<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Db;
use Minn\Engine;
use Minn\Http;
use Minn\RestError;
use Minn\Runtime\Options;

/**
 * The Minn update service, https://updates.minn.run: the one place the
 * engine asks about anything it does not have yet, so a site running Minn
 * talks to Minn and never to wordpress.org. The service speaks
 * wordpress.org's own endpoints under /v1/ (plugin and theme update checks,
 * directory search and information, translations) with every address a site
 * would fetch pointed back at itself: packages under /v1/download/, icons,
 * screenshots and emoji under /v1/assets/. It also serves Minn's own
 * releases and changelogs. Every request names the engine and the WordPress
 * version it is compatible with (which judges a plugin's "Requires at
 * least"), never the site's address. A development install says "(lab)"
 * after the version, so the service counts it apart from real sites.
 */
final class Directory
{
    public const ORIGIN = 'https://updates.minn.run/';
    public const BASE = self::ORIGIN . 'v1/';

    /** Host names only a development install answers to. */
    private const LAB_HOST = '/(^localhost$|\.(localhost|test|local|example|invalid|internal|lan|home\.arpa)$)/';

    private static ?bool $lab = null;

    /** Where plugin, theme and translation packages download from. */
    public const PACKAGES = self::BASE . 'download/';

    /** An address on the service: a path under /v1/, such as "plugins/info/1.2/". */
    public static function url(string $path): string
    {
        return self::BASE . ltrim($path, '/');
    }

    /** How the engine introduces itself: its version, "(lab)" from a development install, and nothing about the site. */
    public static function userAgent(): string
    {
        self::$lab ??= self::lab(self::home(), defined('WP_ENVIRONMENT_TYPE') ? (string) WP_ENVIRONMENT_TYPE : (string) getenv('WP_ENVIRONMENT_TYPE'));
        return 'Minn/' . (defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : '0.0.0') . (self::$lab ? ' (lab)' : '');
    }

    /**
     * Whether a site is a development install: its environment type is local
     * or development, or its address names a development host (localhost, a
     * .localhost, .test, .local, .example, .invalid or .internal name, a
     * private or loopback address). Staging counts as a real site.
     */
    public static function lab(string $home, string $environment = ''): bool
    {
        if (in_array(strtolower($environment), ['local', 'development'], true)) {
            return true;
        }
        $host = strtolower((string) parse_url($home, PHP_URL_HOST));
        $host = trim($host, '[]');
        if ($host === '') {
            return false;
        }
        if (preg_match(self::LAB_HOST, $host) === 1 || $host === '::1') {
            return true;
        }
        return filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /** The site's address (wp-config's WP_HOME, else the home option), through the options store; '' when it cannot be read. */
    private static function home(): string
    {
        try {
            return (string) ((new Options(Db::current()))->get('home') ?? '');
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * A form POST to the service (the update checks) over the caller's client, its answer decoded.
     * The daily pass's checks are marked (Asking::Daily): the service counts those, one a site,
     * as the sites running Minn that day, and keeps nothing else about it.
     *
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    public static function post(Http $http, string $path, array $form, Asking $asking = Asking::Now): array
    {
        $decoded = $http->post(self::url($path), form: $form, headers: $asking->headers(['X-Minn-Compat: ' . Engine::WP_VERSION]), timeout: 20, hosts: [self::ORIGIN], userAgent: self::userAgent())->json();
        if (!is_array($decoded)) {
            throw new RestError('check_failed', 'The Minn update service did not answer the update check.', 502);
        }
        return $decoded;
    }
}
