<?php
/**
 * The functions at the head of the plugin catalogue's queue (probe
 * plugin-queue), as the reference answers them: get_lastpostdate beside
 * get_lastpostmodified; restoring a revision, whole or some fields;
 * numeric slugs that look like date archives under three permalink
 * structures; a term's parent; update_post_cache; the untrash status
 * filter; whether the user count is large; a comment's excerpt (and one
 * behind a password); the_terms; wp_list_authors with the arguments themes
 * pass; a post's canonical URL and rel_canonical, paged and on a comment
 * page; the timezone offset override; the special options (and the option
 * calls asked to touch them); the first
 * block of a name; a theme's block template folders (and the old folder
 * names, in a theme made for a moment); and the deprecated theme getters.
 * The probe's own posts, terms and comments, removed at the end; settings
 * changed only through pre_option filters. Same protocol as api-probe.php.
 */

global $wpdb;
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$editor = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
$made = ['posts' => [], 'terms' => [], 'comments' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['comments'] as $id) {
        wp_delete_comment($id, true);
    }
    foreach ($made['posts'] as $id) {
        wp_delete_post($id, true);
    }
    foreach ($made['terms'] as $id) {
        wp_delete_term($id, 'category');
    }
});
$names = [];
$mask = static function ($value) use (&$mask, &$names) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_int($value) || (is_string($value) && ctype_digit($value))) {
        return isset($names[(int) $value]) ? '{' . $names[(int) $value] . '}' : $value;
    }
    if (is_string($value)) {
        $value = str_replace(home_url(), '{home}', $value);
        foreach ($names as $id => $label) {
            $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
        }
    }
    return $value;
};
$quiet = static fn () => true;
set_error_handler($quiet, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE);
// A post fixed at a far date, published and modified then, so the newest dates are its.
$post = static function (string $label, array $fields, array $stamp = []) use (&$made, &$names, $wpdb): int {
    $id = (int) wp_insert_post($fields + ['post_status' => 'draft', 'post_content' => "Zz {$label} body", 'post_title' => "Zz {$label}"]);
    $made['posts'][] = $id;
    $names[$id] = $label;
    if ($stamp !== []) {
        $wpdb->update($wpdb->posts, $stamp, ['ID' => $id]);
        clean_post_cache($id);
    }
    return $id;
};
$far = $post('far', ['post_author' => $editor], ['post_status' => 'publish', 'post_name' => 'zz-far', 'post_date' => '2098-01-02 03:04:05', 'post_date_gmt' => '2098-01-02 08:04:05', 'post_modified' => '2098-01-01 00:00:00', 'post_modified_gmt' => '2098-01-01 05:00:00']);
$farPage = $post('far page', ['post_type' => 'page', 'post_author' => $editor], ['post_status' => 'publish', 'post_name' => 'zz-far-page', 'post_date' => '2097-06-07 08:09:10', 'post_date_gmt' => '2097-06-07 12:09:10', 'post_modified' => '2097-06-08 00:00:00', 'post_modified_gmt' => '2097-06-08 04:00:00']);

// The newest dates.
$heard = [];
add_filter('get_lastpostdate', static function ($date, $timezone, $type) use (&$heard) {
    $heard[] = ['get_lastpostdate', $date, $timezone, $type];
    return $date;
}, 10, 3);
foreach ([['gmt', 'any'], ['blog', 'any'], ['server', 'any'], ['gmt', 'page'], ['GMT', 'post'], ['gmt', 'zz_nope'], ['bogus', 'any']] as [$zone, $type]) {
    $say("get_lastpostdate {$zone} {$type}", get_lastpostdate($zone, $type));
    $say("get_lastpostmodified {$zone} {$type}", get_lastpostmodified($zone, $type));
}
$say('get_lastpostdate filter heard', $heard);
$early = static fn () => 'zz early';
add_filter('pre_get_lastpostmodified', $early);
$say('get_lastpostmodified answered early', get_lastpostmodified('gmt'));
remove_filter('pre_get_lastpostmodified', $early);

// Revisions restored.
$draft = $post('revised', ['post_title' => 'First title', 'post_content' => 'First body', 'post_excerpt' => 'First excerpt']);
wp_update_post(['ID' => $draft, 'post_title' => 'Second title', 'post_content' => 'Second body', 'post_excerpt' => 'Second excerpt']);
wp_update_post(['ID' => $draft, 'post_title' => 'Third title', 'post_content' => 'Third body']);
$revisions = array_values(wp_get_post_revisions($draft, ['order' => 'ASC', 'orderby' => 'ID']));
foreach ($revisions as $n => $revision) {
    $names[(int) $revision->ID] = 'rev' . ($n + 1);
}
$restored = [];
add_action('wp_restore_post_revision', static function ($postId, $revisionId) use (&$restored, $mask) {
    $restored[] = $mask([(int) $postId, (int) $revisionId]);
}, 10, 2);
$state = static function (int $id) use ($mask): array {
    clean_post_cache($id);
    $p = get_post($id);
    return ['title' => $p->post_title, 'content' => $p->post_content, 'excerpt' => $p->post_excerpt, 'revisions' => count(wp_get_post_revisions($id))];
};
$say('revisions made', [count($revisions), array_map(static fn ($r) => $r->post_title, $revisions)]);
$say('wp_restore_post_revision first', $mask(wp_restore_post_revision($revisions[0]->ID)));
$say('after restoring the first', $state($draft) + ['_edit_last' => get_post_meta($draft, '_edit_last', true)]);
$say('wp_restore_post_revision second, title only', $mask(wp_restore_post_revision($revisions[1]->ID, ['post_title'])));
$say('after restoring the second title', $state($draft));
$say('wp_restore_post_revision as an object', $mask(wp_restore_post_revision($revisions[1])));
$say('wp_restore_post_revision of nothing', wp_restore_post_revision(0));
$say('wp_restore_post_revision of a post', $mask(wp_restore_post_revision($far)));
$say('wp_restore_post_revision heard', $restored);

// Numeric slugs under date-shaped permalinks.
$numeric = $post('numeric', [], ['post_status' => 'publish', 'post_name' => '1987', 'post_date' => '2001-03-11 10:00:00', 'post_date_gmt' => '2001-03-11 10:00:00']);
$month = $post('month', [], ['post_status' => 'publish', 'post_name' => '11', 'post_date' => '2001-03-11 11:00:00', 'post_date_gmt' => '2001-03-11 11:00:00']);
$pages = $post('pages', ['post_content' => 'One<!--nextpage-->Two'], ['post_status' => 'publish', 'post_name' => '1990', 'post_date' => '2001-03-12 10:00:00', 'post_date_gmt' => '2001-03-12 10:00:00']);
foreach ([
    ['/%postname%/', ['year' => '1987']],
    ['/%postname%/', ['year' => '1987', 'monthnum' => '2']],
    ['/%postname%/', ['year' => '1999']],
    ['/%postname%/', ['pagename' => 'about']],
    ['/%year%/%postname%/', ['year' => '2001', 'monthnum' => '11']],
    ['/%year%/%postname%/', ['year' => '2001', 'monthnum' => '11', 'day' => '3']],
    ['/%year%/%postname%/', ['year' => '2002', 'monthnum' => '11']],
    ['/%year%/%monthnum%/%postname%/', ['year' => '2001', 'monthnum' => '03', 'day' => '11']],
    ['/%year%/%monthnum%/%postname%/', ['year' => '2001', 'monthnum' => '04', 'day' => '11']],
    ['/%postname%/', ['year' => '1990', 'monthnum' => '2']],
    ['/%postname%/', ['year' => '1990', 'monthnum' => '3']],
    ['/%postname%/', ['year' => '1990', 'monthnum' => '1']],
    ['/archives/%post_id%', ['year' => '1987']],
] as [$structure, $vars]) {
    $hook = static fn () => $structure;
    add_filter('pre_option_permalink_structure', $hook);
    $say("wp_resolve_numeric_slug_conflicts {$structure} " . http_build_query($vars), wp_resolve_numeric_slug_conflicts($vars));
    remove_filter('pre_option_permalink_structure', $hook);
}

// A term's parent.
$parent = wp_insert_term('Zz Parent', 'category')['term_id'];
$child = wp_insert_term('Zz Child', 'category', ['parent' => $parent])['term_id'];
array_push($made['terms'], $child, $parent);
$names[(int) $parent] = 'parent';
$names[(int) $child] = 'child';
$say('wp_get_term_taxonomy_parent_id child', $mask(wp_get_term_taxonomy_parent_id($child, 'category')));
$say('wp_get_term_taxonomy_parent_id parent', wp_get_term_taxonomy_parent_id($parent, 'category'));
$say('wp_get_term_taxonomy_parent_id other taxonomy', wp_get_term_taxonomy_parent_id($child, 'post_tag'));
$say('wp_get_term_taxonomy_parent_id nothing', wp_get_term_taxonomy_parent_id(0, 'category'));

// The post cache.
$cached = clone get_post($draft);
$cached->post_title = 'Cached, never saved';
$list = [$cached];
update_post_cache($list);
$say('update_post_cache over a cached post', get_post($draft)->post_title);
clean_post_cache($draft);
update_post_cache($list);
$say('update_post_cache then get_post', get_post($draft)->post_title);
$empty = [];
$say('update_post_cache of nothing', update_post_cache($empty));
clean_post_cache($draft);
$say('after clean_post_cache', get_post($draft)->post_title);

$say('wp_untrash_post_set_previous_status', wp_untrash_post_set_previous_status('draft', $draft, 'pending'));
$say('wp_is_large_user_count', wp_is_large_user_count());
$large = static fn ($is, $count) => $count > 0;
add_filter('wp_is_large_user_count', $large, 10, 2);
$say('wp_is_large_user_count filtered', wp_is_large_user_count());
remove_filter('wp_is_large_user_count', $large, 10);

// A comment's excerpt.
$long = (int) wp_insert_comment(['comment_post_ID' => $far, 'comment_author' => 'Zz', 'comment_content' => '<p>One <strong>two</strong> three four five six seven eight nine ten eleven twelve thirteen fourteen fifteen sixteen seventeen eighteen nineteen twenty twenty-one twenty-two.</p>', 'comment_approved' => 1]);
$short = (int) wp_insert_comment(['comment_post_ID' => $far, 'comment_author' => 'Zz', 'comment_content' => 'Short &amp; sweet', 'comment_approved' => 1]);
array_push($made['comments'], $long, $short);
$say('get_comment_excerpt long', get_comment_excerpt($long));
$say('get_comment_excerpt short', get_comment_excerpt($short));
$length = static fn () => 5;
add_filter('comment_excerpt_length', $length);
$say('get_comment_excerpt five words', get_comment_excerpt($long));
remove_filter('comment_excerpt_length', $length);
$GLOBALS['comment'] = get_comment($short);
$say('get_comment_excerpt of the loop', get_comment_excerpt());
ob_start();
comment_excerpt($long);
$say('comment_excerpt', ob_get_clean());
$locked = $post('locked', [], ['post_status' => 'publish', 'post_password' => 'zz', 'post_name' => 'zz-locked']);
$hidden = (int) wp_insert_comment(['comment_post_ID' => $locked, 'comment_author' => 'Zz', 'comment_content' => 'Behind a password', 'comment_approved' => 1]);
$made['comments'][] = $hidden;
$say('get_comment_excerpt behind a password', get_comment_excerpt($hidden));

// the_terms.
wp_set_post_categories($far, [$child, $parent]);
ob_start();
$say('the_terms return', the_terms($far, 'category', 'In: ', ' | ', '.'));
$say('the_terms printed', $mask(ob_get_clean()));
ob_start();
$say('the_terms none', the_terms($far, 'post_tag', 'Tags: '));
$say('the_terms none printed', ob_get_clean());
ob_start();
$say('the_terms bad taxonomy', is_wp_error(the_terms($far, 'zz_nope')));
$say('the_terms bad taxonomy printed', ob_get_clean());

// wp_list_authors.
foreach ([
    'default' => [],
    'with counts, full names' => ['optioncount' => true, 'show_fullname' => true],
    'admins too, empty too' => ['exclude_admin' => false, 'hide_empty' => false, 'number' => 3, 'orderby' => 'name'],
    'plain list' => ['style' => 'none', 'html' => true, 'exclude_admin' => false],
    'names only' => ['html' => false, 'exclude_admin' => false],
    'with feeds' => ['exclude_admin' => false, 'feed' => 'RSS', 'feed_image' => ''],
] as $label => $args) {
    $say("wp_list_authors {$label}", $mask((string) wp_list_authors($args + ['echo' => false])));
}

// Canonical URLs.
$say('wp_get_canonical_url published', $mask(wp_get_canonical_url($far)));
$say('wp_get_canonical_url draft', wp_get_canonical_url($draft));
$say('wp_get_canonical_url nothing', wp_get_canonical_url(-1));
foreach (['queried' => [], 'queried, page 2' => ['page' => 2], 'queried, comment page 2' => ['cpage' => 2]] as $label => $vars) {
    query_posts(['p' => $far] + $vars);
    $GLOBALS['post'] = get_post($far);
    $say("wp_get_canonical_url {$label}", $mask(wp_get_canonical_url($far)));
    ob_start();
    rel_canonical();
    $say("rel_canonical {$label}", $mask(ob_get_clean()));
}
$canonical = static fn ($url, $p) => $url . '#zz-' . $p->post_name;
add_filter('get_canonical_url', $canonical, 10, 2);
$say('wp_get_canonical_url filtered', $mask(wp_get_canonical_url($far)));
remove_filter('get_canonical_url', $canonical, 10);
wp_reset_query();

// The timezone override.
foreach (['' => '', 'UTC' => 'UTC', 'Kolkata' => 'Asia/Kolkata', 'Kathmandu' => 'Asia/Kathmandu'] as $label => $zone) {
    $hook = static fn () => $zone;
    add_filter('pre_option_timezone_string', $hook);
    $say("wp_timezone_override_offset {$label}", wp_timezone_override_offset());
    remove_filter('pre_option_timezone_string', $hook);
}

// The special options.
$died = static fn () => static function ($message) {
    throw new RuntimeException(is_string($message) ? $message : 'died');
};
add_filter('wp_die_handler', $died);
foreach (['blogname', 'alloptions', 'notoptions'] as $option) {
    try {
        $say("wp_protect_special_option {$option}", wp_protect_special_option($option));
    } catch (RuntimeException $e) {
        $say("wp_protect_special_option {$option}", ['died' => $e->getMessage()]);
    }
}
foreach (['add_option', 'update_option', 'delete_option'] as $call) {
    foreach (['alloptions', 'notoptions'] as $option) {
        try {
            $say("{$call} {$option}", $call === 'delete_option' ? delete_option($option) : $call($option, 'zz'));
        } catch (RuntimeException $e) {
            $say("{$call} {$option}", ['died' => $e->getMessage()]);
        }
        $wpdb->delete($wpdb->options, ['option_name' => $option]);
    }
}
remove_filter('wp_die_handler', $died);

// The first block of a name.
$blocks = parse_blocks('<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>One</p><!-- /wp:paragraph --><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:image {"id":5} --><figure class="wp-block-image"></figure><!-- /wp:image --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group --><!-- wp:image {"id":6} --><figure class="wp-block-image"></figure><!-- /wp:image -->');
foreach (['core/image', 'core/paragraph', 'core/group', 'core/quote'] as $name) {
    $first = wp_get_first_block($blocks, $name);
    $say("wp_get_first_block {$name}", isset($first['blockName']) ? [$first['blockName'], $first['attrs'], trim($first['innerHTML'])] : $first);
}
$say('wp_get_first_block of nothing', wp_get_first_block([], 'core/image'));

// A theme's block template folders.
$say('get_block_theme_folders', get_block_theme_folders());
$say('get_block_theme_folders twentytwentyfive', get_block_theme_folders('twentytwentyfive'));
$say('get_block_theme_folders a missing theme', get_block_theme_folders('zz-no-such-theme'));
$legacy = get_theme_root() . '/zz-legacy-folders';
@mkdir($legacy . '/block-template-parts', 0755, true);
file_put_contents($legacy . '/style.css', "/*\nTheme Name: Zz Legacy Folders\n*/\n");
$say('get_block_theme_folders a theme with the old folder names', get_block_theme_folders('zz-legacy-folders'));
@unlink($legacy . '/style.css');
@rmdir($legacy . '/block-template-parts');
@rmdir($legacy);

// The deprecated theme getters.
$say('get_current_theme', get_current_theme());
$themes = get_themes();
$say('get_themes', [count($themes) === count(wp_get_themes()), array_keys($themes) === array_map(static fn ($t) => $t->get('Name'), array_values($themes)), $themes[wp_get_theme()->get('Name')]->get_stylesheet() ?? null]);
$say('get_theme current', get_theme(wp_get_theme()->get('Name'))?->get_stylesheet());
$say('get_theme missing', get_theme('Zz No Such Theme'));
restore_error_handler();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
