<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
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

    #[Route(Method::Get, '/wp/v2/settings')]
    #[Route(Method::Post, '/wp/v2/settings')]
    #[Route(Method::Put, '/wp/v2/settings')]
    #[Route(Method::Patch, '/wp/v2/settings')]
    public function settings(Request $request): Response
    {
        $this->caller->require('rest_forbidden', 'Sorry, you are not allowed to do that.');
        if (!$this->caller->can('manage_options')) {
            throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
        }
        if ($request->method !== Method::Get) {
            $this->settings->store($request->json());
        }
        return Reply::item($this->settings->payload(), Fields::fromQuery($request->query));
    }
}
