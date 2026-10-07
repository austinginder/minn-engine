<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Ops\Packages;
use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Runtime\ThemeSwitch;
use Minn\Runtime\Runtime;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;

/** Adding and removing themes and extensions from the Extensions view. */
final readonly class PackagesController
{
    public function __construct(private Packages $packages, private Site $site, private Caller $caller)
    {
    }

    /** Searches the theme directory. */
    #[Route(Method::Get, '/minn-admin/v1/themes/search', policy: new Policy(Access::Floor, caps: ['install_themes']))]
    public function searchThemes(Request $request): Response
    {
        return Reply::answer($request, ['themes' => $this->packages->searchThemes(trim((string) ($request->query('q') ?? '')))]);
    }

    /** Installs a theme from the directory by slug. */
    #[Route(Method::Post, '/minn-admin/v1/themes/install', policy: new Policy(Access::Floor, caps: ['install_themes']))]
    public function installTheme(Request $request): Response
    {
        $slug = trim((string) ($request->json()['slug'] ?? ''));
        return Reply::answer($request, ['installed' => true, 'stylesheet' => $this->packages->installTheme($slug)]);
    }

    /** Installs a theme from an uploaded zip. */
    #[Route(Method::Post, '/minn-admin/v1/themes/upload', policy: new Policy(Access::Floor, caps: ['install_themes']))]
    public function uploadTheme(Request $request): Response
    {
        $result = !empty($request->form['overwrite']) ? $this->packages->unpackReplacing($this->uploaded($request, 'Theme'), 'theme') : $this->packages->unpack($this->uploaded($request, 'Theme'), 'theme');
        return Reply::answer($request, ['installed' => true, 'stylesheet' => $result['folder']]);
    }

    /** Deletes an inactive theme. */
    #[Route(Method::Post, '/minn-admin/v1/themes/delete', policy: new Policy(Access::Floor, caps: ['delete_themes']))]
    public function deleteTheme(Request $request): Response
    {
        $stylesheet = trim((string) ($request->json()['stylesheet'] ?? ''));
        if ($stylesheet === (string) ($this->site->option('stylesheet') ?? '') || $stylesheet === (string) ($this->site->option('template') ?? '')) {
            throw new RestError('theme_in_use', 'The active theme (or its parent) cannot be deleted.', 400);
        }
        // With plugins loaded, they hear it as on the reference (delete_theme, deleted_theme); a name that is no folder name is refused either way.
        if (Runtime::booted() && preg_match('/^[A-Za-z0-9_-][A-Za-z0-9._-]*$/', $stylesheet) === 1) {
            ThemeSwitch::delete($stylesheet, fn (string $folder): bool => $this->packages->remove('theme', $folder) === null);
        } else {
            $this->packages->remove('theme', $stylesheet);
        }
        return Reply::answer($request, ['deleted' => true]);
    }

    /** A plugin zip: a WordPress plugin or a Minn extension. */
    #[Route(Method::Post, '/minn-admin/v1/plugins/upload', policy: new Policy(Access::Floor, caps: ['install_plugins']))]
    public function uploadPlugin(Request $request): Response
    {
        $result = !empty($request->form['overwrite']) ? $this->packages->unpackReplacing($this->uploaded($request, 'Plugin'), 'extension') : $this->packages->unpack($this->uploaded($request, 'Plugin'), 'extension');
        return Reply::answer($request, ['installed' => true, 'plugin' => $result['folder'] . '/' . $result['folder']]);
    }

    /** A zip URL, or a GitHub owner/repo whose latest release carries a zip asset. */
    #[Route(Method::Post, '/minn-admin/v1/plugins/install-url', policy: new Policy(Access::Floor, caps: ['install_plugins']))]
    public function installFromUrl(Request $request): Response
    {
        $body = $request->json();
        $url = trim((string) ($body['url'] ?? ''));
        $github = trim((string) ($body['github'] ?? ''));
        $asset = trim((string) ($body['asset'] ?? ''));
        if ($github !== '') {
            if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $github)) {
                throw new RestError('bad_github', 'Invalid GitHub repository.', 400);
            }
            $release = json_decode($this->packages->fetch("https://api.github.com/repos/{$github}/releases/latest"), true);
            $url = '';
            foreach ((array) ($release['assets'] ?? []) as $row) {
                $name = (string) ($row['name'] ?? '');
                if (str_ends_with($name, '.zip') && ($asset === '' || $name === $asset)) {
                    $url = (string) ($row['browser_download_url'] ?? '');
                    break;
                }
            }
            if ($url === '') {
                throw new RestError('no_asset', 'The latest release carries no zip asset.', 404);
            }
        }
        if ($url === '') {
            throw new RestError('no_source', 'Provide a zip URL or a github owner/repo.', 400);
        }
        $result = $this->packages->unpack($this->packages->fetch($url), 'extension');
        return Reply::answer($request, ['installed' => true, 'plugin' => $result['folder'] . '/' . $result['folder'], 'url' => $url]);
    }

    /** Searches the plugin directory. */
    #[Route(Method::Get, '/minn-admin/v1/plugins/search', policy: new Policy(Access::Floor, caps: ['install_plugins']))]
    public function searchPlugins(Request $request): Response
    {
        return Reply::answer($request, $this->packages->searchPlugins(trim((string) ($request->query('q') ?? '')), (int) ($request->query('page') ?? 1)));
    }

    /** One directory plugin's details. */
    #[Route(Method::Get, '/minn-admin/v1/plugins/info', policy: new Policy(Access::Floor, caps: ['install_plugins']))]
    public function pluginInfo(Request $request): Response
    {
        return Reply::answer($request, $this->packages->pluginInfo(trim((string) ($request->query('slug') ?? ''))));
    }

    private function uploaded(Request $request, string $kind): string
    {
        $file = $request->files['file'] ?? null;
        if (!is_array($file) || empty($file['tmp_name']) || !is_uploaded_file((string) $file['tmp_name'])) {
            throw new RestError('no_file', 'No file uploaded.', 400);
        }
        if (!preg_match('/\.zip$/i', (string) ($file['name'] ?? ''))) {
            throw new RestError('not_zip', "{$kind} uploads must be .zip files.", 400);
        }
        return (string) file_get_contents((string) $file['tmp_name']);
    }

}
