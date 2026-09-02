<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Site;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;
use Minn\Support\FileHeaders;

/**
 * The theme inventory of minn-admin/v1: every theme on disk with its
 * headers, screenshot, and update offer, and the switch of the active one.
 */
final readonly class ThemesController
{
    public function __construct(
        private Site $site,
        private Permalinks $permalinks,
        private Updates $updates,
        private Caller $caller,
        private string $contentDir,
    ) {
    }

    /** Every theme on disk with the active one marked. */
    #[Route(Method::Get, '/minn-admin/v1/themes', policy: new Policy(Access::Floor))]
    public function themes(Request $request): Response
    {
        $this->caller->requireCap('switch_themes');
        $active = (string) ($this->site->option('stylesheet') ?? '');
        $offers = $this->caller->can('update_themes') ? $this->updates->themeOffers() : [];
        $auto = $this->updates->auto('theme');
        $items = [];
        foreach ($this->themeFolders() as $slug => $headers) {
            $items[] = [
                'stylesheet' => $slug,
                'name' => self::themeText($headers['Theme Name']),
                'version' => $headers['Version'],
                'author' => self::themeText($headers['Author']),
                'author_uri' => $headers['Author URI'],
                'theme_uri' => $headers['Theme URI'],
                'screenshot' => $this->screenshot($slug),
                'active' => $slug === $active,
                'parent' => $headers['Template'] === '' ? null : $headers['Template'],
                'on_wporg' => $this->updates->themeOnDirectory($slug),
                'network' => false,
                'update' => $offers[$slug] ?? null,
                'block' => is_file("{$this->contentDir}/themes/{$slug}/templates/index.html"),
                'auto_update' => in_array($slug, $auto, true),
            ];
        }
        usort($items, static fn (array $a, array $b): int => ($b['active'] <=> $a['active']) ?: strcasecmp($a['name'], $b['name']));
        return Reply::answer($request, ['themes' => $items, 'auto_updates' => $this->caller->can('update_themes')]);
    }

    /** Switches the active theme. */
    #[Route(Method::Post, '/minn-admin/v1/themes/activate', policy: new Policy(Access::Floor))]
    public function activateTheme(Request $request): Response
    {
        $this->caller->requireCap('switch_themes');
        $stylesheet = trim((string) ($request->json()['stylesheet'] ?? $request->form['stylesheet'] ?? ''));
        $folders = $this->themeFolders();
        if ($stylesheet === '' || !isset($folders[$stylesheet])) {
            throw new RestError('not_found', 'Theme not found.', 404);
        }
        $template = $folders[$stylesheet]['Template'];
        if ($template !== '' && !isset($folders[$template])) {
            throw new RestError('not_found', 'The parent theme is not installed.', 404);
        }
        $this->site->setOption('stylesheet', $stylesheet);
        $this->site->setOption('template', $template === '' ? $stylesheet : $template);
        $this->site->setOption('current_theme', $folders[$stylesheet]['Theme Name']);
        return Reply::answer($request, ['active' => $stylesheet]);
    }

    /** A theme header as the reference serves it: tags stripped, a bare ampersand entity-encoded. */
    private static function themeText(string $value): string
    {
        return (string) preg_replace('/&(?!(?:#\d+|#x[0-9a-f]+|[a-z][a-z0-9]*);)/i', '&amp;', strip_tags($value));
    }

    /** @return array<string, array<string, string>> slug => style.css headers */
    private function themeFolders(): array
    {
        $dir = "{$this->contentDir}/themes";
        $out = [];
        foreach (is_dir($dir) ? (scandir($dir) ?: []) : [] as $entry) {
            if ($entry[0] === '.' || !is_dir("{$dir}/{$entry}")) {
                continue;
            }
            $headers = FileHeaders::values("{$dir}/{$entry}/style.css", ['Theme Name', 'Version', 'Author', 'Author URI', 'Theme URI', 'Template']);
            if ($headers['Theme Name'] !== '') {
                $out[$entry] = $headers;
            }
        }
        return $out;
    }

    private function screenshot(string $slug): string
    {
        foreach (['png', 'jpg', 'jpeg', 'gif', 'webp'] as $ext) {
            if (is_file("{$this->contentDir}/themes/{$slug}/screenshot.{$ext}")) {
                return $this->permalinks->url("/wp-content/themes/{$slug}/screenshot.{$ext}");
            }
        }
        return '';
    }
}
