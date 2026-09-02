<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Ops\Updates;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;

/** The minn-admin/v1 update routes: offers, directory meta, the check, the installs, the auto-update lists. */
final readonly class UpdatesController
{
    public function __construct(private Updates $updates, private Caller $caller)
    {
    }

    /** The plugin update offers. */
    #[Route(Method::Get, '/minn-admin/v1/plugin-updates', policy: new Policy(Access::SignedIn))]
    public function pluginUpdates(Request $request): Response
    {
        $this->caller->requireCap('update_plugins');
        return Reply::answer($request, [
            'updates' => (object) $this->updates->pluginOffers(),
            'themes' => (object) ($this->caller->can('update_themes') ? $this->updates->themeOffers() : []),
            'translations' => 0,
            'translationGroups' => [],
            'auto' => $this->updates->auto('plugin'),
            'autoAllowed' => true,
        ]);
    }

    /** Icons and details for the installed plugins. */
    #[Route(Method::Get, '/minn-admin/v1/plugin-meta', policy: new Policy(Access::SignedIn))]
    public function pluginMeta(Request $request): Response
    {
        $this->caller->requireCap('activate_plugins');
        return Reply::answer($request, (object) $this->updates->pluginMeta());
    }

    /** Asks wordpress.org again, now. */
    #[Route(Method::Post, '/minn-admin/v1/check-updates', policy: new Policy(Access::SignedIn))]
    public function check(Request $request): Response
    {
        if (!$this->caller->can('update_plugins') && !$this->caller->can('update_themes')) {
            throw $this->caller->refuse('rest_forbidden', 'Sorry, you are not allowed to do that.');
        }
        $this->updates->refresh();
        $plugins = $this->caller->can('update_plugins') ? $this->updates->pluginOffers() : [];
        $themes = $this->caller->can('update_themes') ? $this->updates->themeOffers() : [];
        return Reply::answer($request, [
            'ok' => true,
            'pluginUpdates' => (object) $plugins,
            'themeUpdates' => (object) $themes,
            'translations' => 0,
            'translationGroups' => [],
            'plugins' => count($plugins),
            'themes' => count($themes),
        ]);
    }

    /** Updates one plugin. */
    #[Route(Method::Post, '/minn-admin/v1/plugins/update', policy: new Policy(Access::SignedIn))]
    public function updatePlugin(Request $request): Response
    {
        $this->caller->requireCap('update_plugins');
        $file = (string) ($request->json()['plugin'] ?? '');
        if ($file === '') {
            throw RestError::missingParams(['plugin']);
        }
        if (!str_ends_with($file, '.php')) {
            $file .= '.php';
        }
        return Reply::answer($request, ['updated' => true, 'version' => $this->updates->updatePlugin($file)]);
    }

    /** Updates every plugin with an offer. */
    #[Route(Method::Post, '/minn-admin/v1/plugins/update-all', policy: new Policy(Access::SignedIn))]
    public function updateAll(Request $request): Response
    {
        $this->caller->requireCap('update_plugins');
        $this->updates->refresh();
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
        return Reply::answer($request, $updated === [] && $failed === [] ? ['updated' => []] : ['updated' => $updated, 'failed' => $failed, 'errors' => $errors]);
    }

    /** Updates one theme. */
    #[Route(Method::Post, '/minn-admin/v1/themes/update', policy: new Policy(Access::SignedIn))]
    public function updateTheme(Request $request): Response
    {
        $this->caller->requireCap('update_themes');
        $stylesheet = (string) ($request->json()['stylesheet'] ?? '');
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $stylesheet)) {
            throw new RestError('not_found', 'Theme not found.', 404);
        }
        return Reply::answer($request, ['updated' => true, 'version' => $this->updates->updateTheme($stylesheet)]);
    }

    /** Turns auto-updates on or off for one asset. */
    #[Route(Method::Post, '/minn-admin/v1/auto-updates')]
    public function autoUpdates(Request $request): Response
    {
        $body = $request->json();
        $type = (string) ($body['type'] ?? '');
        if (!in_array($type, ['plugin', 'theme'], true)) {
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): type', 400, ['params' => ['type' => 'type is not one of plugin, theme.']]);
        }
        $this->caller->require();
        $this->caller->requireCap($type === 'plugin' ? 'update_plugins' : 'update_themes');
        $asset = (string) ($body['asset'] ?? '');
        if ($asset === '') {
            throw RestError::missingParams(['asset']);
        }
        return Reply::answer($request, ['auto' => !empty($body['enabled']) ? $this->updates->enableAuto($type, $asset) : $this->updates->disableAuto($type, $asset)]);
    }

}
