<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Http\RouteMiss;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;

/**
 * A person's own settings in minn-admin/v1: their appearance, the views
 * they hid, and an administrator's reach into another person's, for the
 * user edit page. While Minn Admin runs as code, hiding is the plugin's
 * own routes': only its registry knows the surfaces, editor panels, design
 * sources and slash namespaces its adapters add.
 */
final readonly class PreferencesController
{
    public function __construct(
        private Appearance $appearance,
        private HiddenIntegrations $hiddenIntegrations,
        private Caller $caller,
    ) {
    }

    /** The caller's appearance. */
    #[Route(Method::Get, '/minn-admin/v1/me/appearance', policy: new Policy(Access::Floor))]
    public function myAppearance(Request $request): Response
    {
        return Reply::answer($request, $this->appearance->read($this->caller->id()));
    }

    /** Saves the caller's appearance. */
    #[Route(Method::Post, '/minn-admin/v1/me/appearance', policy: new Policy(Access::Floor))]
    public function saveMyAppearance(Request $request): Response
    {
        return Reply::answer($request, $this->appearance->save($this->caller->id(), $this->appearanceBody($request)));
    }

    /** A user's appearance, for one who may edit them. */
    #[Route(Method::Get, '/minn-admin/v1/users/{id:\d+}/appearance', policy: new Policy(Access::Own, 'edit_user', param: 'id', caps: ['edit_posts'], signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function userAppearance(Request $request, string $id): Response
    {
        return Reply::answer($request, $this->appearance->read($this->editableUser($id)));
    }

    /** Saves a user's appearance. */
    #[Route(Method::Post, '/minn-admin/v1/users/{id:\d+}/appearance', policy: new Policy(Access::Own, 'edit_user', param: 'id', caps: ['edit_posts'], signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function saveUserAppearance(Request $request, string $id): Response
    {
        return Reply::answer($request, $this->appearance->save($this->editableUser($id), $this->appearanceBody($request)));
    }

    /** The target user's restore list, for the user edit page. */
    #[Route(Method::Get, '/minn-admin/v1/users/{id:\d+}/hidden', policy: new Policy(Access::Own, 'edit_user', param: 'id', caps: ['edit_posts'], signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function hidden(Request $request, string $id): Response
    {
        self::leaveToPlugin();
        return Reply::answer($request, ['hidden' => $this->hiddenIntegrations->listFor($this->editableUser($id))]);
    }

    /** An administrator restores something another person hid; hiding stays that person's own choice. */
    #[Route(Method::Post, '/minn-admin/v1/users/{id:\d+}/integrations/unhide', policy: new Policy(Access::Own, 'edit_user', param: 'id', caps: ['edit_posts'], signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function unhideForUser(Request $request, string $id): Response
    {
        self::leaveToPlugin();
        $userId = $this->editableUser($id);
        $integration = $request->json()['integration'] ?? $request->query('integration');
        if (!is_string($integration) || $integration === '') {
            throw RestError::missingParams(['integration']);
        }
        $this->hiddenIntegrations->unhide($userId, HiddenIntegrations::sanitize($integration));
        return Reply::answer($request, ['ok' => true, 'hidden' => $this->hiddenIntegrations->listFor($userId)]);
    }

    /** Hides a view for the caller. */
    #[Route(Method::Post, '/minn-admin/v1/integrations/hide', policy: new Policy(Access::Floor))]
    public function hide(Request $request): Response
    {
        self::leaveToPlugin();
        $userId = $this->caller->requireFloor();
        if (!$this->hiddenIntegrations->hide($userId, $this->integrationId($request))) {
            throw new RestError('minn_unknown_integration', 'That integration is not registered.', 400);
        }
        return Reply::answer($request, $this->integrationState($userId));
    }

    /** Shows a view again for the caller. */
    #[Route(Method::Post, '/minn-admin/v1/integrations/unhide', policy: new Policy(Access::Floor))]
    public function unhide(Request $request): Response
    {
        self::leaveToPlugin();
        $userId = $this->caller->requireFloor();
        $this->hiddenIntegrations->unhide($userId, $this->integrationId($request));
        return Reply::answer($request, $this->integrationState($userId));
    }

    /**
     * Declines the route while Minn Admin runs as code, so the plugin's own
     * answers: it validates against every integration its adapters register
     * and repaints with the slices they build (the engine's registry holds
     * only the core views, and an empty answer emptied the app's nav).
     */
    private static function leaveToPlugin(): void
    {
        if (class_exists('Minn_Admin_Surfaces', false)) {
            throw new RouteMiss();
        }
    }

    private function integrationId(Request $request): string
    {
        $id = $request->json()['id'] ?? $request->query('id');
        if (!is_string($id) || $id === '') {
            throw RestError::missingParams(['id']);
        }
        return HiddenIntegrations::sanitize($id);
    }

    /**
     * The boot slices a hide or unhide repaints from when Minn Admin is not
     * running as code: no plugin surfaces, editor panels, design sources, or
     * block forms then.
     */
    private function integrationState(int $userId): array
    {
        return [
            'ok' => true,
            'surfaces' => [],
            'editorPanels' => [],
            'hidden' => $this->hiddenIntegrations->listFor($userId),
            'designs' => [],
            'editorCommands' => [],
            'blockForms' => [],
            'insertBlocks' => [],
        ];
    }

    private function appearanceBody(Request $request): array
    {
        $body = $request->json();
        return $body === [] ? $request->form : $body;
    }

    private function editableUser(string $id): int
    {
        return (int) $id;
    }
}
