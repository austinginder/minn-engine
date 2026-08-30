<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Fields;
use Minn\Rest\Reply;
use Minn\RestError;

/** The minn-admin/v1 update routes: offers, directory meta, the check, the installs, the auto-update lists. */
final readonly class UpdatesController
{
    public function __construct(private Updates $updates, private Caller $caller)
    {
    }

    #[Route(Method::Get, '/minn-admin/v1/plugin-updates')]
    public function pluginUpdates(Request $request): Response
    {
        $this->requireCap('update_plugins');
        return $this->reply($request, [
            'updates' => (object) $this->updates->pluginOffers(),
            'themes' => (object) ($this->caller->can('update_themes') ? $this->updates->themeOffers() : []),
            'translations' => 0,
            'translationGroups' => [],
            'auto' => $this->updates->auto('plugin'),
            'autoAllowed' => true,
        ]);
    }

    #[Route(Method::Get, '/minn-admin/v1/plugin-meta')]
    public function pluginMeta(Request $request): Response
    {
        $this->requireCap('activate_plugins');
        return $this->reply($request, (object) $this->updates->pluginMeta());
    }

    #[Route(Method::Post, '/minn-admin/v1/check-updates')]
    public function check(Request $request): Response
    {
        $this->caller->require();
        if (!$this->caller->can('update_plugins') && !$this->caller->can('update_themes')) {
            throw $this->caller->refuse('rest_forbidden', 'Sorry, you are not allowed to do that.');
        }
        $this->updates->state(true);
        $plugins = $this->caller->can('update_plugins') ? $this->updates->pluginOffers() : [];
        $themes = $this->caller->can('update_themes') ? $this->updates->themeOffers() : [];
        return $this->reply($request, [
            'ok' => true,
            'pluginUpdates' => (object) $plugins,
            'themeUpdates' => (object) $themes,
            'translations' => 0,
            'translationGroups' => [],
            'plugins' => count($plugins),
            'themes' => count($themes),
        ]);
    }

    #[Route(Method::Post, '/minn-admin/v1/plugins/update')]
    public function updatePlugin(Request $request): Response
    {
        $this->requireCap('update_plugins');
        $file = (string) ($request->json()['plugin'] ?? '');
        if ($file === '') {
            throw RestError::missingParams(['plugin']);
        }
        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }
        return $this->reply($request, ['updated' => true, 'version' => $this->updates->updatePlugin($file)]);
    }

    #[Route(Method::Post, '/minn-admin/v1/plugins/update-all')]
    public function updateAll(Request $request): Response
    {
        $this->requireCap('update_plugins');
        $this->updates->state(true);
        $updated = [];
        $failed = [];
        $errors = [];
        foreach (array_keys($this->updates->pluginOffers()) as $file) {
            try {
                $this->updates->updatePlugin($file);
                $updated[] = $file;
            } catch (RestError $e) {
                $failed[] = $file;
                $errors[] = $e->getMessage();
            }
        }
        return $this->reply($request, $updated === [] && $failed === [] ? ['updated' => []] : ['updated' => $updated, 'failed' => $failed, 'errors' => $errors]);
    }

    #[Route(Method::Post, '/minn-admin/v1/themes/update')]
    public function updateTheme(Request $request): Response
    {
        $this->requireCap('update_themes');
        $stylesheet = (string) ($request->json()['stylesheet'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $stylesheet)) {
            throw new RestError('not_found', 'Theme not found.', 404);
        }
        return $this->reply($request, ['updated' => true, 'version' => $this->updates->updateTheme($stylesheet)]);
    }

    #[Route(Method::Post, '/minn-admin/v1/auto-updates')]
    public function autoUpdates(Request $request): Response
    {
        $body = $request->json();
        $type = (string) ($body['type'] ?? '');
        if (!in_array($type, ['plugin', 'theme'], true)) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): type', 400, ['params' => ['type' => 'type is not one of plugin, theme.']]);
        }
        $this->requireCap($type === 'plugin' ? 'update_plugins' : 'update_themes');
        $asset = (string) ($body['asset'] ?? '');
        if ($asset === '') {
            throw RestError::missingParams(['asset']);
        }
        return $this->reply($request, ['auto' => $this->updates->setAuto($type, $asset, (bool) ($body['enabled'] ?? false))]);
    }

    private function requireCap(string $capability): void
    {
        $this->caller->require();
        if (!$this->caller->can($capability)) {
            throw $this->caller->refuse('rest_forbidden', 'Sorry, you are not allowed to do that.');
        }
    }

    private function reply(Request $request, mixed $data): Response
    {
        return Reply::item($data, Fields::fromQuery($request->query));
    }
}
