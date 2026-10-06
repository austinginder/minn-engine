<?php
/**
 * Functions plugins call that the engine held as placeholders, as the
 * reference answers them (probe placeholders-a): wp_debug_backtrace_summary
 * (the probe's own frames: functions, methods, static calls, a class left
 * out, frames skipped), wp_link_pages over a three-page post in both modes
 * and with every argument, locale switching (switch_to_locale and
 * switch_to_user_locale, nested, restored, and the actions they fire),
 * wp_unspam_comment, _count_posts_cache_key, _get_meta_table, the social
 * link services, get_block_categories, page templates (the block theme's,
 * and a classic theme's by their headers), and the PHP 8.4 array
 * functions the reference provides on older PHP. Same protocol as
 * api-probe.php; the posts, comments and files it makes go at the end.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$home = home_url();
$made = ['posts' => [], 'comments' => []];

// The call stack, the probe's frames only (what lies beneath differs between the two stacks).
function zz_probe_trace_inner($pretty)
{
    return wp_debug_backtrace_summary(null, 0, $pretty);
}
function zz_probe_trace_outer($pretty)
{
    return zz_probe_trace_inner($pretty);
}
final class ZzProbeTracer
{
    public function run($pretty)
    {
        return zz_probe_trace_outer($pretty);
    }

    public static function go($pretty)
    {
        return (new self())->run($pretty);
    }

    public function leftOut()
    {
        return wp_debug_backtrace_summary('ZzProbeTracer', 0, false);
    }

    public function skipping()
    {
        return wp_debug_backtrace_summary(null, 1, false);
    }
}
$ours = static fn ($frames): array => array_values(array_filter(is_array($frames) ? $frames : explode(', ', (string) $frames), static fn (string $frame): bool => (bool) preg_match('/zz_probe|ZzProbe|zz-probe/', $frame)));
// Through a hook, a filter, and a file required from the temporary folder (outside both installs).
add_action('zz_probe_hook', static function () use (&$traced): void {
    $traced['action'] = zz_probe_trace_inner(false);
});
add_filter('zz_probe_filter', static fn ($value) => zz_probe_trace_inner(false));
$traced = [];
do_action('zz_probe_hook');
$traced['filter'] = apply_filters('zz_probe_filter', null);
$traced['ref array'] = apply_filters_ref_array('zz_probe_filter', [null]);
$required = realpath(sys_get_temp_dir()) . '/zz-probe-trace.php';
file_put_contents($required, "<?php\nreturn zz_probe_trace_inner(false);\n");
$traced['required'] = require $required;
unlink($required);
remove_all_actions('zz_probe_hook');
remove_all_filters('zz_probe_filter');
$say('backtrace', [
    'pretty' => $ours(ZzProbeTracer::go(true)),
    'list' => $ours(ZzProbeTracer::go(false)),
    'pretty is a string' => is_string(ZzProbeTracer::go(true)),
    'class left out' => $ours((new ZzProbeTracer())->leftOut()),
    'frames skipped' => $ours((new ZzProbeTracer())->skipping()),
    'function alone' => $ours(zz_probe_trace_outer(false)),
    'through hooks and a file' => json_decode(str_replace(wp_normalize_path((string) realpath(sys_get_temp_dir())), '{tmp}', (string) json_encode(array_map($ours, $traced), JSON_UNESCAPED_SLASHES)), true),
]);

// wp_link_pages over a post split in three.
$paged = (int) wp_insert_post(['post_title' => 'zz probe paged', 'post_content' => "one<!--nextpage-->two<!--nextpage-->three", 'post_status' => 'publish']);
$single = (int) wp_insert_post(['post_title' => 'zz probe whole', 'post_content' => 'whole', 'post_status' => 'publish']);
$draft = (int) wp_insert_post(['post_title' => 'zz probe paged draft', 'post_content' => "a<!--nextpage-->b", 'post_status' => 'draft']);
array_push($made['posts'], $paged, $single, $draft);
$mask = static fn ($value) => json_decode((string) preg_replace('/zz-probe-paged(?:-\\d+)?/', '{slug}', str_replace([(string) $paged, (string) $draft, $home], ['{paged}', '{draft}', '{home}'], (string) json_encode($value, JSON_UNESCAPED_SLASHES))), true);
$links = static function (int $id, int $page, array $args = []) use ($mask): array {
    global $post;
    $post = get_post($id);
    setup_postdata($post);
    $GLOBALS['page'] = $page;
    // Links to the next and previous page show only where the whole post is shown ($more).
    $GLOBALS['more'] = ($args['more'] ?? 1);
    unset($args['more']);
    $out = wp_link_pages($args + ['echo' => 0]);
    return $mask([$out, $GLOBALS['numpages'] ?? null, $GLOBALS['multipage'] ?? null]);
};
$say('wp_link_pages', [
    'page 1' => $links($paged, 1),
    'page 2' => $links($paged, 2),
    'page 3' => $links($paged, 3),
    'next on 1' => $links($paged, 1, ['next_or_number' => 'next']),
    'next on 2' => $links($paged, 2, ['next_or_number' => 'next']),
    'next on 3' => $links($paged, 3, ['next_or_number' => 'next']),
    'next without more' => $links($paged, 2, ['next_or_number' => 'next', 'more' => 0]),
    'numbers without more' => $links($paged, 2, ['more' => 0]),
    'every argument' => $links($paged, 2, ['before' => '<nav>', 'after' => '</nav>', 'link_before' => '<i>', 'link_after' => '</i>', 'aria_current' => 'step', 'separator' => ' | ', 'pagelink' => 'Part %', 'nextpagelink' => 'On', 'previouspagelink' => 'Back']),
    'next with words' => $links($paged, 2, ['next_or_number' => 'next', 'nextpagelink' => 'On', 'previouspagelink' => 'Back', 'link_before' => '[', 'link_after' => ']']),
    'one page' => $links($single, 1),
    'draft' => $links($draft, 2),
    'draft on 1' => $links($draft, 1),
    'query string form' => $links($paged, 2, ['before' => '<p>', 'echo' => 0]),
]);
add_filter('wp_link_pages_link', static fn ($link, $i) => "<b data-i=\"{$i}\">{$link}</b>", 10, 2);
add_filter('wp_link_pages_args', static fn ($args) => ['separator' => ' / '] + $args);
add_filter('wp_link_pages', static fn ($output, $args) => $output . '<!-- ' . implode(',', array_keys((array) $args)) . ' -->', 10, 2);
$say('wp_link_pages filtered', $links($paged, 2));
remove_all_filters('wp_link_pages_link');
remove_all_filters('wp_link_pages_args');
remove_all_filters('wp_link_pages');
ob_start();
$returned = $links($paged, 2, ['echo' => 1]);
$say('wp_link_pages echoed', [$returned[0], $mask(ob_get_clean())]);
wp_reset_postdata();

// A user's locale, and switching locales: an empty German .mo makes de_DE available (the reference
// lists languages when it loads, so its capture has the file in place first; the engine's replay makes it).
$mo = WP_LANG_DIR . '/de_DE.mo';
$madeMo = !file_exists($mo) && wp_mkdir_p(WP_LANG_DIR) && file_put_contents($mo, pack('V7', 0x950412de, 0, 0, 28, 28, 0, 28)) !== false;
$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
$GLOBALS['wp_locale_switcher']->init();
$switcher = $GLOBALS['wp_locale_switcher'];
$heard = [];
$hear = static function (...$args) use (&$heard): void {
    $heard[] = current_filter() . ' ' . json_encode($args);
};
foreach (['switch_locale', 'restore_previous_locale', 'change_locale'] as $hook) {
    add_action($hook, $hear, 10, 3);
}
$state = static fn () => [is_locale_switched(), $switcher->get_switched_locale(), $switcher->get_switched_user_id(), get_locale(), determine_locale()];
$editor = (int) (get_users(['role' => 'editor', 'number' => 1, 'fields' => 'ID'])[0] ?? 2);
$keptLocale = get_user_meta($editor, 'locale', true);
$steps = [];
$steps['start'] = $state();
$steps['to en_US'] = [switch_to_locale('en_US'), $state()];
$steps['to xx_YY'] = [switch_to_locale('xx_YY'), $state()];
$steps['to de_DE'] = [switch_to_locale('de_DE'), $state()];
$steps['to de_DE again'] = [switch_to_locale('de_DE'), $state()];
$steps['to en_US inside'] = [switch_to_locale('en_US'), $state()];
$steps['restore previous'] = [restore_previous_locale(), $state()];
$steps['restore previous again'] = [restore_previous_locale(), $state()];
$steps['restore with nothing'] = [restore_previous_locale(), restore_current_locale(), $state()];
update_user_meta($editor, 'locale', 'de_DE');
wp_set_current_user($editor);
$steps['a user\'s locale'] = [get_user_locale($editor), get_user_locale(get_userdata($editor)), get_user_locale(), get_user_locale(0), get_user_locale((string) $editor), get_user_locale(999999), get_user_locale(-1)];
wp_set_current_user(0);
$steps['signed out'] = [get_user_locale(), get_user_locale($editor)];
$steps['user in German'] = [switch_to_user_locale($editor), $state()];
$steps['user restore'] = [restore_current_locale(), $state()];
update_user_meta($editor, 'locale', '');
$steps['user on the site default'] = [switch_to_user_locale($editor), $state()];
$steps['no such user'] = [switch_to_user_locale(999999), $state()];
update_user_meta($editor, 'locale', $keptLocale);
foreach (['switch_locale', 'restore_previous_locale', 'change_locale'] as $hook) {
    remove_action($hook, $hear, 10);
}
if ($madeMo) {
    unlink($mo);
}
$say('locale switching', ['steps' => $steps, 'hooks' => array_map(static fn ($line) => str_replace((string) $editor, '{editor}', $line), $heard)]);

// A comment back from spam.
$commented = (int) wp_insert_post(['post_title' => 'zz probe comments', 'post_status' => 'publish']);
$made['posts'][] = $commented;
$comment = (int) wp_insert_comment(['comment_post_ID' => $commented, 'comment_content' => 'zz probe spam', 'comment_approved' => '0', 'comment_author' => 'Zz']);
$approved = (int) wp_insert_comment(['comment_post_ID' => $commented, 'comment_content' => 'zz probe fine', 'comment_approved' => '1', 'comment_author' => 'Zz']);
array_push($made['comments'], $comment, $approved);
wp_spam_comment($comment);
$fired = [];
$listen = static function (...$args) use (&$fired): void {
    $fired[] = current_filter() . ' ' . json_encode(array_map(static fn ($a) => is_object($a) ? get_class($a) : $a, $args));
};
foreach (['unspam_comment', 'unspammed_comment', 'transition_comment_status', 'comment_unapproved_to_approved', 'comment_spam_to_unapproved', 'wp_set_comment_status', 'edit_comment'] as $hook) {
    add_action($hook, $listen, 10, 3);
}
$result = wp_unspam_comment($comment);
foreach (['unspam_comment', 'unspammed_comment', 'transition_comment_status', 'comment_unapproved_to_approved', 'comment_spam_to_unapproved', 'wp_set_comment_status', 'edit_comment'] as $hook) {
    remove_action($hook, $listen, 10);
}
$unspamHooks = $fired;
$idsOut = static fn (array $lines) => array_map(static fn (string $line) => str_replace([(string) $comment, (string) $approved], ['{comment}', '{approved}'], $line), $lines);
// An approved comment spammed and back: the status it had is kept aside and restored.
$liked = (int) wp_insert_comment(['comment_post_ID' => $commented, 'comment_content' => 'zz probe liked', 'comment_approved' => '1', 'comment_author' => 'Zz']);
$made['comments'][] = $liked;
$fired = [];
foreach (['spam_comment', 'spammed_comment', 'transition_comment_status', 'wp_set_comment_status', 'comment_approved_to_spam', 'unspam_comment', 'unspammed_comment', 'comment_spam_to_approved'] as $hook) {
    add_action($hook, $listen, 10, 3);
}
$spammed = wp_spam_comment($liked);
$aside = [get_comment_meta($liked, '_wp_trash_meta_status', true), get_comment_meta($liked, '_wp_trash_meta_time', true) !== '', wp_get_comment_status($liked)];
$back = wp_unspam_comment($liked);
foreach (['spam_comment', 'spammed_comment', 'transition_comment_status', 'wp_set_comment_status', 'comment_approved_to_spam', 'unspam_comment', 'unspammed_comment', 'comment_spam_to_approved'] as $hook) {
    remove_action($hook, $listen, 10);
}
$say('spam and back', ['spammed' => $spammed, 'kept aside' => $aside, 'back' => $back, 'status after' => wp_get_comment_status($liked), 'hooks' => array_map(static fn (string $line) => str_replace((string) $liked, '{liked}', $line), $fired), 'spam missing' => wp_spam_comment(999999)]);
$fired = [];
foreach (['trash_comment', 'trashed_comment', 'untrash_comment', 'untrashed_comment', 'wp_set_comment_status', 'comment_approved_to_trash', 'comment_trash_to_approved'] as $hook) {
    add_action($hook, $listen, 10, 3);
}
$trashed = [wp_trash_comment($liked), get_comment_meta($liked, '_wp_trash_meta_status', true), wp_untrash_comment($liked), wp_get_comment_status($liked), get_comment_meta($liked, '_wp_trash_meta_status', true)];
foreach (['trash_comment', 'trashed_comment', 'untrash_comment', 'untrashed_comment', 'wp_set_comment_status', 'comment_approved_to_trash', 'comment_trash_to_approved'] as $hook) {
    remove_action($hook, $listen, 10);
}
$say('trash and back', ['steps' => $trashed, 'hooks' => array_map(static fn (string $line) => str_replace((string) $liked, '{liked}', $line), $fired), 'untrash missing' => wp_untrash_comment(999999)]);
$say('wp_unspam_comment', [
    'result' => $result,
    'status after' => wp_get_comment_status($comment),
    'trash meta left' => [get_comment_meta($comment, '_wp_trash_meta_status', true), get_comment_meta($comment, '_wp_trash_meta_time', true) !== ''],
    'hooks' => $idsOut($unspamHooks),
    'not spam' => wp_unspam_comment($approved),
    'status of the one not spam' => wp_get_comment_status($approved),
    'no such comment' => wp_unspam_comment(999999),
]);

// Small helpers.
wp_set_current_user(1);
$say('_count_posts_cache_key', [_count_posts_cache_key(), _count_posts_cache_key('page'), _count_posts_cache_key('post', 'readable'), _count_posts_cache_key('attachment', 'readable')]);
wp_set_current_user((int) (get_users(['role' => 'author', 'number' => 1, 'fields' => 'ID'])[0] ?? 3));
$say('_count_posts_cache_key as an author', array_map(static fn ($key) => preg_replace('/_\d+$/', '_{author}', $key), [_count_posts_cache_key('post', 'readable'), _count_posts_cache_key('page', 'readable'), _count_posts_cache_key('post')]));
wp_set_current_user(0);
$say('_count_posts_cache_key signed out', [_count_posts_cache_key('post', 'readable'), _count_posts_cache_key('zz_none', 'readable')]);
global $wpdb;
$names = static fn (array $tables) => array_map(static fn ($t) => str_replace($wpdb->prefix, '{prefix}', (string) $t), $tables);
$say('database tables', ['network ones' => $names(['blogs' => $wpdb->blogs, 'blogmeta' => $wpdb->blogmeta, 'site' => $wpdb->site, 'sitemeta' => $wpdb->sitemeta, 'signups' => $wpdb->signups]), 'global' => $names($wpdb->tables('global')), 'ms_global' => $names($wpdb->tables('ms_global')), 'all' => array_keys($wpdb->tables('all')), 'blog' => array_keys($wpdb->tables('blog')), 'old' => array_keys($wpdb->tables('old'))]);
$say('_get_meta_table', array_map(static fn ($t) => is_string(_get_meta_table($t)) ? str_replace($wpdb->prefix, '{prefix}', _get_meta_table($t)) : _get_meta_table($t), ['post' => 'post', 'user' => 'user', 'comment' => 'comment', 'term' => 'term', 'site' => 'site', 'blog' => 'blog', 'nope' => 'nope', 'empty' => '']));
$services = block_core_social_link_services();
$say('social link services', [
    'count' => count($services),
    'first keys' => array_slice(array_keys($services), 0, 5),
    'names' => [block_core_social_link_get_name('wordpress'), block_core_social_link_get_name('x'), block_core_social_link_get_name('chain'), block_core_social_link_get_name('nope')],
    'one field' => [block_core_social_link_services('wordpress', 'name'), md5((string) block_core_social_link_services('wordpress', 'icon')), block_core_social_link_services('nope', 'name'), block_core_social_link_services('wordpress', 'nope')],
    'one service' => array_keys((array) block_core_social_link_services('mastodon')),
    'icon hashes' => array_map(static fn ($s) => md5((string) ($s['icon'] ?? '')), array_slice($services, 0, 4)),
]);

// The editor's block categories, for a post and for the site editor, with a plugin's added.
$categories = static fn ($context) => array_map(static fn ($c) => [$c['slug'] ?? null, $c['title'] ?? null, $c['icon'] ?? null], (array) get_block_categories($context));
$say('get_block_categories', [
    'post' => $categories(get_post($paged)),
    'site editor' => class_exists('WP_Block_Editor_Context') ? $categories(new WP_Block_Editor_Context(['name' => 'core/edit-site'])) : 'no class',
]);
add_filter('block_categories_all', static fn ($cats, $context) => array_merge([['slug' => 'zz-probe', 'title' => 'Zz Probe', 'icon' => null]], $cats, [['slug' => 'zz-name', 'title' => is_object($context) ? (string) ($context->name ?? '') : 'none', 'icon' => 'star']]), 10, 2);
$say('get_block_categories filtered', ['post' => $categories(get_post($paged)), 'site editor' => class_exists('WP_Block_Editor_Context') ? $categories(new WP_Block_Editor_Context(['name' => 'core/edit-site'])) : 'no class']);
remove_all_filters('block_categories_all');

// The theme's page templates.
$say('get_page_templates', [
    'pages' => get_page_templates(),
    'posts' => get_page_templates(null, 'post'),
    'for a post' => get_page_templates(get_post($paged), 'post'),
    'nope type' => get_page_templates(null, 'zz_none'),
]);
add_filter('theme_page_templates', static fn ($templates, $theme, $post, $type) => $templates + ['zz-probe.php' => 'Zz ' . $type . ' ' . (is_object($post) ? 'post' : 'none')], 10, 4);
add_filter('theme_templates', static fn ($templates, $theme, $post, $type) => $templates + ['zz-any.php' => 'Zz any ' . $type], 10, 4);
$say('get_page_templates filtered', ['pages' => get_page_templates(), 'posts' => get_page_templates(null, 'post')]);
remove_all_filters('theme_page_templates');
remove_all_filters('theme_templates');

// A classic theme's templates, by their headers (made for the run, read without switching to it).
$themeDir = get_theme_root() . '/zz-probe-templates';
$files = [
    'style.css' => "/*\nTheme Name: Zz Probe Templates\n*/\n",
    'index.php' => "<?php\n",
    'full.php' => "<?php\n/*\nTemplate Name: Zz Full\n*/\n",
    'templates/wide.php' => "<?php\n/**\n * Template Name: Zz Wide\n * Template Post Type: post, page, zz_book\n */\n",
    'templates/posts-only.php' => "<?php\n// Template Name: Zz Posts Only\n// Template Post Type: post\n",
    'templates/deep/too-deep.php' => "<?php\n/* Template Name: Zz Too Deep */\n",
    'no-name.php' => "<?php\n/* Template Post Type: post */\n",
    'notes.txt' => "Template Name: Zz Not PHP\n",
];
foreach ($files as $file => $body) {
    wp_mkdir_p(dirname("{$themeDir}/{$file}"));
    file_put_contents("{$themeDir}/{$file}", $body);
}
$classic = wp_get_theme('zz-probe-templates');
$say('classic theme templates', [
    'post templates' => $classic->get_post_templates(),
    'pages' => $classic->get_page_templates(),
    'posts' => $classic->get_page_templates(null, 'post'),
    'books' => $classic->get_page_templates(null, 'zz_book'),
]);
foreach (array_reverse(array_keys($files)) as $file) {
    unlink("{$themeDir}/{$file}");
}
foreach (['templates/deep', 'templates', ''] as $dir) {
    rmdir(rtrim("{$themeDir}/{$dir}", '/'));
}
$say('block theme post templates', wp_get_theme()->get_post_templates());

// PHP 8.4's array functions (the reference provides them on older PHP).
$numbers = ['a' => 1, 'b' => 2, 'c' => 3];
$say('array functions', [
    'array_all' => [array_all($numbers, static fn ($v) => $v > 0), array_all($numbers, static fn ($v) => $v > 1), array_all([], static fn () => false), array_all($numbers, static fn ($v, $k) => $k !== 'b')],
    'array_any' => [array_any($numbers, static fn ($v) => $v > 2), array_any($numbers, static fn ($v) => $v > 3), array_any([], static fn () => true), array_any($numbers, static fn ($v, $k) => $k === 'c')],
    'array_find' => [array_find($numbers, static fn ($v) => $v > 1), array_find($numbers, static fn ($v) => $v > 9), array_find($numbers, static fn ($v, $k) => $k === 'c')],
    'array_find_key' => [array_find_key($numbers, static fn ($v) => $v > 1), array_find_key($numbers, static fn ($v) => $v > 9), array_find_key([5, 6], static fn ($v) => $v === 6)],
]);

foreach ($made['comments'] as $id) {
    wp_delete_comment($id, true);
}
foreach (array_reverse($made['posts']) as $id) {
    wp_delete_post($id, true);
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
