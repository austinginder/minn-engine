<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\RestError;

/** wp/v2/settings: read and write, both behind manage_options. */
final readonly class SettingsController
{
    public function __construct(
        private Settings $settings,
        private Caller $caller,
    ) {
    }

    /** The site settings: read, or write from the body. */
    #[Route(Method::Get, '/wp/v2/settings', policy: new Policy(Access::SignedIn, signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    #[Route(Method::Post, '/wp/v2/settings', policy: new Policy(Access::SignedIn, signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'), body: [Settings::SCHEMA])]
    #[Route(Method::Put, '/wp/v2/settings', policy: new Policy(Access::SignedIn, signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'), body: [Settings::SCHEMA])]
    #[Route(Method::Patch, '/wp/v2/settings', policy: new Policy(Access::SignedIn, signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'), body: [Settings::SCHEMA])]
    public function settings(Request $request): Response
    {
        if (!$this->caller->can('manage_options')) {
            throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
        }
        if ($request->method !== Method::Get) {
            // With plugins loaded each option goes through update_option, so they are told.
            $this->settings->store($request->json(), Runtime::booted() ? static fn (string $option, mixed $value) => \update_option($option, $value) : null);
        }
        return Reply::item($this->settings->payload(), Fields::fromQuery($request->query));
    }
}
