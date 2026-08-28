<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/**
 * The one namespace whose handlers still live in a procedural file. The
 * method hands off to a legacy dispatcher that writes and exits on its own;
 * this class disappears when that file migrates.
 */
final readonly class LegacyRoutes
{
    #[Route(Method::Any, '/minn-admin/v1/{rest*}')]
    public function minnAdmin(Request $request): Response
    {
        minn_v1_dispatch($request->path, $request->method->value);
        throw RestError::noRoute();
    }
}
