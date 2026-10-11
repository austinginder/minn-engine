<?php
/**
 * The network classes as a single site meets them (probe multisite-classes):
 * a site and a network made from their rows, their magic properties, and
 * their lookups from the object cache; a network by path answered by its
 * filter; and the site and network queries: their defaults and parsed
 * vars, answers given before them, and the SQL they build, read from the
 * query filter and swapped for one that returns nothing (a single site has
 * no network tables). The cache entries the probe adds are removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
if (!did_action('init')) {
    do_action('init');
}
// A single site never loads the network classes on the reference; they load when asked for.
foreach ([WP_Site::class => 'class-wp-site.php', WP_Network::class => 'class-wp-network.php', WP_Site_Query::class => 'class-wp-site-query.php', WP_Network_Query::class => 'class-wp-network-query.php'] as $class => $file) {
    if (!class_exists($class)) {
        require_once ABSPATH . WPINC . '/' . $file;
    }
}

$row = (object) ['blog_id' => '7', 'domain' => 'zz.example', 'path' => '/zz/', 'site_id' => '2', 'registered' => '2026-01-02 03:04:05', 'last_updated' => '2026-02-03 04:05:06', 'public' => '1', 'archived' => '0', 'mature' => '0', 'spam' => '0', 'deleted' => '0', 'lang_id' => '0'];
$site = new WP_Site($row);
$sites = [
    'made' => get_object_vars($site),
    'as an array' => $site->to_array(),
    'id and network' => [$site->id, $site->network_id, $site->blog_id, $site->site_id],
    'set' => isset($site->id, $site->network_id, $site->blog_id),
    'not set' => [isset($site->zz), isset($site->blogname), isset($site->siteurl), isset($site->post_count), isset($site->home)],
];
$site->id = '9';
$site->network_id = '5';
$sites['after setting the id and network'] = [$site->blog_id, $site->site_id, $site->id, $site->network_id];
// The details a single site cannot switch to are read from their cache.
wp_cache_set(9, (object) ['blogname' => 'ZZ site', 'siteurl' => 'https://zz.example/zz', 'post_count' => '4', 'home' => 'https://zz.example/zz'], 'site-details');
$sites['details from their cache'] = [$site->blogname, $site->siteurl, $site->post_count, $site->home];
$sites['details filtered'] = (static function () use ($site) {
    $filter = static function ($details) {
        $details->blogname .= ' (filtered)';
        return $details;
    };
    add_filter('site_details', $filter);
    $out = $site->blogname;
    remove_filter('site_details', $filter);
    return $out;
})();
wp_cache_delete(9, 'site-details');
$sites['looked up'] = [
    'not a number' => WP_Site::get_instance('zz'),
    'zero' => WP_Site::get_instance(0),
    'cached' => (static function () use ($row) {
        wp_cache_set(7, $row, 'sites');
        $found = WP_Site::get_instance('7');
        wp_cache_delete(7, 'sites');
        return $found instanceof WP_Site ? [get_class($found), $found->to_array()] : $found;
    })(),
];
$say('site', $sites);

$networkRow = (object) ['id' => '3', 'domain' => 'www.zz.example', 'path' => '/', 'blog_id' => '4', 'cookie_domain' => '', 'site_name' => ''];
$network = new WP_Network($networkRow);
$networks = [
    'made' => get_object_vars($network),
    'magic' => [$network->id, $network->site_id, $network->blog_id],
    'set' => [isset($network->id), isset($network->site_id), isset($network->blog_id), isset($network->zz)],
    'with its own cookie domain and name' => get_object_vars(new WP_Network((object) ['id' => '4', 'domain' => 'zz.example', 'path' => '/a/', 'blog_id' => '5', 'cookie_domain' => 'cookies.zz.example', 'site_name' => 'ZZ named'])),
];
$network->id = '8';
$network->site_id = '6';
$network->blog_id = '11';
$networks['after setting'] = [$network->id, $network->site_id, $network->blog_id];
$networks['looked up'] = [
    'not a number' => WP_Network::get_instance('zz'),
    'zero' => WP_Network::get_instance(0),
    'cached' => (static function () use ($networkRow) {
        wp_cache_set(3, $networkRow, 'networks');
        $found = WP_Network::get_instance(3);
        wp_cache_delete(3, 'networks');
        return $found instanceof WP_Network ? [get_class($found), get_object_vars($found), $found->id] : $found;
    })(),
];
// By path, answered by its filter with what it was asked.
$networks['by path'] = (static function () use ($networkRow) {
    $asked = [];
    $fired = [];
    // The network-by-path hooks and the network option's own; how get_option loads the autoloaded options is the option layer's.
    $listen = static function ($hook) use (&$fired) {
        if (!str_starts_with($hook, 'gettext') && !in_array($hook, ['pre_wp_load_alloptions', 'alloptions'], true)) {
            $fired[] = $hook;
        }
    };
    add_action('all', $listen);
    $segmentsCount = static fn ($segments) => $segments;
    $answer = static function ($pre, $domain, $path, $segments, $paths) use (&$asked, $networkRow) {
        $asked[] = [$domain, $path, $segments, $paths];
        return new WP_Network($networkRow);
    };
    add_filter('pre_get_network_by_path', $answer, 10, 5);
    $out = [];
    foreach ([['zz.example', '/a/b/c/', null], ['www.zz.example', '/a/b/c', 2], ['zz.example', '', 1]] as [$domain, $path, $segments]) {
        $found = WP_Network::get_by_path($domain, $path, $segments);
        $out[] = $found instanceof WP_Network ? $found->domain : $found;
    }
    remove_filter('pre_get_network_by_path', $answer, 10);
    remove_action('all', $listen);
    return [$out, $asked, array_values(array_unique($fired))];
})();
$say('network', $networks);

// The queries' SQL, read from the query filter and swapped for one that returns nothing.
$statements = [];
$swap = static function ($sql) use (&$statements) {
    if (preg_match('/\bFROM\s+(WHERE|ORDER|LIMIT|$|  )|FOUND_ROWS|^SELECT \* FROM  /i', (string) $sql) || str_contains((string) $sql, 'FROM  ')) {
        $statements[] = (string) $sql;
        return 'SELECT 1 FROM DUAL WHERE 0';
    }
    return $sql;
};
add_filter('query', $swap);
// The meta and cache updates call network functions a single site does not have, so they are off.
$run = static function (string $class, array $args) use (&$statements) {
    $statements = [];
    $args += $class === WP_Site_Query::class ? ['update_site_meta_cache' => false, 'update_site_cache' => false] : ['update_network_cache' => false];
    $fired = [];
    $hooks = ['parse_site_query', 'pre_get_sites', 'sites_pre_query', 'sites_clauses', 'found_sites_query', 'the_sites', 'parse_network_query', 'pre_get_networks', 'networks_pre_query', 'networks_clauses', 'found_networks_query', 'the_networks'];
    $listen = static function ($hook) use (&$fired, $hooks) {
        if (in_array($hook, $hooks, true)) {
            $fired[] = $hook;
        }
    };
    add_action('all', $listen);
    $query = new $class();
    $found = $query->query($args);
    remove_action('all', $listen);
    $escape = static fn ($sql) => preg_replace('/\{[0-9a-f]{64}\}/', '{escape}', (string) $sql);
    return ['found' => $found, 'statements' => array_map($escape, $statements), 'request' => $escape($query->request), 'fired' => $fired];
};
$siteCases = [
    'ids' => ['fields' => 'ids'],
    'one id' => ['fields' => 'ids', 'ID' => 5],
    'filtered' => ['fields' => 'ids', 'number' => 10, 'offset' => 5, 'network_id' => 2, 'domain__in' => ['a.zz', 'b.zz'], 'path__not_in' => ['/x/'], 'public' => 1, 'archived' => 0, 'spam' => 0, 'deleted' => 0, 'lang_id' => 3, 'orderby' => 'domain', 'order' => 'ASC'],
    'flags set' => ['fields' => 'ids', 'public' => 0, 'archived' => 1, 'mature' => '1', 'spam' => 1, 'deleted' => '1'],
    'networks' => ['fields' => 'ids', 'network__in' => [1, '2'], 'network__not_in' => [3]],
    'domains and paths' => ['fields' => 'ids', 'domain' => 'zz.example', 'domain__not_in' => ['c.zz'], 'path' => '/zz/', 'path__in' => ['/a/', '/b/']],
    'languages' => ['fields' => 'ids', 'lang__in' => [1, 2], 'lang__not_in' => [3]],
    'searched' => ['fields' => 'ids', 'search' => 'zz*', 'search_columns' => ['domain', 'path']],
    'searched, default columns' => ['fields' => 'ids', 'search' => 'zz'],
    'searched, a leading star' => ['fields' => 'ids', 'search' => '*zz', 'search_columns' => ['domain']],
    'searched, a path' => ['fields' => 'ids', 'search' => 'zz', 'search_columns' => ['path', 'zz-not-a-column']],
    'by id list' => ['fields' => 'ids', 'site__in' => [3, 1, 2], 'orderby' => 'site__in', 'no_found_rows' => false],
    'by network list' => ['fields' => 'ids', 'network__in' => [2, 1], 'orderby' => 'network__in'],
    'counted' => ['count' => true, 'site__not_in' => [1], 'mature' => 1],
    'counted, a page' => ['count' => true, 'number' => 5, 'offset' => 10],
    'ordered by several' => ['fields' => 'ids', 'orderby' => ['registered' => 'DESC', 'last_updated' => 'ASC', 'network_id' => 'DESC']],
    'no number' => ['fields' => 'ids', 'number' => 0, 'offset' => 3],
    'descending' => ['fields' => 'ids', 'order' => 'desc'],
    'a bad order' => ['fields' => 'ids', 'order' => 'zz', 'orderby' => 'path'],
    'a bad orderby' => ['fields' => 'ids', 'orderby' => 'zz'],
    'no orderby' => ['fields' => 'ids', 'orderby' => ''],
    'orderby none' => ['fields' => 'ids', 'orderby' => 'none'],
    'meta' => ['fields' => 'ids', 'meta_key' => 'zz', 'meta_value' => '1'],
    'searched, a dot' => ['fields' => 'ids', 'search' => 'zz.example'],
    'searched, a slash' => ['fields' => 'ids', 'search' => 'zz/'],
    'searched, both stars' => ['fields' => 'ids', 'search' => '*zz*'],
    'searched, like characters' => ['fields' => 'ids', 'search' => 'z_z%', 'search_columns' => ['domain']],
    'everything' => ['fields' => 'ids', 'ID' => 4, 'site__in' => [4, 5], 'site__not_in' => [6], 'network_id' => 1, 'network__in' => [1], 'network__not_in' => [2], 'domain' => 'a.zz', 'domain__in' => ['a.zz'], 'domain__not_in' => ['b.zz'], 'path' => '/', 'path__in' => ['/'], 'path__not_in' => ['/x/'], 'public' => 1, 'archived' => 0, 'mature' => 0, 'spam' => 0, 'deleted' => 0, 'lang_id' => 1, 'lang__in' => [1], 'lang__not_in' => [2], 'search' => 'zz', 'number' => 3, 'offset' => 2, 'no_found_rows' => false],
];
$siteCases['a star inside'] = ['fields' => 'ids', 'search' => 'z*z', 'search_columns' => ['domain']];
$siteCases['two columns in a string'] = ['fields' => 'ids', 'orderby' => 'domain path', 'order' => 'DESC'];
$siteCases['found rows, no number'] = ['fields' => 'ids', 'number' => 0, 'no_found_rows' => false];
foreach (['id', 'domain', 'path', 'network_id', 'last_updated', 'registered', 'domain_length', 'path_length', 'public', 'archived', 'mature', 'spam', 'deleted', 'lang_id', 'blog_id', 'site_id'] as $orderby) {
    $siteCases["ordered by {$orderby}"] = ['fields' => 'ids', 'orderby' => $orderby, 'order' => 'DESC'];
}
$siteQueries = [
    'defaults' => (new WP_Site_Query())->query_var_defaults,
    'parsed' => (static function () {
        $query = new WP_Site_Query();
        $query->parse_query(['number' => '5', 'domain' => 'zz.example', 'zz' => 'kept']);
        return $query->query_vars;
    })(),
    'made with args' => (static function () use (&$statements) {
        $statements = [];
        $query = new WP_Site_Query(['fields' => 'ids', 'update_site_meta_cache' => false, 'update_site_cache' => false]);
        return [$query->sites, count($statements)];
    })(),
];
foreach ($siteCases as $label => $args) {
    $siteQueries[$label] = $run(WP_Site_Query::class, $args);
}
$siteQueries['answered before'] = (static function () {
    $answer = static fn () => [3, 4];
    add_filter('sites_pre_query', $answer);
    $query = new WP_Site_Query();
    $found = $query->query(['fields' => 'ids', 'no_found_rows' => false, 'update_site_meta_cache' => false, 'update_site_cache' => false]);
    remove_filter('sites_pre_query', $answer);
    return [$found, $query->found_sites, $query->max_num_pages];
})();
$say('site query', $siteQueries);
$networkCases = [
    'ids' => ['fields' => 'ids'],
    'filtered' => ['fields' => 'ids', 'number' => 3, 'offset' => 1, 'domain' => 'zz.example', 'path__in' => ['/', '/a/'], 'network__not_in' => [9], 'orderby' => 'domain_length', 'order' => 'DESC'],
    'domains and paths' => ['fields' => 'ids', 'domain__in' => ['a.zz'], 'domain__not_in' => ['b.zz'], 'path' => '/', 'path__not_in' => ['/x/']],
    'searched' => ['fields' => 'ids', 'search' => 'zz'],
    'searched, stars' => ['fields' => 'ids', 'search' => '*zz*'],
    'by id list' => ['fields' => 'ids', 'network__in' => [3, 1], 'orderby' => 'network__in', 'no_found_rows' => false],
    'counted' => ['count' => true, 'network__in' => [1, 2]],
    'ordered by several' => ['fields' => 'ids', 'orderby' => ['domain' => 'ASC', 'path_length' => 'DESC']],
    'a page' => ['fields' => 'ids', 'number' => 2, 'offset' => 4],
    'an offset alone' => ['fields' => 'ids', 'offset' => 4],
    'descending' => ['fields' => 'ids', 'order' => 'desc'],
    'a bad orderby' => ['fields' => 'ids', 'orderby' => 'zz'],
    'no orderby' => ['fields' => 'ids', 'orderby' => ''],
    'everything' => ['fields' => 'ids', 'network__in' => [1, 2], 'network__not_in' => [3], 'domain' => 'a.zz', 'domain__in' => ['a.zz'], 'domain__not_in' => ['b.zz'], 'path' => '/', 'path__in' => ['/'], 'path__not_in' => ['/x/'], 'search' => 'z_z', 'number' => 5, 'no_found_rows' => false],
];
$networkCases['two columns in a string'] = ['fields' => 'ids', 'orderby' => 'domain path'];
foreach (['id', 'domain', 'path', 'domain_length', 'path_length', 'site_id', 'cookie_domain'] as $orderby) {
    $networkCases["ordered by {$orderby}"] = ['fields' => 'ids', 'orderby' => $orderby, 'order' => 'DESC'];
}
$networkQueries = [
    'defaults' => (new WP_Network_Query())->query_var_defaults,
    'parsed' => (static function () {
        $query = new WP_Network_Query();
        $query->parse_query(['number' => '2', 'zz' => 'kept']);
        return $query->query_vars;
    })(),
];
foreach ($networkCases as $label => $args) {
    $networkQueries[$label] = $run(WP_Network_Query::class, $args);
}
$networkQueries['answered before'] = (static function () {
    $answer = static fn () => [5];
    add_filter('networks_pre_query', $answer);
    $query = new WP_Network_Query();
    $found = $query->query(['fields' => 'ids', 'update_network_cache' => false, 'no_found_rows' => false]);
    remove_filter('networks_pre_query', $answer);
    return [$found, $query->found_networks, $query->max_num_pages];
})();
remove_filter('query', $swap);
$say('network query', $networkQueries);

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
