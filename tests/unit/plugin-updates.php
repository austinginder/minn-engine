<?php

declare(strict_types=1);

use Minn\Runtime\PluginUpdates;

/**
 * The offers a site's own plugins publish, read from the update transient
 * the reference filters: a self-hosted plugin answers there and nowhere
 * else. Normalising that answer is pure, so it is proven here; the wiring
 * to the runtime's filter is proven live against a site that has one.
 */
$installed = ['minn-admin/minn-admin.php' => '0.36.0', 'gravityforms/gravityforms.php' => '3.1.0.4'];
$offer = (object) ['slug' => 'minn-admin', 'plugin' => 'minn-admin/minn-admin.php', 'new_version' => '0.37.0', 'package' => 'https://github.com/austinginder/minn-admin/releases/download/v0.37.0/minn-admin.zip', 'tested' => '7.1'];
$current = (object) ['slug' => 'gravityforms', 'new_version' => '3.1.0.2'];
$transient = (object) ['response' => ['minn-admin/minn-admin.php' => $offer], 'no_update' => ['gravityforms/gravityforms.php' => $current], 'checked' => $installed];

return [
    'an offer and a current answer land in their own buckets, as arrays' => static function () use ($transient, $installed): bool|string {
        $out = PluginUpdates::fromFiltered($transient, $installed);
        return $out['plugins']['minn-admin/minn-admin.php']['new_version'] === '0.37.0'
            && $out['plugins']['minn-admin/minn-admin.php']['package'] === 'https://github.com/austinginder/minn-admin/releases/download/v0.37.0/minn-admin.zip'
            && $out['no_update']['gravityforms/gravityforms.php']['new_version'] === '3.1.0.2'
            && !isset($out['no_update']['minn-admin/minn-admin.php'])
            ? true : json_encode($out);
    },
    'a plugin the site does not have is ignored' => static function () use ($installed): bool|string {
        $out = PluginUpdates::fromFiltered((object) ['response' => ['ghost/ghost.php' => (object) ['new_version' => '9.9']]], $installed);
        return $out === ['plugins' => [], 'no_update' => []] ? true : json_encode($out);
    },
    'a transient with nothing in it, or of the wrong shape, is no answer at all' => static function () use ($installed): bool|string {
        $empty = ['plugins' => [], 'no_update' => []];
        return PluginUpdates::fromFiltered((object) ['response' => [], 'no_update' => []], $installed) === $empty
            && PluginUpdates::fromFiltered(null, $installed) === $empty
            && PluginUpdates::fromFiltered(false, $installed) === $empty
            && PluginUpdates::fromFiltered((object) ['response' => 'nonsense'], $installed) === $empty
            && PluginUpdates::fromFiltered(['response' => ['minn-admin/minn-admin.php' => ['new_version' => '0.37.0']]], $installed)['plugins']['minn-admin/minn-admin.php']['new_version'] === '0.37.0'
            ? true : 'shape not handled';
    },
    'nothing is asked when the site has no plugins' => static fn (): bool|string => PluginUpdates::supplied([]) === ['plugins' => [], 'no_update' => []] ? true : 'asked anyway',
];
