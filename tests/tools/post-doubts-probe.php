<?php
/**
 * The facade review's doubts about post.php, each measured on both stacks
 * and matched (contracts/api/facade-review.json; ~/Documents/minn-facade-review/doubts-2026-10-20).
 * Same protocol as api-probe.php; the probe's rows (zzpd) are removed after.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'users' => []];
try {
    // has_excerpt: an excerpt of "0" is none.
    $zero = $made['posts'][] = wp_insert_post(['post_title' => 'zzpd zero', 'post_excerpt' => '0', 'post_status' => 'draft']);
    $say('has_excerpt of "0", and of text', [has_excerpt($zero), has_excerpt($made['posts'][] = wp_insert_post(['post_title' => 'zzpd text', 'post_excerpt' => 'x', 'post_status' => 'draft']))]);

    // The date and time getters hand their filters the format as given; no post is false through the filter.
    $heard = [];
    foreach (['get_the_modified_date', 'get_the_modified_time', 'get_the_time'] as $filter) {
        add_filter($filter, static function ($value, $format, $post) use ($filter, &$heard) {
            $heard[] = [$filter, $value === false ? false : 'value', $format, $post === null ? null : 'post'];
            return $value;
        }, 10, 3);
    }
    get_the_modified_date('', $zero);
    get_the_modified_date('Y', $zero);
    get_the_modified_date('', 999999999);
    get_the_modified_time('', $zero);
    get_the_modified_time('', 999999999);
    get_the_time('', $zero);
    get_the_time('', 999999999);
    $say('date and time getters: what their filters are handed', $heard);

    // wp_post_revision_meta_keys: every type's keys first, then the type's own.
    register_post_meta('', 'zzpd_generic', ['revisions_enabled' => true, 'single' => true]);
    register_post_meta('post', 'zzpd_typed', ['revisions_enabled' => true, 'single' => true]);
    $say('wp_post_revision_meta_keys order', array_values(array_filter(wp_post_revision_meta_keys('post'), static fn ($k) => str_starts_with($k, 'zzpd'))));
    unregister_post_meta('', 'zzpd_generic');
    unregister_post_meta('post', 'zzpd_typed');

    // get_available_post_statuses: every status wp_count_posts names.
    register_post_type('zzpd_empty', ['public' => false]);
    $say('get_available_post_statuses of an empty type, and of pages', [get_available_post_statuses('zzpd_empty'), count(get_available_post_statuses('page'))]);
    unregister_post_type('zzpd_empty');

    $deprecated = [];
    add_action('deprecated_argument_run', static function ($function, $message, $version) use (&$deprecated) {
        $deprecated[] = [$function, $message, $version];
    }, 10, 3);
    add_filter('deprecated_argument_trigger_error', '__return_false');

    // count_user_posts: private posts count for their author or a reader who may read them.
    $author = $made['users'][] = wp_insert_user(['user_login' => 'zzpd_author', 'user_pass' => wp_generate_password(), 'role' => 'author', 'user_email' => 'zzpd_author@example.com']);
    $reader = $made['users'][] = wp_insert_user(['user_login' => 'zzpd_reader', 'user_pass' => wp_generate_password(), 'role' => 'subscriber', 'user_email' => 'zzpd_reader@example.com']);
    $made['posts'][] = wp_insert_post(['post_title' => 'zzpd public', 'post_status' => 'publish', 'post_author' => $author]);
    $made['posts'][] = wp_insert_post(['post_title' => 'zzpd private', 'post_status' => 'private', 'post_author' => $author]);
    $counts = [];
    foreach (['nobody' => 0, 'a subscriber' => $reader, 'the author' => $author, 'an administrator' => 1] as $who => $id) {
        wp_set_current_user($id);
        $counts[$who] = [count_user_posts($author), count_user_posts($author, 'post', true)];
    }
    wp_set_current_user(0);
    $say('count_user_posts, by who asks', $counts);

    // wp_get_recent_posts: a number is deprecated; objects asked for and none found is false.
    $deprecated = [];
    wp_get_recent_posts(1);
    $say('wp_get_recent_posts: a number, nothing found as objects and as arrays', [$deprecated, wp_get_recent_posts(['post_type' => 'zzpd_none'], OBJECT), wp_get_recent_posts(['post_type' => 'zzpd_none'], ARRAY_A)]);

    // get_adjacent_post: a taxonomy that does not exist is null first.
    $GLOBALS['post'] = get_post($made['posts'][2]);
    $say('get_adjacent_post with a taxonomy that does not exist', [get_adjacent_post(false, '', true, 'zzpd_none'), get_adjacent_post(true, '', false, 'zzpd_none')]);

    // the_author: its deprecated arguments, and echo off prints nothing.
    $deprecated = [];
    ob_start();
    the_author('x', false);
    $printed = ob_get_clean();
    $say('the_author with its deprecated arguments', [$deprecated, $printed]);

    // use_block_editor_for_post and _post_type: false before the filter for no post, no type, no editor.
    $asked = [];
    $hear = static function ($use, $what) use (&$asked) {
        $asked[] = [current_filter(), is_object($what) ? 'post' : $what];
        return $use;
    };
    add_filter('use_block_editor_for_post', $hear, 10, 2);
    add_filter('use_block_editor_for_post_type', $hear, 10, 2);
    register_post_type('zzpd_noeditor', ['public' => true, 'show_in_rest' => true, 'supports' => ['title']]);
    register_post_type('zzpd_norest', ['public' => true, 'show_in_rest' => false]);
    $say('use_block_editor_for_post*: answers, and the filters asked', [[use_block_editor_for_post(999999999), use_block_editor_for_post_type('zzpd_noeditor'), use_block_editor_for_post_type('zzpd_norest'), use_block_editor_for_post_type('zzpd_unknown'), use_block_editor_for_post_type('post')], $asked]);
    remove_filter('use_block_editor_for_post', $hear, 10);
    remove_filter('use_block_editor_for_post_type', $hear, 10);

    // get_post_types_by_support: types holding features whether registered or not, the operator in any case.
    add_post_type_support('zzpd:pseudo', 'zzpd_feature');
    add_post_type_support('zzpd_noeditor', 'zzpd_feature');
    $say('get_post_types_by_support', [get_post_types_by_support('zzpd_feature'), get_post_types_by_support(['zzpd_feature', 'title'], 'OR'), in_array('attachment:audio', get_post_types_by_support('thumbnail'), true), in_array('zzpd_norest', get_post_types_by_support('zzpd_feature', 'NOT'), true)]);
    remove_post_type_support('zzpd:pseudo', 'zzpd_feature');

    // get_delete_post_link: the type's own edit address; the deprecated argument noticed.
    register_post_type('zzpd_edit', ['public' => true, '_edit_link' => 'zzedit.php?thing=%d']);
    $edited = $made['posts'][] = wp_insert_post(['post_type' => 'zzpd_edit', 'post_title' => 'zzpd edit', 'post_status' => 'publish']);
    wp_set_current_user(1);
    $deprecated = [];
    $link = (string) get_delete_post_link($edited, 'old');
    $say('get_delete_post_link of a type with its own edit address', [preg_replace(['/post=\d+|thing=\d+/', '/_wpnonce=[0-9a-f]+/', '#^https?://[^/]+#'], ['{id}', '_wpnonce={nonce}', ''], $link), $deprecated]);
    wp_set_current_user(0);
    foreach (['zzpd_noeditor', 'zzpd_norest', 'zzpd_edit'] as $type) {
        unregister_post_type($type);
    }

    // get_page_by_title: of three pages of one title, the oldest-dated.
    foreach (['2021-03-03 10:00:00', '2019-01-01 10:00:00', '2020-02-02 10:00:00'] as $i => $date) {
        $made['posts'][] = wp_insert_post(['post_type' => 'page', 'post_title' => 'zzpd same title', 'post_status' => 'publish', 'post_date' => $date]);
    }
    $found = @get_page_by_title('zzpd same title');
    $say('get_page_by_title picks the oldest-dated', $found ? $found->post_date : null);

    // get_post_class: given classes escaped, not cut down to class characters.
    $say('get_post_class keeps given characters, escaped', array_values(array_filter(get_post_class(['zz:colon', 'zz.dot', 'zz/slash', 'zz"q<b>'], $zero), static fn ($c) => str_starts_with($c, 'zz'))));

    // get_body_class: escaped before the filter, a filter's classes as it gave them.
    $add = static fn (array $classes): array => [...$classes, 'zz"q<b>'];
    add_filter('body_class', $add);
    $say('get_body_class: a given class and a filter\'s', array_values(array_filter(get_body_class('zz"given'), static fn ($c) => str_starts_with($c, 'zz'))));
    remove_filter('body_class', $add);

    // The old date and slug kept: a draft published, and an attachment.
    $draft = $made['posts'][] = wp_insert_post(['post_title' => 'zzpd draft', 'post_name' => 'zzpd-draft', 'post_status' => 'draft', 'post_date' => '2020-05-05 10:00:00']);
    wp_update_post(['ID' => $draft, 'post_status' => 'publish', 'post_date' => '2021-06-06 10:00:00', 'post_name' => 'zzpd-draft-moved']);
    $attachment = $made['posts'][] = wp_insert_post(['post_type' => 'attachment', 'post_title' => 'zzpd file', 'post_name' => 'zzpd-file', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'post_date' => '2020-05-05 10:00:00']);
    wp_update_post(['ID' => $attachment, 'post_name' => 'zzpd-file-moved', 'post_date' => '2021-06-06 10:00:00']);
    $say('old dates and slugs kept: a draft published, an attachment', [get_post_meta($draft, '_wp_old_date'), get_post_meta($draft, '_wp_old_slug'), get_post_meta($attachment, '_wp_old_date'), get_post_meta($attachment, '_wp_old_slug')]);

    // wp_is_post_autosave: an autosave of its own parent only.
    $parent = $made['posts'][] = wp_insert_post(['post_title' => 'zzpd parent', 'post_status' => 'publish']);
    $named = static fn (string $name) => wp_insert_post(['post_type' => 'revision', 'post_status' => 'inherit', 'post_parent' => $parent, 'post_name' => str_replace('{parent}', (string) $parent, $name), 'post_title' => 'zzpd rev']);
    $autosaves = [];
    foreach (['{parent}-autosave-v1', '999999-autosave-v1', '{parent}-autosavexyz', '123-autosavexyz', '{parent}-revision-v1'] as $name) {
        $revision = $made['posts'][] = $named($name);
        $answer = wp_is_post_autosave($revision);
        $autosaves[str_replace((string) $parent, '{parent}', $name)] = $answer === false ? false : ($answer === $parent ? 'parent' : $answer);
    }
    $say('wp_is_post_autosave by name', $autosaves);

    // _wp_put_post_revision: no ID, and a revision of a revision, are errors.
    $error = static fn ($result) => is_wp_error($result) ? [$result->get_error_code(), $result->get_error_message()] : (is_int($result) ? 'id' : $result);
    $say('_wp_put_post_revision refusals', [$error(_wp_put_post_revision(['post_title' => 'zzpd'])), $error(_wp_put_post_revision(get_post($made['posts'][count($made['posts']) - 1])))]);

    // wp_get_post_revision leaves the caller's variable alone.
    $id = $made['posts'][count($made['posts']) - 1];
    $kept = $id;
    wp_get_post_revision($kept);
    $missing = 999999999;
    wp_get_post_revision($missing);
    $say('wp_get_post_revision: the variable handed in, after', [$kept === $id, $missing]);

    // wp_save_post_revision saves on post_updated when wp_save_post_revision_on_insert is gone.
    $revised = $made['posts'][] = wp_insert_post(['post_title' => 'zzpd revised', 'post_status' => 'publish', 'post_content' => 'one']);
    remove_action('wp_after_insert_post', 'wp_save_post_revision_on_insert', 9);
    wp_update_post(['ID' => $revised, 'post_content' => 'two']);
    wp_update_post(['ID' => $revised, 'post_content' => 'three']);
    add_action('wp_after_insert_post', 'wp_save_post_revision_on_insert', 9, 3);
    $say('revisions with wp_save_post_revision_on_insert unhooked', count(wp_get_post_revisions($revised)));

    // _get_custom_object_labels writes the derived labels back onto the object; get_post_type_labels restores what a filter took.
    $defaults = ['name' => ['Posts', 'Pages'], 'singular_name' => ['Post', 'Page'], 'menu_name' => ['M1', 'M2'], 'all_items' => ['A1', 'A2'], 'archives' => ['R1', 'R2'], 'name_admin_bar' => ['B1', 'B2']];
    $labelled = [];
    foreach (['a label' => (object) ['name' => 'zzt', 'label' => 'Zz Things'], 'a singular' => (object) ['name' => 'zzt', 'labels' => ['singular_name' => 'Thing']], 'a menu' => (object) ['name' => 'zzt', 'label' => 'L', 'labels' => ['menu_name' => 'Menu']], 'nothing' => (object) ['name' => 'zzt']] as $case => $object) {
        $answer = _get_custom_object_labels($object, $defaults);
        $labelled[$case] = [gettype($object->labels), (array) $object->labels, (array) $answer];
    }
    $say('_get_custom_object_labels: written back, and answered', $labelled);
    $takeAway = static function ($labels) {
        unset($labels->name, $labels->add_new_item);
        return $labels;
    };
    register_post_type('zzpd_labels', ['label' => 'Zz Labels']);
    add_filter('post_type_labels_zzpd_labels', $takeAway);
    $labels = get_post_type_labels(get_post_type_object('zzpd_labels'));
    remove_filter('post_type_labels_zzpd_labels', $takeAway);
    unregister_post_type('zzpd_labels');
    $say('get_post_type_labels after a filter took two', [count((array) $labels), $labels->name ?? null, $labels->add_new_item ?? null]);

    // clean_attachment_cache lets the post and its meta go, unless invalidation is suspended.
    wp_cache_set(999999991, 'zzpd', 'posts');
    wp_cache_set(999999991, 'zzpd', 'post_meta');
    wp_suspend_cache_invalidation(true);
    clean_attachment_cache(999999991);
    $suspended = [wp_cache_get(999999991, 'posts'), wp_cache_get(999999991, 'post_meta')];
    wp_suspend_cache_invalidation(false);
    clean_attachment_cache(999999991);
    $say('clean_attachment_cache, suspended and not', [$suspended, wp_cache_get(999999991, 'posts'), wp_cache_get(999999991, 'post_meta')]);

    // get_post_datetime answers in the site's timezone, a GMT field included.
    $zone = static fn (): string => 'America/New_York';
    add_filter('pre_option_timezone_string', $zone);
    $dated = $made['posts'][] = wp_insert_post(['post_title' => 'zzpd dated', 'post_status' => 'publish', 'post_date' => '2024-01-15 19:00:00']);
    $times = [];
    foreach ([['date', 'local'], ['date', 'gmt'], ['modified', 'gmt']] as [$field, $source]) {
        $time = get_post_datetime($dated, $field, $source);
        $times["{$field} {$source}"] = $time ? [$time->getTimezone()->getName(), $field === 'date' ? $time->format('Y-m-d H:i') : 'set'] : $time;
    }
    remove_filter('pre_option_timezone_string', $zone);
    $say('get_post_datetime zones', $times);

    // get_the_excerpt's protected sentence is translated.
    $translate = static fn ($translation, $text) => $text === 'There is no excerpt because this is a protected post.' ? 'ZZ ' . $text : $translation;
    add_filter('gettext', $translate, 10, 2);
    $say('get_the_excerpt of a protected post, translated', get_the_excerpt($made['posts'][] = wp_insert_post(['post_title' => 'zzpd locked', 'post_status' => 'publish', 'post_password' => 'pw'])));
    remove_filter('gettext', $translate, 10);

    // A template's slug a filter chose is made unique within the theme too.
    $template = $made['posts'][] = wp_insert_post(['post_type' => 'wp_template', 'post_title' => 'zzpd template', 'post_name' => 'zzpd-chosen', 'post_status' => 'publish', 'tax_input' => ['wp_theme' => [get_stylesheet()]]]);
    wp_set_object_terms($template, get_stylesheet(), 'wp_theme');
    $say('wp_filter_wp_template_unique_post_slug: an override that is taken', wp_filter_wp_template_unique_post_slug('zzpd-chosen', 'zzpd-other', 0, 'publish', 'wp_template'));

    // post_exists: each part given matched; any type when none is named; the title as the save leaves it.
    $public = $made['posts'][] = wp_insert_post(['post_title' => 'zzpd exists', 'post_content' => 'zzpd body', 'post_status' => 'publish', 'post_date' => '2020-01-02 03:04:05']);
    $block = $made['posts'][] = wp_insert_post(['post_type' => 'wp_block', 'post_title' => 'zzpd pattern', 'post_status' => 'publish']);
    $which = static fn ($id) => $id === $public ? 'post' : ($id === $block ? 'pattern' : $id);
    $exists = [];
    foreach ([['zzpd exists', 'wrong'], ['zzpd exists', '', '2020-01-02'], ['zzpd exists', '', '', '', 'draft'], ['zzpd pattern'], ["zzpd exists\\"], [' zzpd exists'], ['zzpd exists', 'zzpd body', '2020-01-02 03:04:05', 'post', 'publish']] as $args) {
        $exists[json_encode($args)] = $which(post_exists(...$args));
    }
    $say('post_exists', $exists);

    // A loop already among a new post's ancestors is broken.
    global $wpdb;
    $a = $made['posts'][] = wp_insert_post(['post_type' => 'page', 'post_title' => 'zzpd A', 'post_status' => 'publish']);
    $b = $made['posts'][] = wp_insert_post(['post_type' => 'page', 'post_title' => 'zzpd B', 'post_status' => 'publish', 'post_parent' => $a]);
    $wpdb->update($wpdb->posts, ['post_parent' => $b], ['ID' => $a]);
    clean_post_cache($a);
    $c = $made['posts'][] = wp_insert_post(['post_type' => 'page', 'post_title' => 'zzpd C', 'post_status' => 'publish']);
    $answer = wp_check_post_hierarchy_for_loops($a, $c);
    clean_post_cache($a);
    clean_post_cache($b);
    $say('a loop among the ancestors: the answer, then A and B\'s parents', [$answer === $a, (int) get_post($a)->post_parent, (int) get_post($b)->post_parent]);

    // wp_count_posts "readable": a reader who may not read others' private posts counts only their own.
    $made['posts'][] = wp_insert_post(['post_type' => 'page', 'post_title' => 'zzpd private page', 'post_status' => 'private', 'post_author' => 1]);
    $readable = [];
    foreach (['nobody' => 0, 'a subscriber' => $reader, 'an administrator' => 1] as $who => $id) {
        wp_set_current_user($id);
        $readable[$who] = (int) wp_count_posts('page', 'readable')->private - (int) wp_count_posts('page')->private;
    }
    wp_set_current_user(0);
    $say('wp_count_posts readable: private posts left out, by who asks', $readable);

    // _wp_link_page: a static front page's pages go by the pagination base; a published post's preview keeps its id and nonce.
    $front = $made['posts'][] = wp_insert_post(['post_type' => 'page', 'post_title' => 'zzpd front', 'post_status' => 'publish', 'post_content' => "one<!--nextpage-->two"]);
    $onFront = static fn () => 'page';
    $frontId = static fn () => $front;
    add_filter('pre_option_show_on_front', $onFront);
    add_filter('pre_option_page_on_front', $frontId);
    $GLOBALS['post'] = get_post($front);
    $site = static fn (string $html): string => (string) preg_replace(['#https?://[^/"]+#', '/p=\\d+|page_id=\\d+|preview_id=\\d+/'], ['{site}', '{id}'], $html);
    $say('_wp_link_page of a static front page', $site(_wp_link_page(2)));
    remove_filter('pre_option_show_on_front', $onFront);
    remove_filter('pre_option_page_on_front', $frontId);
    $GLOBALS['wp_query']->is_preview = true;
    $_GET['preview_id'] = (string) $front;
    $_GET['preview_nonce'] = 'zznonce';
    $say('_wp_link_page in a published post\'s preview', $site(_wp_link_page(2)));
    unset($_GET['preview_id'], $_GET['preview_nonce']);
    $GLOBALS['wp_query']->is_preview = false;

    // prepend_attachment: a video with a featured image shows it as the poster.
    $video = $made['posts'][] = wp_insert_post(['post_type' => 'attachment', 'post_title' => 'zzpd video', 'post_status' => 'inherit', 'post_mime_type' => 'video/mp4', 'guid' => 'https://example.com/zzpd.mp4']);
    update_post_meta($video, '_wp_attached_file', 'zzpd.mp4');
    $poster = $made['posts'][] = wp_insert_post(['post_type' => 'attachment', 'post_title' => 'zzpd poster', 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg']);
    update_post_meta($poster, '_wp_attached_file', 'zzpd-poster.jpg');
    add_post_type_support('attachment:video', 'thumbnail');
    update_post_meta($video, '_thumbnail_id', $poster);
    $GLOBALS['post'] = get_post($video);
    $say('prepend_attachment of a video with a featured image: its poster', preg_match('/poster="([^"]*)"/', prepend_attachment(''), $m) ? basename($m[1]) : null);

    // is_page_template: whole slugs only.
    $templated = $made['posts'][] = wp_insert_post(['post_type' => 'page', 'post_title' => 'zzpd templated', 'post_status' => 'publish']);
    update_post_meta($templated, '_wp_page_template', 'a/full.php');
    $query = $GLOBALS['wp_query'];
    $GLOBALS['wp_query'] = new WP_Query(['page_id' => $templated]);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $say('is_page_template against a/full.php', [is_page_template(), is_page_template('a/full.php'), is_page_template('b/full.php'), is_page_template('full.php'), is_page_template(['x.php', 'b/full.php']), is_page_template('default')]);
    $GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = $query;
} finally {
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 3) {
            wp_delete_user($id);
        }
    }
}
echo json_encode($log);
