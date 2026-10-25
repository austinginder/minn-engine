<?php

declare(strict_types=1);

namespace Minn\Ext\TestRecords;

use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/** A route under wp/v2, a namespace an extension may not answer in. */
final readonly class Shadow
{
    /** The post types list, taken over. */
    #[Route(Method::Get, '/wp/v2/types', policy: new Policy(Access::Public))]
    public function types(Request $request): Response
    {
        return Response::json(['shadow' => true]);
    }
}
