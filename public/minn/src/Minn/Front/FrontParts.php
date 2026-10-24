<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Admin\AdminTypes;
use Minn\Admin\App;
use Minn\Admin\Appearance;
use Minn\Auth\Authenticated;
use Minn\Auth\Capabilities;
use Minn\Content\Reader;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Db;
use Minn\Extension\Loader;
use Minn\Extension\Seams;
use Minn\Http\Request;
use Minn\Rest\RestUrl;
use Minn\Rest\Types;

/**
 * What the front builds before its routes: the extensions' seams for the
 * request's reader, the Minn bar they may carry, and the admin's types and
 * appearance the bar and the shell read. Built once a request, whether the
 * runtime has booted or not (MINN_WP_API lets an extension route answer
 * without it), and the extensions register on it once.
 */
final class FrontParts
{
    private bool $registered = false;

    private function __construct(
        public readonly Seams $seams,
        public readonly Loader $loader,
        public readonly Appearance $appearance,
        public readonly AdminTypes $adminTypes,
        public readonly bool $adminOff,
        public readonly ?AdminBar $bar,
    ) {
    }

    /** The parts for one request's reader: seams carrying the bar, nothing registered on them yet. */
    public static function build(Db $db, Site $site, Request $request, Reader $reader, Capabilities $capabilities, Users $users, App $app, mixed $session, Permalinks $permalinks, string $contentDir): self
    {
        $seams = new Seams($db, $site, $request, $reader);
        $loader = new Loader($contentDir, $site);
        $appearance = new Appearance($users, $site);
        $adminTypes = new AdminTypes(new Types(new RestUrl($permalinks), $loader->declaredTypes()), $capabilities);
        $adminOff = App::switchedOff($contentDir, $site);
        $bar = $adminOff ? null : AdminBar::forReader($session instanceof Authenticated ? $session : null, $capabilities, $site, $permalinks, $app, $appearance, $adminTypes);
        // The bar is known before extensions register, so their own pages can carry it (Seams::frontBar).
        $seams->useFrontBar($bar);
        return new self($seams, $loader, $appearance, $adminTypes, $adminOff, $bar);
    }

    /** The seams with every active extension registered on them; the first call registers. */
    public function registered(): Seams
    {
        if (!$this->registered) {
            $this->registered = true;
            $this->loader->register($this->seams);
        }
        return $this->seams;
    }
}
