<?php

declare(strict_types=1);

namespace Minn\Front;

/** The Minn site's header and footer parts, painted around an engine-only page with the current nav link marked. */
final readonly class SiteChrome
{
    public function __construct(private ?string $themeDir)
    {
    }

    public static function locate(string $themesDir): ?string
    {
        return is_file($themesDir . '/minn-site/style.css') ? $themesDir . '/minn-site' : null;
    }

    public function stylesheet(string $themeUri): string
    {
        return $this->themeDir === null ? '' : '<link rel="stylesheet" href="' . \Minn\Support\Html::attr($themeUri . '/style.css') . '" />' . "\n";
    }

    public function part(string $slug, string $currentPath): string
    {
        if ($this->themeDir === null) {
            return '';
        }
        $file = $this->themeDir . '/parts/' . $slug . '.html';
        if (!is_file($file)) {
            return '';
        }
        $raw = (string) preg_replace('/<!--\s*\/?wp:[^>]*-->/', '', (string) file_get_contents($file));
        if ($slug === 'header') {
            $raw = str_replace('href="' . $currentPath . '"', 'href="' . $currentPath . '" aria-current="page"', $raw);
        }
        return trim($raw) . "\n";
    }
}
