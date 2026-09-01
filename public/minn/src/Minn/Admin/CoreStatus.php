<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Site;
use Minn\Support\Serialized;

/**
 * The installed version comes from the update_core transient's
 * version_checked (the database's own record of what last phoned home);
 * the engine never reads WordPress code files and never phones home
 * itself. dbUpgrade is false by definition: there is no newer core code
 * on disk for the database to lag behind.
 */
final readonly class CoreStatus
{
    public function __construct(private Site $site)
    {
    }

    /** The core version and any offer from the update transient. */
    public function data(): array
    {
        $blob = $this->site->option('_site_transient_update_core');
        $offer = null;
        if ($blob !== null && Serialized::field($blob, 'response') === 'upgrade') {
            $offer = [
                'version' => (string) Serialized::field($blob, 'current'),
                'locale' => (string) Serialized::field($blob, 'locale'),
            ];
        }
        return [
            'version' => (string) (Serialized::field($blob, 'version_checked') ?? ''),
            'dbUpgrade' => false,
            'update' => $offer,
        ];
    }
}
