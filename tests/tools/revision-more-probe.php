<?php
/**
 * revision.php's remaining functions (probe revision-more): a meta key copied
 * between posts, revisioned meta restored from a revision, a reader's autosave
 * found, the preview swapping the autosave's fields in (_set_preview) and the
 * preview link's checks (_show_post_preview), the preview's meta, thumbnail
 * and terms filters, and pre-3.6 revisions upgraded. The probe's posts are
 * removed. Same protocol as api-probe.php.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => []];
register_shutdown_function(static function () use (&$made): void {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
});
$ids = [];
$mask = static function ($value) use (&$mask, &$ids) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (is_object($value)) {
        return $mask(get_object_vars($value));
    }
    if (!is_string($value)) {
        return is_int($value) && isset($ids[$value]) ? '{' . $ids[$value] . '}' : $value;
    }
    $value = (string) preg_replace('#https?://' . preg_quote((string) parse_url(home_url(), PHP_URL_HOST), '#') . '#', '{site}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$post = static function (string $label, array $fields) use (&$made, &$ids): int {
    $id = (int) wp_insert_post($fields + ['post_title' => 'zz rm ' . $label, 'post_content' => 'x', 'post_status' => 'publish']);
    $made['posts'][] = $id;
    $ids[$id] = $label;
    return $id;
};
$fields = static fn ($p) => $p instanceof WP_Post ? array_intersect_key($p->to_array(), array_flip(['ID', 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_type', 'post_parent', 'post_name', 'post_author'])) : $p;

// _wp_copy_post_meta: every value of one key, from one post to another.
$source = $post('source', []);
$target = $post('target', []);
add_post_meta($source, 'zz_rm_key', 'one');
add_post_meta($source, 'zz_rm_key', ['two' => 2]);
add_post_meta($source, 'zz_rm_other', 'not copied');
add_post_meta($source, 'zz_rm_slashed', wp_slash('back\\slash and \\"quote\\"'));
add_post_meta($target, 'zz_rm_key', 'old target value');
$copied = _wp_copy_post_meta($source, $target, 'zz_rm_key');
_wp_copy_post_meta($source, $target, 'zz_rm_slashed');
$say('meta copied', [$copied, get_post_meta($target, 'zz_rm_key'), get_post_meta($target, 'zz_rm_other'), _wp_copy_post_meta($source, $target, 'zz_rm_missing'), get_post_meta($target, 'zz_rm_missing'), get_post_meta($source, 'zz_rm_slashed'), get_post_meta($target, 'zz_rm_slashed')]);
$say('where the revision functions are hooked', [has_action('wp_restore_post_revision', 'wp_restore_post_revision_meta'), has_action('init', '_show_post_preview'), has_filter('the_preview', '_set_preview')]);

// wp_restore_post_revision_meta: the revisioned keys of the post's type, from the revision.
register_post_meta('post', 'zz_rm_revisioned', ['single' => true, 'type' => 'string', 'revisions_enabled' => true, 'show_in_rest' => true]);
register_post_meta('post', 'zz_rm_revisioned_too', ['single' => true, 'type' => 'string', 'revisions_enabled' => true, 'show_in_rest' => true]);
$restored = $post('restored', []);
update_post_meta($restored, 'zz_rm_revisioned', 'current');
update_post_meta($restored, 'zz_rm_plain', 'current plain');
$revision = (int) _wp_put_post_revision(get_post($restored));
$ids[$revision] = 'revision';
update_metadata('post', $revision, 'zz_rm_revisioned', 'from the revision');
update_metadata('post', $revision, 'zz_rm_plain', 'plain in revision');
delete_metadata('post', $revision, 'zz_rm_revisioned_too');
update_post_meta($restored, 'zz_rm_revisioned', 'changed since');
add_post_meta($restored, 'zz_rm_revisioned', 'a second value');
update_post_meta($restored, 'zz_rm_revisioned_too', 'only on the post');
$say('revisioned meta restored', [wp_post_revision_meta_keys('post'), wp_restore_post_revision_meta($restored, $revision), get_post_meta($restored, 'zz_rm_revisioned'), get_post_meta($restored, 'zz_rm_plain'), get_post_meta($restored, 'zz_rm_revisioned_too')]);

// wp_get_post_autosave: the autosave of a post, by any author or one author.
$autosaved = $post('autosaved', ['post_author' => 1, 'post_excerpt' => 'stored excerpt']);
$say('no autosave yet', [wp_get_post_autosave($autosaved), wp_get_post_autosave($autosaved, 1)]);
$autosave = (int) _wp_put_post_revision(['ID' => $autosaved, 'post_title' => 'zz rm autosaved title', 'post_content' => 'autosaved content', 'post_excerpt' => 'autosaved excerpt', 'post_author' => 1] + get_post($autosaved, ARRAY_A), true);
$ids[$autosave] = 'autosave';
global $wpdb;
$authorBefore = get_post_field('post_author', $autosave);
$wpdb->update($wpdb->posts, ['post_author' => 1], ['ID' => $autosave]);
clean_post_cache($autosave);
$say('autosave found', $mask([$authorBefore, $fields(wp_get_post_autosave($autosaved)), $fields(wp_get_post_autosave($autosaved, 1)), wp_get_post_autosave($autosaved, 2), wp_get_post_autosave($source), wp_get_post_autosave(999999999), wp_get_post_autosave($autosaved, '1') instanceof WP_Post]));

// Two authors' autosaves: which one wp_get_post_autosave answers without an author.
$pair = $post('pair', []);
$older = (int) _wp_put_post_revision(['ID' => $pair, 'post_title' => 'zz rm pair first'] + get_post($pair, ARRAY_A), true);
$newer = (int) _wp_put_post_revision(['ID' => $pair, 'post_title' => 'zz rm pair second'] + get_post($pair, ARRAY_A), true);
$ids[$older] = 'first autosave';
$ids[$newer] = 'second autosave';
$wpdb->update($wpdb->posts, ['post_author' => 1, 'post_date' => '2021-01-01 10:00:00', 'post_date_gmt' => '2021-01-01 15:00:00', 'post_modified' => '2020-01-01 10:00:00', 'post_modified_gmt' => '2020-01-01 15:00:00'], ['ID' => $older]);
$wpdb->update($wpdb->posts, ['post_author' => 2, 'post_date' => '2020-01-01 10:00:00', 'post_date_gmt' => '2020-01-01 15:00:00', 'post_modified' => '2021-01-01 10:00:00', 'post_modified_gmt' => '2021-01-01 15:00:00'], ['ID' => $newer]);
clean_post_cache($older);
clean_post_cache($newer);
$say('two authors\' autosaves', $mask([$older !== $newer, wp_get_post_autosave($pair)->ID ?? null, wp_get_post_autosave($pair, 1)->ID ?? null, wp_get_post_autosave($pair, 2)->ID ?? null]));
$pairB = $post('pair b', []);
$firstB = (int) _wp_put_post_revision(['ID' => $pairB, 'post_title' => 'zz rm pair b first'] + get_post($pairB, ARRAY_A), true);
$secondB = (int) _wp_put_post_revision(['ID' => $pairB, 'post_title' => 'zz rm pair b second'] + get_post($pairB, ARRAY_A), true);
$ids[$firstB] = 'first autosave b';
$ids[$secondB] = 'second autosave b';
$wpdb->update($wpdb->posts, ['post_author' => 1, 'post_date' => '2020-01-01 10:00:00', 'post_date_gmt' => '2020-01-01 15:00:00', 'post_modified' => '2021-01-01 10:00:00', 'post_modified_gmt' => '2021-01-01 15:00:00'], ['ID' => $firstB]);
$wpdb->update($wpdb->posts, ['post_author' => 2, 'post_date' => '2021-01-01 10:00:00', 'post_date_gmt' => '2021-01-01 15:00:00', 'post_modified' => '2020-01-01 10:00:00', 'post_modified_gmt' => '2020-01-01 15:00:00'], ['ID' => $secondB]);
clean_post_cache($firstB);
clean_post_cache($secondB);
$say('two authors\' autosaves, dates the other way', $mask([wp_get_post_autosave($pairB)->ID ?? null]));

// _set_preview: the autosave's title, content and excerpt replace the post's (on a fresh copy each time:
// the post object it is handed is changed in place).
$fresh = static function (int $id): WP_Post {
    clean_post_cache($id);
    return get_post($id);
};
$say('preview set, nobody signed in', $mask([$fields(_set_preview($fresh($autosaved))), _set_preview(false), _set_preview('x'), has_filter('get_post_metadata', '_wp_preview_meta_filter')]));
remove_filter('get_post_metadata', '_wp_preview_meta_filter', 10);
remove_filter('get_post_metadata', '_wp_preview_post_thumbnail_filter', 10);
remove_filter('get_the_terms', '_wp_preview_terms_filter', 10);
wp_set_current_user(2);
$say('preview set, another user', $mask($fields(_set_preview($fresh($autosaved)))));
remove_filter('get_post_metadata', '_wp_preview_meta_filter', 10);
remove_filter('get_post_metadata', '_wp_preview_post_thumbnail_filter', 10);
remove_filter('get_the_terms', '_wp_preview_terms_filter', 10);
wp_set_current_user(1);
$handed = $fresh($autosaved);
$previewed = _set_preview($handed);
$say('preview set, the object handed is the one changed', [$previewed === $handed, get_post($autosaved)->post_title]);
clean_post_cache($autosaved);
$accepted = static function (string $hook, string $callback) {
    global $wp_filter;
    $priority = has_filter($hook, $callback);
    return $priority === false ? false : [$priority, $wp_filter[$hook]->callbacks[$priority][$callback]['accepted_args'] ?? null];
};
$unhook = static function (): void {
    remove_filter('get_post_metadata', '_wp_preview_meta_filter', 10);
    remove_filter('get_post_metadata', '_wp_preview_post_thumbnail_filter', 10);
    remove_filter('get_the_terms', '_wp_preview_terms_filter', 10);
};
$say('preview set, the autosave author', $mask([
    $fields($previewed),
    $accepted('get_post_metadata', '_wp_preview_meta_filter'),
    $accepted('get_post_metadata', '_wp_preview_post_thumbnail_filter'),
    $accepted('get_the_terms', '_wp_preview_terms_filter'),
]));
$unhook();
$say('preview set, a post without an autosave', $mask([$fields(_set_preview($fresh($source))), has_filter('get_post_metadata', '_wp_preview_meta_filter'), has_filter('get_the_terms', '_wp_preview_terms_filter')]));
$unhook();
$tricky = $post('tricky', ['post_author' => 1]);
$trickySave = (int) _wp_put_post_revision(['ID' => $tricky, 'post_title' => "  Spaced <b>title</b> & \"quoted\"  ", 'post_content' => "<p>Line one\r\nline two</p>\n\n<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->", 'post_excerpt' => "Excerpt\twith tab"] + get_post($tricky, ARRAY_A), true);
$ids[$trickySave] = 'tricky autosave';
$say('preview set, fields as stored', $mask([$fields(_set_preview($fresh($tricky))), get_post_field('post_title', $trickySave, 'raw')]));
$unhook();
wp_set_current_user(0);

// The preview filters, called as hooks are: outside a preview, then in one (the main query a preview,
// the global post the autosaved one).
$inPreview = static function (Closure $call, bool $preview = true) use ($autosaved) {
    global $wp_query;
    $was = [$wp_query->is_preview, $GLOBALS['post'] ?? null];
    $wp_query->is_preview = $preview;
    $GLOBALS['post'] = get_post($autosaved);
    $out = $call();
    $wp_query->is_preview = $was[0];
    $GLOBALS['post'] = $was[1];
    return $out;
};
update_metadata('post', $autosave, 'zz_rm_revisioned', 'autosave meta');
update_post_meta($autosaved, 'zz_rm_revisioned', 'post meta');
$say('preview meta filter, in a preview', $mask($inPreview(static fn () => [
    is_preview(),
    _wp_preview_meta_filter('given', $autosaved, 'zz_rm_revisioned', true),
    _wp_preview_meta_filter(null, $autosaved, 'zz_rm_revisioned', true),
    _wp_preview_meta_filter(null, $autosaved, 'zz_rm_revisioned', false),
    _wp_preview_meta_filter(null, $autosaved, 'zz_rm_plain', true),
    _wp_preview_meta_filter(null, $autosaved, '', false),
    _wp_preview_meta_filter(null, $source, 'zz_rm_revisioned', true),
    _wp_preview_meta_filter(null, $autosaved, 'zz_rm_missing_revisioned', true),
    _wp_preview_meta_filter(null, $autosaved, 'zz_rm_revisioned_too', true),
    _wp_preview_meta_filter(null, $autosaved, 'zz_rm_revisioned_too', false),
    _wp_preview_meta_filter('given', $autosaved, 'zz_rm_revisioned_too', true),
    _wp_preview_meta_filter(null, $tricky, 'zz_rm_revisioned', true),
])));
$say('preview filters, the post global but not a preview', $mask($inPreview(static function () use ($autosaved) {
    $_REQUEST['_thumbnail_id'] = '77';
    $_REQUEST['post_format'] = 'aside';
    $_REQUEST['preview_id'] = (string) $autosaved;
    $terms = _wp_preview_terms_filter(['given'], $autosaved, 'post_format');
    $out = [is_preview(), _wp_preview_meta_filter(null, $autosaved, 'zz_rm_revisioned', true), _wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id'), array_map(static fn ($t) => $t instanceof WP_Term ? $t->slug : $t, (array) $terms)];
    unset($_REQUEST['_thumbnail_id'], $_REQUEST['post_format'], $_REQUEST['preview_id']);
    return $out;
}, false)));
$say('preview meta filter, another user reading', $mask($inPreview(static function () use ($autosaved) {
    wp_set_current_user(2);
    $out = _wp_preview_meta_filter(null, $autosaved, 'zz_rm_revisioned', true);
    wp_set_current_user(0);
    return $out;
})));
$say('preview meta filter, in a preview of another post', $mask($inPreview(static function () use ($source, $autosaved) {
    $GLOBALS['post'] = get_post($source);
    return [_wp_preview_meta_filter(null, $autosaved, 'zz_rm_revisioned', true), _wp_preview_meta_filter(null, $source, 'zz_rm_revisioned', true)];
})));
$say('preview thumbnail filter, in a preview', $mask($inPreview(static function () use ($autosaved) {
    $out = [_wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id')];
    $_REQUEST['_thumbnail_id'] = '77';
    $_REQUEST['preview_id'] = (string) $autosaved;
    $out[] = _wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id');
    $out[] = _wp_preview_post_thumbnail_filter('given', $autosaved, '_thumbnail_id');
    $out[] = _wp_preview_post_thumbnail_filter(null, $autosaved, 'zz_other');
    $out[] = _wp_preview_post_thumbnail_filter(null, 123, '_thumbnail_id');
    $_REQUEST['_thumbnail_id'] = '-1';
    $out[] = _wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id');
    foreach (['abc', '0', ' 12', '12abc'] as $given) {
        $_REQUEST['_thumbnail_id'] = $given;
        $out[] = [$given, _wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id')];
    }
    $_REQUEST['_thumbnail_id'] = '77';
    $_REQUEST['preview_id'] = '123';
    $out[] = ['preview_id elsewhere', _wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id'), _wp_preview_post_thumbnail_filter(null, 123, '_thumbnail_id')];
    unset($_REQUEST['preview_id']);
    $out[] = ['no preview_id', _wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id')];
    unset($_REQUEST['_thumbnail_id'], $_REQUEST['preview_id']);
    return $out;
})));
$say('preview terms filter, in a preview', $mask($inPreview(static function () use ($autosaved) {
    $out = [_wp_preview_terms_filter(['given'], $autosaved, 'post_format')];
    $_REQUEST['post_format'] = 'aside';
    $_REQUEST['preview_id'] = (string) $autosaved;
    $terms = _wp_preview_terms_filter(['given'], $autosaved, 'post_format');
    $out[] = is_array($terms) ? array_map(static fn ($t) => $t instanceof WP_Term ? [$t->slug, $t->taxonomy, $t->name] : $t, $terms) : $terms;
    $out[] = _wp_preview_terms_filter(['given'], 123, 'post_format');
    $out[] = _wp_preview_terms_filter(['given'], $autosaved, 'category');
    $_REQUEST['post_format'] = 'standard';
    $out[] = _wp_preview_terms_filter(['given'], $autosaved, 'post_format');
    $_REQUEST['post_format'] = 'zz-not-a-format';
    $out[] = _wp_preview_terms_filter(['given'], $autosaved, 'post_format');
    $slugs = static fn ($terms) => is_array($terms) ? array_map(static fn ($t) => $t instanceof WP_Term ? $t->slug : $t, $terms) : $terms;
    $_REQUEST['post_format'] = 'Aside';
    $out[] = ['Aside', $slugs(_wp_preview_terms_filter(['given'], $autosaved, 'post_format'))];
    $_REQUEST['post_format'] = 'aside';
    $_REQUEST['preview_id'] = '123';
    $out[] = ['preview_id elsewhere', $slugs(_wp_preview_terms_filter(['given'], $autosaved, 'post_format')), $slugs(_wp_preview_terms_filter(['given'], 123, 'post_format'))];
    unset($_REQUEST['preview_id']);
    $out[] = ['no preview_id', $slugs(_wp_preview_terms_filter(['given'], $autosaved, 'post_format'))];
    $_REQUEST['post_format'] = 'gallery';
    $_REQUEST['preview_id'] = (string) $autosaved;
    $out[] = ['gallery', $slugs(_wp_preview_terms_filter(['given'], $autosaved, 'post_format')), (bool) get_term_by('slug', 'post-format-gallery', 'post_format')];
    unset($_REQUEST['post_format'], $_REQUEST['preview_id']);
    return $out;
})));
$say('preview meta filter', $mask([
    _wp_preview_meta_filter('given', $autosaved, 'zz_rm_revisioned', true),
    _wp_preview_meta_filter(null, $autosaved, '', false),
    _wp_preview_meta_filter(null, $autosaved, 'zz_rm_plain', true),
]));
$say('preview thumbnail filter', $mask([
    _wp_preview_post_thumbnail_filter('given', $autosaved, '_thumbnail_id'),
    _wp_preview_post_thumbnail_filter(null, $autosaved, 'zz_other'),
    (static function () use ($autosaved) {
        $_REQUEST['_thumbnail_id'] = '77';
        $_REQUEST['preview_id'] = (string) $autosaved;
        $out = [_wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id'), _wp_preview_post_thumbnail_filter(null, 123, '_thumbnail_id')];
        $_REQUEST['_thumbnail_id'] = '-1';
        $out[] = _wp_preview_post_thumbnail_filter(null, $autosaved, '_thumbnail_id');
        unset($_REQUEST['_thumbnail_id'], $_REQUEST['preview_id']);
        return $out;
    })(),
]));
$say('preview terms filter', $mask([
    _wp_preview_terms_filter(['given'], $autosaved, 'post_format'),
    _wp_preview_terms_filter(['given'], $autosaved, 'category'),
    (static function () use ($autosaved) {
        $_REQUEST['post_format'] = 'aside';
        $_REQUEST['preview_id'] = (string) $autosaved;
        $out = [_wp_preview_terms_filter(['given'], $autosaved, 'post_format'), _wp_preview_terms_filter(['given'], 123, 'post_format'), _wp_preview_terms_filter(['given'], $autosaved, 'category')];
        $_REQUEST['post_format'] = 'standard';
        $out[] = _wp_preview_terms_filter(['given'], $autosaved, 'post_format');
        unset($_REQUEST['post_format'], $_REQUEST['preview_id']);
        return $out;
    })(),
]));

// _show_post_preview: nothing without a preview_id; the_preview filtered with a good nonce.
$say('preview link checks', (static function () use ($autosaved) {
    $out = [];
    _show_post_preview();
    $out[] = has_filter('the_preview', '_set_preview');
    wp_set_current_user(1);
    $_GET['preview_id'] = (string) $autosaved;
    $_GET['preview_nonce'] = wp_create_nonce('post_preview_' . $autosaved);
    global $wp_filter;
    $count = static function () use (&$wp_filter): array {
        $counts = [];
        foreach ($wp_filter as $hook => $object) {
            $counts[$hook] = array_sum(array_map('count', $object->callbacks));
        }
        return $counts;
    };
    $before = $count();
    _show_post_preview();
    $after = $count();
    $changed = [];
    foreach ($after as $hook => $n) {
        if (($before[$hook] ?? 0) !== $n) {
            $changed[$hook] = $n - ($before[$hook] ?? 0);
        }
    }
    ksort($changed);
    $out[] = has_filter('the_preview', '_set_preview');
    $out[] = $changed;
    remove_filter('the_preview', '_set_preview');
    $handler = static fn () => static function ($message, $title = '', $args = []) {
        throw new RuntimeException(wp_json_encode([is_string($message) ? $message : get_class($message), $title, is_array($args) ? ($args['response'] ?? null) : null]));
    };
    add_filter('wp_die_handler', $handler);
    foreach (['bad nonce' => 'nope', 'no nonce' => null] as $case => $nonce) {
        if ($nonce === null) {
            unset($_GET['preview_nonce']);
        } else {
            $_GET['preview_nonce'] = $nonce;
        }
        try {
            _show_post_preview();
            $out[] = [$case, 'returned', has_filter('the_preview', '_set_preview')];
        } catch (RuntimeException $e) {
            $out[] = [$case, json_decode($e->getMessage(), true)];
        }
        remove_filter('the_preview', '_set_preview');
    }
    remove_filter('wp_die_handler', $handler);
    unset($_GET['preview_id'], $_GET['preview_nonce']);
    wp_set_current_user(0);
    return $out;
})());

// _wp_upgrade_revisions_of_post: revisions named before 3.6 ("{id}-revision", "{id}-autosave").
$old = $post('old revisions', []);
$oldRevision = $post('old revision', ['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $old, 'post_name' => $old . '-revision', 'post_title' => 'zz rm old revision', 'post_date' => '2012-01-01 10:00:00', 'post_date_gmt' => '2012-01-01 15:00:00']);
$oldAutosave = $post('old autosave', ['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $old, 'post_name' => $old . '-autosave', 'post_title' => 'zz rm old autosave', 'post_date' => '2012-01-02 10:00:00', 'post_date_gmt' => '2012-01-02 15:00:00']);
$wpdb->update($wpdb->posts, ['post_name' => $old . '-revision'], ['ID' => $oldRevision]);
$wpdb->update($wpdb->posts, ['post_name' => $old . '-autosave'], ['ID' => $oldAutosave]);
clean_post_cache($oldRevision);
clean_post_cache($oldAutosave);
$revisionsOf = static function (int $parent) use ($wpdb, &$made, &$ids) {
    $rows = $wpdb->get_results($wpdb->prepare("SELECT ID, post_name, post_title, post_author, post_date FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'revision' ORDER BY ID", $parent), ARRAY_A);
    foreach ($rows as $row) {
        if (!in_array((int) $row['ID'], $made['posts'], true)) {
            $made['posts'][] = (int) $row['ID'];
            $ids[(int) $row['ID']] = 'added revision';
        }
    }
    return array_map(static fn ($row) => array_diff_key($row, ['post_date' => 1]) + ['recent' => strtotime($row['post_date']) > time() - DAY_IN_SECONDS], $rows);
};
$sourceBefore = $revisionsOf($source);
$say('pre-3.6 revisions', $mask([
    get_post_field('post_name', $oldRevision),
    _wp_upgrade_revisions_of_post(get_post($old), wp_get_post_revisions($old)),
    get_post_field('post_name', $oldRevision),
    $fields(get_post($oldRevision)),
    _wp_upgrade_revisions_of_post(get_post($old), wp_get_post_revisions($old)),
    _wp_upgrade_revisions_of_post(get_post($source), []),
    $revisionsOf($old),
    $sourceBefore,
    $revisionsOf($source),
    $wpdb->get_col($wpdb->prepare("SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", '%' . $wpdb->esc_like((string) $old) . '%')),
]));

$same = $post('same revisions', ['post_excerpt' => 'same excerpt']);
$sameRevision = $post('same revision', ['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $same, 'post_title' => get_post_field('post_title', $same), 'post_content' => get_post_field('post_content', $same), 'post_excerpt' => 'same excerpt', 'post_date' => '2012-01-01 10:00:00', 'post_date_gmt' => '2012-01-01 15:00:00']);
$wpdb->update($wpdb->posts, ['post_name' => $same . '-revision'], ['ID' => $sameRevision]);
clean_post_cache($sameRevision);
$say('pre-3.6 revisions, the latest already the post', $mask([_wp_upgrade_revisions_of_post(get_post($same), wp_get_post_revisions($same)), $revisionsOf($same)]));

$modern = $post('modern revisions', []);
$modernRevision = $post('modern revision', ['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $modern, 'post_title' => 'zz rm an older title', 'post_date' => '2012-01-01 10:00:00', 'post_date_gmt' => '2012-01-01 15:00:00']);
$wpdb->update($wpdb->posts, ['post_name' => $modern . '-revision-v1'], ['ID' => $modernRevision]);
clean_post_cache($modernRevision);
$say('3.6 revisions, the latest not the post', $mask([_wp_upgrade_revisions_of_post(get_post($modern), wp_get_post_revisions($modern)), $revisionsOf($modern)]));

$mixed = $post('mixed revisions', []);
$mixedOld = $post('mixed old', ['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $mixed, 'post_title' => 'zz rm mixed old', 'post_date' => '2012-01-01 10:00:00', 'post_date_gmt' => '2012-01-01 15:00:00']);
$mixedNew = $post('mixed new', ['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $mixed, 'post_title' => 'zz rm mixed new', 'post_date' => '2013-01-01 10:00:00', 'post_date_gmt' => '2013-01-01 15:00:00']);
$wpdb->update($wpdb->posts, ['post_name' => $mixed . '-revision'], ['ID' => $mixedOld]);
$wpdb->update($wpdb->posts, ['post_name' => $mixed . '-revision-v1'], ['ID' => $mixedNew]);
clean_post_cache($mixedOld);
clean_post_cache($mixedNew);
$say('revisions, the newest upgraded and an older one not', $mask([_wp_upgrade_revisions_of_post(get_post($mixed), wp_get_post_revisions($mixed)), $revisionsOf($mixed)]));
$excerpt = $post('excerpt revisions', ['post_excerpt' => 'the post excerpt']);
$excerptRevision = $post('excerpt revision', ['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $excerpt, 'post_title' => get_post_field('post_title', $excerpt), 'post_content' => get_post_field('post_content', $excerpt), 'post_excerpt' => 'another excerpt', 'post_date' => '2012-01-01 10:00:00', 'post_date_gmt' => '2012-01-01 15:00:00']);
$wpdb->update($wpdb->posts, ['post_name' => $excerpt . '-revision'], ['ID' => $excerptRevision]);
clean_post_cache($excerptRevision);
$say('pre-3.6 revisions, the latest differs only in its excerpt', $mask([_wp_upgrade_revisions_of_post(get_post($excerpt), wp_get_post_revisions($excerpt)), $revisionsOf($excerpt)]));

unregister_post_meta('post', 'zz_rm_revisioned');
unregister_post_meta('post', 'zz_rm_revisioned_too');
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
