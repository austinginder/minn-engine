<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Site;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Fields;
use Minn\Rest\Reply;
use Minn\RestError;

/** Adding and removing themes and extensions from the Extensions view. */
final readonly class PackagesController
{
    public function __construct(private Packages $packages, private Site $site, private Caller $caller)
    {
    }

    #[Route(Method::Get, '/minn-admin/v1/themes/search')]
    public function searchThemes(Request $request): Response
    {
        $this->requireCap('install_themes');
        return $this->reply($request, ['themes' => $this->packages->searchThemes(trim((string) ($request->query('q') ?? '')))]);
    }

    #[Route(Method::Post, '/minn-admin/v1/themes/install')]
    public function installTheme(Request $request): Response
    {
        $this->requireCap('install_themes');
        $slug = trim((string) ($request->json()['slug'] ?? ''));
        return $this->reply($request, ['installed' => true, 'stylesheet' => $this->packages->installTheme($slug)]);
    }

    #[Route(Method::Post, '/minn-admin/v1/themes/upload')]
    public function uploadTheme(Request $request): Response
    {
        $this->requireCap('install_themes');
        $result = $this->packages->unpack($this->uploaded($request, 'Theme'), 'theme', !empty($request->form['overwrite']));
        return $this->reply($request, ['installed' => true, 'stylesheet' => $result['folder']]);
    }

    #[Route(Method::Post, '/minn-admin/v1/themes/delete')]
    public function deleteTheme(Request $request): Response
    {
        $this->requireCap('delete_themes');
        $stylesheet = trim((string) ($request->json()['stylesheet'] ?? ''));
        if ($stylesheet === (string) ($this->site->option('stylesheet') ?? '') || $stylesheet === (string) ($this->site->option('template') ?? '')) {
            throw new RestError('theme_in_use', 'The active theme (or its parent) cannot be deleted.', 400);
        }
        $this->packages->remove('theme', $stylesheet);
        return $this->reply($request, ['deleted' => true]);
    }

    /** A plugin zip: a WordPress plugin or a Minn extension. */
    #[Route(Method::Post, '/minn-admin/v1/plugins/upload')]
    public function uploadPlugin(Request $request): Response
    {
        $this->requireCap('install_plugins');
        $result = $this->packages->unpack($this->uploaded($request, 'Plugin'), 'extension', !empty($request->form['overwrite']));
        return $this->reply($request, ['installed' => true, 'plugin' => $result['folder'] . '/' . $result['folder']]);
    }

    /** A zip URL, or a GitHub owner/repo whose latest release carries a zip asset. */
    #[Route(Method::Post, '/minn-admin/v1/plugins/install-url')]
    public function installFromUrl(Request $request): Response
    {
        $this->requireCap('install_plugins');
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
        $result = $this->packages->unpack($this->packages->fetch($url), 'extension', false);
        return $this->reply($request, ['installed' => true, 'plugin' => $result['folder'] . '/' . $result['folder'], 'url' => $url]);
    }

    #[Route(Method::Get, '/minn-admin/v1/plugins/search')]
    public function searchPlugins(Request $request): Response
    {
        $this->requireCap('install_plugins');
        return $this->reply($request, $this->packages->searchPlugins(trim((string) ($request->query('q') ?? '')), (int) ($request->query('page') ?? 1)));
    }

    #[Route(Method::Get, '/minn-admin/v1/plugins/info')]
    public function pluginInfo(Request $request): Response
    {
        $this->requireCap('install_plugins');
        return $this->reply($request, $this->packages->pluginInfo(trim((string) ($request->query('slug') ?? ''))));
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

    private function requireCap(string $capability): void
    {
        $this->caller->require('rest_forbidden', 'Sorry, you are not allowed to do that.');
        if (!$this->caller->can('edit_posts') || !$this->caller->can($capability)) {
            throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
        }
    }

    private function reply(Request $request, mixed $data): Response
    {
        return Reply::item($data, Fields::fromQuery($request->query));
    }
}
