<?php

declare(strict_types=1);

namespace Minn\Ext\TestRecords;

use Minn\Extension\Seams;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Response;
use Minn\Http\Route;

/** A page of the extension's own that signs out on a plain GET, reachable by a navigation from another site. */
final readonly class PlayerPages
{
    public function __construct(private Seams $minn)
    {
    }

    /** Signs out, and says so. */
    #[Route(Method::Get, '/minn-test-records/sign-out/', policy: new Policy(Access::Public))]
    public function signOut(): Response
    {
        return $this->minn->accounts()->signOut(Response::html('<p>signed out</p>'));
    }
}
