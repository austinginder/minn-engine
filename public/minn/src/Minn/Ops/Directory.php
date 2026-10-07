<?php

declare(strict_types=1);

namespace Minn\Ops;

use Minn\Engine;
use Minn\Http;
use Minn\RestError;

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
 * least"), never the site's address.
 */
final class Directory
{
    public const ORIGIN = 'https://updates.minn.run/';
    public const BASE = self::ORIGIN . 'v1/';

    /** Where plugin, theme and translation packages download from. */
    public const PACKAGES = self::BASE . 'download/';

    /** An address on the service: a path under /v1/, such as "plugins/info/1.2/". */
    public static function url(string $path): string
    {
        return self::BASE . ltrim($path, '/');
    }

    /** How the engine introduces itself: its version, and nothing about the site. */
    public static function userAgent(): string
    {
        return 'Minn/' . (defined('MINN_ENGINE_VERSION') ? MINN_ENGINE_VERSION : '0.0.0');
    }

    /**
     * A form POST to the service (the update checks), its answer decoded.
     *
     * @param array<string, string> $form
     * @return array<string, mixed>
     */
    public static function post(string $path, array $form): array
    {
        $decoded = Http::post(self::url($path), form: $form, headers: ['X-Minn-Compat: ' . Engine::WP_VERSION], timeout: 20, hosts: [self::ORIGIN], userAgent: self::userAgent())->json();
        if (!is_array($decoded)) {
            throw new RestError('check_failed', 'The Minn update service did not answer the update check.', 502);
        }
        return $decoded;
    }
}
