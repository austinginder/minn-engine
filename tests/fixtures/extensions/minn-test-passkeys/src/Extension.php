<?php

declare(strict_types=1);

namespace Minn\Ext\TestPasskeys;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/** REST routes over Seams::accounts()'s passkey methods, so the browser suite can make a passkey and sign in with it. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $minn->rest(new PasskeyRoutes($minn));
    }
}
