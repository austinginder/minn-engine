<?php

declare(strict_types=1);

use Minn\Content\Site;
use Minn\Db;
use Minn\Runtime\Recovery;

/** Recovery names the plugin file the loader checks, wherever inside the plugin the fatal was. */
$recovery = new Recovery(new Site(new Db(new mysqli(), 'wp_')), '/site/wp-content');
$active = ['redirection/redirection.php', 'hello.php', 'woocommerce/woocommerce.php'];
return [
    'a fatal deep in a plugin blames its active file' => static fn () => $recovery->blame('/site/wp-content/plugins/redirection/models/url/url-transform.php', [], $active) === ['kind' => 'plugin', 'name' => 'redirection/redirection.php'],
    'a fatal in the main file blames the same name' => static fn () => $recovery->blame('/site/wp-content/plugins/woocommerce/woocommerce.php', [], $active) === ['kind' => 'plugin', 'name' => 'woocommerce/woocommerce.php'],
    'a single-file plugin blames itself' => static fn () => $recovery->blame('/site/wp-content/plugins/hello.php', [], $active) === ['kind' => 'plugin', 'name' => 'hello.php'],
    'a folder no active plugin claims is named alone' => static fn () => $recovery->blame('/site/wp-content/plugins/orphan/inc/x.php', [], $active) === ['kind' => 'plugin', 'name' => 'orphan'],
    'a symlinked plugin is brought home first' => static fn () => $recovery->blame('/elsewhere/redirection/api/x.php', ['/elsewhere/redirection' => '/site/wp-content/plugins/redirection'], $active) === ['kind' => 'plugin', 'name' => 'redirection/redirection.php'],
    'a theme blames its slug' => static fn () => $recovery->blame('/site/wp-content/themes/twentytwentyfive/functions.php', [], $active) === ['kind' => 'theme', 'name' => 'twentytwentyfive'],
    'the engine blames nobody' => static fn () => $recovery->blame('/site/minn/src/Minn/Db.php', [], $active) === null,
    'a failure inside engine code is the theme frame\'s that called it' => static fn () => $recovery->blameFailure('/site/minn/src/Minn/Json.php', ['/site/minn/src/Minn/Json.php', '/site/wp-content/themes/zz/functions.php']) === ['kind' => 'theme', 'name' => 'zz'],
    // Security sweep 2026-11-12 (2.2): a full disk, a deadlock or a cache backend down is the site's state.
    'a failure in the engine\'s database layer is nobody\'s, whoever wrote at boot' => static fn () => $recovery->blameFailure('/site/minn/src/Minn/Db.php', ['/site/minn/src/Minn/Runtime/Options.php', '/site/wp-content/themes/zz/functions.php']) === null,
    'a failure in the options or transients store is nobody\'s' => static fn () => $recovery->blameFailure('/site/minn/src/Minn/Runtime/Transients.php', ['/site/wp-content/themes/zz/functions.php']) === null && $recovery->blameFailure('/site/minn/src/Minn/Runtime/OptionWrites.php', ['/site/wp-content/themes/zz/functions.php']) === null,
    'a failure in a cache or database drop-in is nobody\'s' => static fn () => $recovery->blameFailure('/site/wp-content/object-cache.php', ['/site/wp-content/themes/zz/functions.php']) === null && $recovery->blameFailure('/site/wp-content/db.php', ['/site/wp-content/themes/zz/functions.php']) === null,
];
