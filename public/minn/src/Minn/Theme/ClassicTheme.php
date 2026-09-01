<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Site;

/**
 * The active classic (PHP-template) theme on disk. A theme is classic when
 * it ships no block template index; its templates are PHP files the engine
 * dispatches through the reference's hierarchy and runs via load_template().
 * The theme's own functions.php loads through the runtime's symbol gate.
 */
final readonly class ClassicTheme
{
    private function __construct(
        public string $stylesheet,
        public string $template,
        public string $stylesheetDir,
        public string $templateDir,
    ) {
    }

    /** The active classic theme, or null under a block theme. */
    public static function active(Site $site, string $themesDir): ?self
    {
        $stylesheet = (string) ($site->option('stylesheet') ?? '');
        $template = (string) ($site->option('template') ?? '');
        if ($template === '') {
            $template = $stylesheet;
        }
        foreach ([$stylesheet, $template] as $slug) {
            if ($slug === '' || preg_match('/^[A-Za-z0-9._-]+$/', $slug) !== 1 || str_contains($slug, '..')) {
                return null;
            }
        }
        // A classic theme needs an index.php in the parent; without one there
        // is nothing to dispatch and the interim template stands in.
        if (!is_file("{$themesDir}/{$template}/index.php")) {
            return null;
        }
        return new self($stylesheet, $template, "{$themesDir}/{$stylesheet}", "{$themesDir}/{$template}");
    }
}
