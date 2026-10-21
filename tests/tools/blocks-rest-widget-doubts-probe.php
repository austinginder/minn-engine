<?php
/**
 * Group g's facade doubts measured on both stacks (probe
 * blocks-rest-widget-doubts): core block renderers (site logo, calendar,
 * home link, log in form, post author, comments link, time to read, read
 * more, query pagination, legacy widget, comment template threading,
 * search classes), translation files and their plural forms, upgrader
 * skins, active plugins, the REST URL's scheme, widget settings
 * conversion, the REST comments, revisions, request, controller, search,
 * templates, widgets and oEmbed classes, sitemaps, the media, recent posts
 * and RSS widgets, plugin dependencies and site health. Rows, files and
 * plugins the probe makes (zzdoubtg) are removed after; options it touches
 * are restored as they were. ZZG=part,part runs only those parts.
 */

$log = [];
$made = ['posts' => [], 'comments' => [], 'users' => [], 'files' => [], 'options' => []];
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$ids = [];
$mask = static function ($value) use (&$mask, &$ids, $host) {
    if (is_array($value)) {
        return array_map($mask, $value);
    }
    if (!is_string($value)) {
        return $value;
    }
    $value = str_replace(['https://' . $host, 'http://' . $host], '{home}', $value);
    foreach ($ids as $id => $label) {
        $value = (string) preg_replace('/(?<![0-9a-z])' . $id . '(?![0-9a-z])/i', '{' . $label . '}', $value);
    }
    return $value;
};
$say = static function (string $label, $value) use (&$log, $mask): void {
    $log[] = [$label, $mask($value)];
};
$keepOption = static function (string $name) use (&$made): void {
    if (!array_key_exists($name, $made['options'])) {
        $made['options'][$name] = [get_option($name, '__zzdoubtg_absent__')];
    }
};
$only = (string) getenv('ZZG');
$part = static fn (string $name): bool => $only === '' || str_contains(",{$only},", ",{$name},");
set_error_handler(static fn () => true, E_USER_DEPRECATED | E_DEPRECATED | E_USER_NOTICE | E_USER_WARNING | E_WARNING | E_NOTICE);
if (!did_action('init')) {
    do_action('init');
}
$userBefore = get_current_user_id();
$queryBefore = $GLOBALS['wp_query'] ?? null;
try {
    $author = wp_insert_user(['user_login' => 'zzdoubtg_author', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubtg_author@example.test', 'display_name' => 'ZZ Doubt G1', 'description' => 'ZZ bio text', 'role' => 'author']);
    $author = is_int($author) ? $author : 0;
    $made['users'][] = $author;
    $ids[$author] = 'author';
    $subscriber = wp_insert_user(['user_login' => 'zzdoubtg_sub', 'user_pass' => wp_generate_password(), 'user_email' => 'zzdoubtg_sub@example.test', 'role' => 'subscriber']);
    $subscriber = is_int($subscriber) ? $subscriber : 0;
    $made['users'][] = $subscriber;
    $ids[$subscriber] = 'subscriber';
    $admin = (int) (get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0] ?? 0);
    $post = wp_insert_post(['post_title' => 'ZZ Doubt G Post', 'post_status' => 'publish', 'post_author' => $author, 'post_content' => trim(str_repeat('hello ', 300)), 'comment_status' => 'open']);
    $post = is_int($post) ? $post : 0;
    $made['posts'][] = $post;
    $ids[$post] = 'post';
    $render = static function (string $markup, array $context = []): string {
        $parsed = parse_blocks($markup)[0];
        return (string) (new WP_Block($parsed, $context))->render();
    };

    if ($part('blocks')) {
        // The site logo theme mod removed while the option goes: the state during the theme mods update.
        $stylesheet = get_option('stylesheet');
        $keepOption("theme_mods_{$stylesheet}");
        $keepOption('site_logo');
        set_theme_mod('custom_logo', 5);
        set_theme_mod('zzdoubtg_mod', 'kept');
        $seen = [];
        $spy = static function () use (&$seen, $stylesheet) {
            $seen[] = [$GLOBALS['_ignore_site_logo_changes'] ?? null, has_action("update_option_theme_mods_{$stylesheet}", '_delete_site_logo_on_remove_custom_logo')];
        };
        add_action("update_option_theme_mods_{$stylesheet}", $spy, 5);
        _delete_custom_logo_on_remove_site_logo();
        remove_action("update_option_theme_mods_{$stylesheet}", $spy, 5);
        $say('site logo removal: state during the update, after', [$seen, $GLOBALS['_ignore_site_logo_changes'] ?? null, get_theme_mod('custom_logo', 'none'), get_theme_mod('zzdoubtg_mod')]);

        // The calendar: no published posts, and its colours.
        $none = static fn () => '0';
        add_filter('pre_option_wp_calendar_block_has_published_posts', $none);
        wp_set_current_user(0);
        $anon = $render('<!-- wp:calendar /-->');
        wp_set_current_user($subscriber);
        $sub = $render('<!-- wp:calendar /-->');
        wp_set_current_user($admin);
        $in = $render('<!-- wp:calendar /-->');
        remove_filter('pre_option_wp_calendar_block_has_published_posts', $none);
        wp_set_current_user(0);
        $tags = [];
        foreach (['{"textColor":"contrast","backgroundColor":"base","style":{"color":{"text":"#112233"}}}', '{"style":{"color":{"text":"#112233","background":"#445566"}}}', '{"backgroundColor":"base"}', '{"style":{"color":{"text":"#112233"}},"className":"zzc","align":"wide"}', '{"style":{"color":{"gradient":"linear-gradient(red,blue)"}}}', '{"gradient":"vivid-cyan","fontSize":"small"}', '{"style":{"typography":{"fontSize":"13px"}}}'] as $attrs) {
            $html = $render('<!-- wp:calendar ' . $attrs . ' /-->');
            $tags[] = [preg_match('#^<div[^>]*>#', $html, $m) ? $m[0] : '', preg_match('#<table[^>]*>#', $html, $m) ? $m[0] : ''];
        }
        $say('calendar: no posts (anon, subscriber, admin); colours on wrapper and table', [$anon, $sub, $in, $tags]);

        // Home link: a custom class and the navigation's colours.
        $say('home link: class, navigation colours', [
            preg_match('#<li[^>]*>#', $render('<!-- wp:home-link {"className":"zzcls"} /-->'), $m) ? $m[0] : '',
            preg_match('#<li[^>]*>#', $render('<!-- wp:home-link /-->', ['textColor' => 'contrast', 'backgroundColor' => 'base']), $m) ? $m[0] : '',
            preg_match('#<li[^>]*>#', $render('<!-- wp:home-link /-->', ['customTextColor' => '#123456', 'style' => ['typography' => ['fontSize' => '12px']]]), $m) ? $m[0] : '',
            preg_match('#<li[^>]*>#', $render('<!-- wp:home-link {"className":"zzcls"} /-->', ['textColor' => 'contrast', 'style' => ['color' => ['text' => '#123456', 'background' => '#654321']]]), $m) ? $m[0] : '',
            preg_match('#<li[^>]*>#', $render('<!-- wp:home-link {"style":{"typography":{"fontSize":"12px"}}} /-->', ['style' => ['color' => ['background' => '#654321']]]), $m) ? $m[0] : '',
        ]);

        // Log in/out as a form.
        wp_set_current_user(0);
        $say('loginout: form class', preg_match('#^<div[^>]*>#', $render('<!-- wp:loginout {"displayLoginAsForm":true} /-->'), $m) ? $m[0] : '');

        // The post author and biography on an author archive with no post.
        $GLOBALS['wp_query'] = new WP_Query(['author' => $author]);
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        unset($GLOBALS['post']);
        $say('post author on an author archive', [$render('<!-- wp:post-author /-->'), $render('<!-- wp:post-author-biography /-->')]);
        $GLOBALS['wp_query'] = $queryBefore;
        $GLOBALS['wp_the_query'] = $queryBefore;

        // Post comments link with a link colour.
        $GLOBALS['post'] = get_post($post);
        $say('post comments link: link colour class', preg_match('#^<div[^>]*>#', (string) preg_replace('/wp-elements-\d+/', 'wp-elements-N', render_block(parse_blocks('<!-- wp:post-comments-link {"style":{"elements":{"link":{"color":{"text":"#ff0000"}}}}} /-->')[0])), $m) ? $m[0] : '');
        unset($GLOBALS['post']);

        // Time to read: the locale's word count type, and the post from context only.
        $GLOBALS['post'] = get_post($post);
        $types = [];
        foreach (['words', 'characters_excluding_spaces', 'characters_including_spaces'] as $type) {
            $was = $GLOBALS['wp_locale']->word_count_type ?? null;
            $GLOBALS['wp_locale']->word_count_type = $type;
            $types[$type] = [wp_get_word_count_type(), strip_tags($render('<!-- wp:post-time-to-read {"displayMode":"words"} /-->', ['postId' => $post])), strip_tags($render('<!-- wp:post-time-to-read {"displayAsRange":false,"averageReadingSpeed":100} /-->', ['postId' => $post]))];
            $GLOBALS['wp_locale']->word_count_type = $was;
        }
        $short = wp_insert_post(['post_title' => 'ZZ short', 'post_status' => 'publish', 'post_content' => 'one two three four five']);
        $made['posts'][] = $short;
        unset($GLOBALS['post']);
        $types['context only'] = strip_tags($render('<!-- wp:post-time-to-read {"displayMode":"words"} /-->', ['postId' => $short]));
        $GLOBALS['post'] = get_post($post);
        $types['global and other context'] = strip_tags($render('<!-- wp:post-time-to-read {"displayMode":"words"} /-->', ['postId' => $short]));
        $locked = wp_insert_post(['post_title' => 'ZZ locked', 'post_status' => 'publish', 'post_password' => 'zzpw', 'post_content' => 'one two three']);
        $made['posts'][] = $locked;
        $GLOBALS['post'] = get_post($locked);
        $types['protected global'] = strip_tags($render('<!-- wp:post-time-to-read {"displayMode":"words"} /-->', ['postId' => $locked]));
        unset($GLOBALS['post']);
        $types['no context'] = $render('<!-- wp:post-time-to-read {"displayMode":"words"} /-->');
        $say('time to read: word count types', $types);

        // Read more: content filtered, justification.
        $say('read more: content and justification', $render('<!-- wp:read-more {"content":"<script>x()</script><b onclick=\"y\">Go</b>","justifyContent":"center"} /-->', ['postId' => $post]));

        // Site logo height in proportion.
        $logo = wp_insert_attachment(['post_title' => 'zzdoubtg logo', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], 'zzdoubtg-logo.png');
        $logo = is_int($logo) ? $logo : 0;
        $made['posts'][] = $logo;
        $ids[$logo] = 'logo';
        $heights = [];
        foreach ([[300, 200, 100], [300, 201, 100], [333, 500, 100], [7, 5, 3]] as [$w, $h, $at]) {
            wp_update_attachment_metadata($logo, ['width' => $w, 'height' => $h, 'file' => 'zzdoubtg-logo.png', 'sizes' => []]);
            set_theme_mod('custom_logo', $logo);
            $heights[] = preg_match('#<img[^>]*>#', $render('<!-- wp:site-logo {"width":' . $at . ',"isLink":false} /-->'), $m) ? preg_replace('#\s(src|srcset|sizes|alt|class|decoding|loading|data-[a-z-]+)="[^"]*"#', '', $m[0]) : '';
        }
        $say('site logo height', $heights);

        // Query pagination following the main query, capped by its pages setting.
        $pages = [];
        $extra = [];
        for ($i = 0; $i < 5; $i++) {
            $extra[] = $made['posts'][] = wp_insert_post(['post_title' => "ZZ page post {$i}", 'post_status' => 'publish', 'post_content' => 'x']);
        }
        foreach ([[2, 2], [5, 2], [2, 0], [20, 0], [1, 1]] as [$paged, $cap]) {
            $GLOBALS['wp_query'] = new WP_Query(['posts_per_page' => 1, 'paged' => $paged, 'post_type' => 'post']);
            $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
            $GLOBALS['paged'] = $paged;
            $context = ['query' => ['inherit' => true, 'pages' => $cap]];
            $numbers = (string) render_block_core_query_pagination_numbers([], '', new WP_Block(parse_blocks('<!-- wp:query-pagination-numbers /-->')[0], $context));
            $next = render_block_core_query_pagination_next([], '', new WP_Block(parse_blocks('<!-- wp:query-pagination-next /-->')[0], $context));
            $prev = render_block_core_query_pagination_previous([], '', new WP_Block(parse_blocks('<!-- wp:query-pagination-previous /-->')[0], $context));
            $pages["page {$paged} cap {$cap}"] = [preg_match_all('#<a #', $numbers), preg_match_all('#<span[^>]*current#', $numbers), $next === null ? null : (preg_match('#href="([^"]*)"#', (string) $next, $m) ? (string) preg_replace('#.*[/=](\d+)/?$#', '$1', $m[1]) : $next), $prev === null ? null : (string) $prev !== ''];
        }
        unset($GLOBALS['paged']);
        $GLOBALS['wp_query'] = $queryBefore;
        $GLOBALS['wp_the_query'] = $queryBefore;
        $say('query pagination: inherit with a pages cap', $pages);

        // A legacy widget by its base: the arguments the widget is given.
        $GLOBALS['zzdoubtg_widget_args'] = null;
        if (!class_exists('Zzdoubtg_Widget')) {
            eval('class Zzdoubtg_Widget extends WP_Widget { public function __construct() { parent::__construct("zzdoubtgw", "ZZ Doubt Widget"); } public function widget($args, $instance) { $GLOBALS["zzdoubtg_widget_args"] = $args; echo "zzw"; } }');
        }
        register_widget('Zzdoubtg_Widget');
        $out = render_block_core_legacy_widget(['idBase' => 'zzdoubtgw']);
        $say('legacy widget by base: widget args', [$out, $GLOBALS['zzdoubtg_widget_args']]);
        unregister_widget('Zzdoubtg_Widget');

        // The comment template's threading: depth classes and the depth limit.
        $c = [];
        $c[1] = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz c1', 'comment_approved' => 1, 'comment_date' => '2025-01-01 00:00:01', 'comment_date_gmt' => '2025-01-01 00:00:01']);
        $c[2] = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz c2', 'comment_approved' => 1, 'comment_parent' => $c[1], 'comment_date' => '2025-01-01 00:00:02', 'comment_date_gmt' => '2025-01-01 00:00:02']);
        $c[3] = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz c3', 'comment_approved' => 1, 'comment_parent' => $c[2], 'comment_date' => '2025-01-01 00:00:03', 'comment_date_gmt' => '2025-01-01 00:00:03']);
        $c[4] = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz c4', 'comment_approved' => 1, 'comment_date' => '2025-01-01 00:00:04', 'comment_date_gmt' => '2025-01-01 00:00:04']);
        $c[5] = wp_insert_comment(['comment_post_ID' => $post, 'comment_content' => 'zz c5', 'comment_approved' => 1, 'comment_date' => '2025-01-01 00:00:05', 'comment_date_gmt' => '2025-01-01 00:00:05']);
        foreach ($c as $n => $id) {
            $made['comments'][] = $id;
            $ids[$id] = "c{$n}";
        }
        $threads = [];
        foreach (['1:2', '1:5', '0:2'] as $setting) {
            [$thread, $depth] = explode(':', $setting);
            $ft = static fn () => $thread;
            $fd = static fn () => $depth;
            add_filter('pre_option_thread_comments', $ft);
            add_filter('pre_option_thread_comments_depth', $fd);
            $tops = get_comments(['post_id' => $post, 'parent' => 0, 'orderby' => 'comment_date_gmt', 'order' => 'ASC', 'hierarchical' => 'threaded', 'status' => 'approve']);
            $block = new WP_Block(parse_blocks('<!-- wp:comment-template --><!-- wp:comment-content /--><!-- /wp:comment-template -->')[0], ['postId' => $post]);
            $html = block_core_comment_template_render_comments($tops, $block);
            remove_filter('pre_option_thread_comments', $ft);
            remove_filter('pre_option_thread_comments_depth', $fd);
            $threads[$setting] = trim((string) preg_replace(['#<p>(zz c\d)</p>#', '#<div[^>]*>|</div>#', '#\s+#'], ['$1', '', ' '], $html));
        }
        unset($GLOBALS['comment_depth']);
        $say('comment template threading', $threads);

        // The search block's classes, a latest comment's post title, the legacy post comments registration's filters.
        $say('search classes', array_map('classnames_for_block_core_search', [['buttonPosition' => 'button-outside', 'buttonUseIcon' => false], ['buttonPosition' => 'button-only'], ['buttonPosition' => 'button-only', 'isSearchFieldHidden' => false], ['buttonPosition' => 'no-button', 'buttonUseIcon' => true], ['buttonPosition' => 'button-inside', 'buttonUseIcon' => true], ['buttonUseIcon' => false], [], ['buttonPosition' => 'button-inside'], ['buttonPosition' => 'button-outside', 'buttonUseIcon' => true], ['buttonPosition' => 'button-only', 'buttonUseIcon' => false], ['buttonPosition' => 'button-only', 'buttonUseIcon' => true], ['buttonPosition' => 'no-button', 'buttonUseIcon' => false], ['buttonPosition' => 'button-inside', 'buttonUseIcon' => false], ['buttonPosition' => 'button-only', 'isSearchFieldHidden' => true, 'buttonUseIcon' => true], ['buttonPosition' => 'button-inside', 'buttonUseIcon' => 0], ['buttonPosition' => 'button-outside', 'buttonUseIcon' => null]]));
        $zero = wp_insert_post(['post_title' => '0', 'post_status' => 'publish']);
        $made['posts'][] = $zero;
        $empty = wp_insert_post(['post_title' => '', 'post_status' => 'publish', 'post_content' => 'zz']);
        $made['posts'][] = $empty;
        $amp = wp_insert_post(['post_title' => 'A & <b>B</b>', 'post_status' => 'publish']);
        $made['posts'][] = $amp;
        $titled = static fn ($title, $id) => $id === $amp ? '<i>x</i> "q" & y' : $title;
        $titles = [wp_latest_comments_draft_or_post_title($zero), wp_latest_comments_draft_or_post_title($empty), wp_latest_comments_draft_or_post_title($amp)];
        add_filter('the_title', $titled, 99, 2);
        $titles[] = wp_latest_comments_draft_or_post_title($amp);
        remove_filter('the_title', $titled, 99);
        $say('latest comments post title', $titles);
        $filters = [];
        $fm = static function ($metadata) use (&$filters) {
            $filters[] = ['block_type_metadata', $metadata['name'] ?? null, array_keys((array) $metadata)];
            return $metadata;
        };
        $fa = static function ($args, $name) use (&$filters) {
            $filters[] = ['register_block_type_args', $name];
            return $args;
        };
        add_filter('block_type_metadata', $fm);
        add_filter('register_block_type_args', $fa, 10, 2);
        register_legacy_post_comments_block();
        remove_filter('block_type_metadata', $fm);
        remove_filter('register_block_type_args', $fa, 10);
        $type = WP_Block_Type_Registry::get_instance()->get_registered('core/post-comments');
        $say('legacy post comments registration filters', [$filters, $type ? [$type->category, $type->style_handles, $type->editor_style_handles, $type->uses_context, $type->render_callback, $type->skip_inner_blocks, array_keys($type->attributes), $type->supports] : null]);
    }
    if ($part('i18n')) {
        $dir = sys_get_temp_dir() . '/zzdoubtg-l10n-' . getmypid();
        @mkdir($dir);
        $mo = static function (array $entries, int $revision = 0, bool $sorted = true): string {
            if ($sorted) {
                ksort($entries, SORT_STRING);
            }
            $count = count($entries);
            $dataAt = 28 + $count * 16;
            $originals = $translations = $originalData = $translationData = '';
            foreach (array_keys($entries) as $original) {
                $originals .= pack('VV', strlen((string) $original), $dataAt + strlen($originalData));
                $originalData .= $original . "\0";
            }
            $at = $dataAt + strlen($originalData);
            foreach ($entries as $translation) {
                $translations .= pack('VV', strlen($translation), $at + strlen($translationData));
                $translationData .= $translation . "\0";
            }
            return pack('VVVVVVV', 0x950412de, $revision, $count, 28, 28 + $count * 8, 0, $dataAt) . $originals . $translations . $originalData . $translationData;
        };
        $moTable = static function (string $bytes): array {
            $count = unpack('V', substr($bytes, 8, 4))[1];
            $at = unpack('V', substr($bytes, 12, 4))[1];
            $out = [];
            for ($i = 0; $i < $count; $i++) {
                [, $length, $offset] = unpack('V2', substr($bytes, $at + $i * 8, 8));
                $out[] = str_replace("\4", '^', substr($bytes, $offset, $length));
            }
            return $out;
        };
        $bytes = $mo(['' => "Content-Type: text/plain; charset=UTF-8\n", 'zzb' => 'B', 'zza' => 'A', "zzapple\0zzapples" => "pomme\0pommes", "ctx\4zzc" => 'C']);
        foreach (['x.txt' => $bytes, 'x.mo' => $bytes, 'y.mo' => "<?php return ['messages' => ['zzx' => 'X']];", 'y.php' => $bytes, 'r1.mo' => $mo(['zza' => 'b'], 1), 'r2.mo' => $mo(['zza' => 'b'], 0x10000), 'bad.mo' => 'not a mo file'] as $name => $content) {
            file_put_contents("{$dir}/{$name}", $content);
            $made['files'][] = "{$dir}/{$name}";
        }
        $made['files'][] = $dir;
        $created = [];
        foreach ([['x.txt', null], ['x.txt', 'mo'], ['x.mo', null], ['x.mo', 'xx'], ['x.mo', 'MO'], ['y.php', null], ['y.mo', 'php']] as [$name, $type]) {
            $file = WP_Translation_File::create("{$dir}/{$name}", $type);
            $created["{$name} " . var_export($type, true)] = $file === false ? false : get_class($file);
        }
        $say('translation file create by extension', $created);
        $parsed = [];
        foreach ([['y.mo', 'php'], ['y.php', 'mo'], ['r1.mo', 'mo'], ['r2.mo', 'mo'], ['bad.mo', 'mo']] as [$name, $type]) {
            $file = WP_Translation_File::create("{$dir}/{$name}", $type);
            ob_start();
            $entries = $file ? $file->entries() : null;
            $echoed = strlen((string) ob_get_clean());
            $parsed["{$name} as {$type}"] = [$entries, $file ? $file->error() : null, $echoed];
        }
        $say('translation file parse by class', $parsed);
        file_put_contents("{$dir}/z.l10n.php", "<?php\nreturn ['messages' => ['zzz' => 'Z', 'aaa' => 'A', 'mmm' => 'M']];\n");
        $made['files'][] = "{$dir}/z.l10n.php";
        file_put_contents("{$dir}/l.l10n.php", "<?php\nreturn ['messages' => ['0' => 'zero', '1' => 'one']];\n");
        $made['files'][] = "{$dir}/l.l10n.php";
        $say('translation transform order and lists', [
            $moTable((string) WP_Translation_File::transform("{$dir}/z.l10n.php", 'mo')),
            $moTable((string) WP_Translation_File::transform("{$dir}/x.mo", 'mo')),
            str_replace("\n", '|', (string) WP_Translation_File::transform("{$dir}/l.l10n.php", 'php')),
            str_replace("\n", '|', (string) WP_Translation_File::transform("{$dir}/z.l10n.php", 'php')),
        ]);
        if (!class_exists('Zzdoubtg_Translation_File')) {
            eval('class Zzdoubtg_Translation_File extends WP_Translation_File_MO { public static function zz() { return new static(""); } public function plural($e) { return $this->make_plural_form_function($e); } }');
        }
        $plurals = [];
        foreach (['abc', '(n', 'n ** 2', 'n = 1', '(n==1) ? 0 : 1 )', 'n % 0', 'n>1 ? 2', 'n > 1;', 'n ==', '1', 'n != 1', ' n>1 ', 'n>1;;', '(n>1);', 'n>1 ;', 'n?1:0', '!n', 'n%10==1 && n%100!=11 ? 0 : n%10>=2 && n%10<=4 && (n%100<10 || n%100>=20) ? 1 : 2', '', 'n > 1 ? 1 : 0;', "n>1\n", 'n>-1'] as $expression) {
            try {
                $f = Zzdoubtg_Translation_File::zz()->plural($expression);
            } catch (Throwable $e) {
                $plurals[$expression] = 'make ' . get_class($e);
                continue;
            }
            $row = [];
            foreach ([0, 1, 2, 5, 11, 22] as $n) {
                try {
                    $row[] = $f($n);
                } catch (Throwable $e) {
                    $row[] = get_class($e);
                }
            }
            $plurals[$expression] = $row;
        }
        $say('translation file plural form functions', $plurals);
        file_put_contents("{$dir}/zzdoubtg-domain.mo", $mo(['' => "Content-Type: text/plain; charset=UTF-8\nPlural-Forms: nplurals=2; plural=(n != 1);\n", "zzapple\0zzapples" => "pomme\0pommes", 'zzone' => 'un']));
        $made['files'][] = "{$dir}/zzdoubtg-domain.mo";
        load_textdomain('zzdoubtg', "{$dir}/zzdoubtg-domain.mo");
        $translations = get_translations_for_domain('zzdoubtg');
        $entry = [];
        foreach ((array) $translations->entries as $e) {
            $entry[] = [$e->singular, $e->plural, $e->is_plural, $e->translations];
        }
        $say('translations entries and plurals', [get_class($translations), $entry, $translations->translate_plural('zzone', null, 2), $translations->translate_plural(null, 'zzmany', 2), $translations->translate_plural('zzapple', 'zzapples', 2), $translations->translate_plural('zzx', 'zzxs', '1')]);
        unload_textdomain('zzdoubtg');
    }
    if ($part('misc')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        // Upgrader skins: an array as feedback, an error's lines.
        $auto = new Automatic_Upgrader_Skin();
        $auto->feedback(['zza', 'zzb']);
        $auto->feedback('zz line');
        if (!class_exists('Zzdoubtg_Skin')) {
            eval('class Zzdoubtg_Skin extends WP_Upgrader_Skin { public $lines = []; public function feedback($feedback, ...$args) { $this->lines[] = $feedback; } }');
            eval('class Zzdoubtg_Bulk_Skin extends Bulk_Upgrader_Skin { public $lines = []; public function feedback($feedback, ...$args) { $this->lines[] = $feedback; } }');
        }
        $error = new WP_Error('c1', 'Msg one', 'Data one');
        $error->add('c2', 'Msg two', 'Data two');
        $error->add('c3', 'Msg three');
        $error->add('c1', 'Msg one b');
        $second = new WP_Error('c1', 'First without data');
        $second->add('c2', 'Second with data', 'Data2');
        $skins = [];
        foreach ([$error, $second, new WP_Error('h', 'Html data', '<b>bold</b> & x'), 'zz string error'] as $e) {
            $skin = new Zzdoubtg_Skin();
            $skin->done_header = true;
            ob_start();
            $skin->error($e);
            $printed = ob_get_clean();
            $bulk = new Zzdoubtg_Bulk_Skin();
            $bulk->upgrader = (object) ['update_current' => 3];
            $bulk->done_header = true;
            ob_start();
            $bulk->error($e);
            $skins[] = [$skin->lines, $printed, $bulk->lines, $bulk->error ?? null, ob_get_clean()];
        }
        $say('upgrader skins: feedback array, error lines', [$auto->get_upgrade_messages(), $skins]);

        // Active plugins that are not plugins.
        $keepOption('active_plugins');
        $active = (array) get_option('active_plugins', []);
        $one = (string) ($active[0] ?? '');
        @mkdir(WP_PLUGIN_DIR . '/zzdoubtg-notes');
        file_put_contents(WP_PLUGIN_DIR . '/zzdoubtg-notes/readme.md', 'zz');
        $made['files'][] = WP_PLUGIN_DIR . '/zzdoubtg-notes/readme.md';
        $made['files'][] = WP_PLUGIN_DIR . '/zzdoubtg-notes';
        $listed = static fn () => array_merge($active, ['../plugins/' . $one, 'zzdoubtg-notes/readme.md', './' . $one, 'zzdoubtg-notes/', 'zzdoubtg-missing/x.php']);
        add_filter('option_active_plugins', $listed);
        $valid = array_map(static fn ($f) => str_replace(WP_PLUGIN_DIR, '{plugins}', $f), wp_get_active_and_valid_plugins());
        remove_filter('option_active_plugins', $listed);
        $say('active and valid plugins: odd entries', array_values(array_filter($valid, static fn ($f) => str_contains($f, $one) || str_contains($f, 'zzdoubtg'))));

        // The REST URL for an http home on an https request, the request's host the home's or another.
        $server = $_SERVER;
        $http = static fn ($url) => str_replace('https://', 'http://', $url);
        add_filter('home_url', $http, 99);
        $rest = [];
        foreach ([$host, 'zzother.example'] as $name) {
            $_SERVER['HTTPS'] = 'on';
            $_SERVER['SERVER_NAME'] = $name;
            $rest[$name === $host ? 'same host' : 'other host'] = str_replace($host, '{host}', [get_rest_url(null, 'x'), get_home_url(null, 'wp-json'), get_home_url(null, 'wp-json', 'rest')]);
        }
        remove_filter('home_url', $http, 99);
        $_SERVER = $server;
        $say('rest url: http home on an https request', $rest);

        // Single widget settings converted: the sidebars renamed, in admin the options written.
        $sidebarsBefore = $GLOBALS['_wp_sidebars_widgets'] ?? null;
        $GLOBALS['_wp_sidebars_widgets'] = ['zzdoubtg-side' => ['zzdoubtg2', 'other-3'], 'wp_inactive_widgets' => []];
        $converted = [wp_convert_widget_settings('zzdoubtg2', 'widget_zzdoubtg2', ['title' => 'T']), $GLOBALS['_wp_sidebars_widgets'], get_option('widget_zzdoubtg2', 'absent')];
        $GLOBALS['_wp_sidebars_widgets'] = ['zzdoubtg-side' => ['zzdoubtg2', 'other-3']];
        $converted[] = wp_convert_widget_settings('zzdoubtg2', 'widget_zzdoubtg2', []);
        $converted[] = $GLOBALS['_wp_sidebars_widgets'];
        $converted[] = wp_convert_widget_settings('zzdoubtg2', 'widget_zzdoubtg2', [3 => ['title' => 'N']]);
        $keepOption('sidebars_widgets');
        $keepOption('widget_zzdoubtg2');
        set_current_screen('widgets');
        $GLOBALS['_wp_sidebars_widgets'] = ['zzdoubtg-side' => ['zzdoubtg2', 'other-3'], 'wp_inactive_widgets' => []];
        update_option('sidebars_widgets', ['zzdoubtg-store' => ['zzdoubtg2', 'zzx-2'], 'array_version' => 3]);
        $converted[] = wp_convert_widget_settings('zzdoubtg2', 'widget_zzdoubtg2', ['title' => 'A']);
        $stored = get_option('sidebars_widgets');
        $converted[] = [$stored, get_option('widget_zzdoubtg2', 'absent'), $GLOBALS['_wp_sidebars_widgets']];
        $GLOBALS['_wp_sidebars_widgets'] = ['zzdoubtg-side' => ['zzdoubtg2-3']];
        delete_option('widget_zzdoubtg2');
        $converted[] = wp_convert_widget_settings('zzdoubtg2', 'widget_zzdoubtg2', [3 => ['title' => 'N']]);
        $converted[] = get_option('widget_zzdoubtg2', 'absent');
        $GLOBALS['current_screen'] = null;
        $GLOBALS['_wp_sidebars_widgets'] = $sidebarsBefore;
        if ($sidebarsBefore === null) {
            unset($GLOBALS['_wp_sidebars_widgets']);
        }
        $say('convert widget settings: sidebars renamed', $converted);
    }
    if ($part('rest')) {
        $call = static fn (object $object, string $method, ...$args) => (fn () => $this->$method(...$args))->call($object);
        $codeOf = static fn ($r) => is_wp_error($r) ? [$r->get_error_code(), $r->get_error_data()['status'] ?? null, $r->get_error_message()] : $r;
        $comments = new WP_REST_Comments_Controller();
        // allow_empty_comment's second argument; a comment without content.
        $seenEmpty = [];
        $allowSpy = static function ($allow, $data) use (&$seenEmpty) {
            $seenEmpty[] = $data;
            return $allow;
        };
        add_filter('allow_empty_comment', $allowSpy, 10, 2);
        $allowed = [$call($comments, 'check_is_comment_content_allowed', ['comment_content' => '']), $call($comments, 'check_is_comment_content_allowed', ['comment_content' => '   ']), $call($comments, 'check_is_comment_content_allowed', []), $call($comments, 'check_is_comment_content_allowed', ['comment_post_ID' => 5, 'comment_content' => '', 'zzextra' => 1])];
        remove_filter('allow_empty_comment', $allowSpy, 10);
        $say('comment content allowed: the filter data', [$allowed, $seenEmpty]);

        // Reading a protected post's comments; the target post of a comment or note; creating a note.
        $own = wp_insert_post(['post_title' => 'ZZ own', 'post_status' => 'publish', 'post_author' => $author, 'comment_status' => 'open']);
        $ownClosed = wp_insert_post(['post_title' => 'ZZ own closed', 'post_status' => 'publish', 'post_author' => $author, 'comment_status' => 'closed']);
        $adminPost = wp_insert_post(['post_title' => 'ZZ admin post', 'post_status' => 'publish', 'post_author' => $admin, 'comment_status' => 'open']);
        $adminClosed = wp_insert_post(['post_title' => 'ZZ admin closed', 'post_status' => 'publish', 'post_author' => $admin, 'comment_status' => 'closed']);
        $draft = wp_insert_post(['post_title' => 'ZZ draft', 'post_status' => 'draft', 'post_author' => $admin, 'comment_status' => 'open']);
        $protected = wp_insert_post(['post_title' => 'ZZ protected', 'post_status' => 'publish', 'post_author' => $admin, 'post_password' => 'zzpw', 'comment_status' => 'open']);
        array_push($made['posts'], $own, $ownClosed, $adminPost, $adminClosed, $draft, $protected);
        $perm = [];
        foreach (['anon' => 0, 'subscriber' => $subscriber, 'author' => $author, 'admin' => $admin] as $who => $uid) {
            wp_set_current_user($uid);
            $request = new WP_REST_Request('GET', '/wp/v2/comments');
            $perm[$who]['read protected'] = [$call($comments, 'check_read_post_permission', get_post($protected), $request)];
            $request['password'] = 'zzpw';
            $perm[$who]['read protected'][] = $call($comments, 'check_read_post_permission', get_post($protected), $request);
            foreach (['own' => $own, 'own closed' => $ownClosed, 'admin post' => $adminPost, 'protected' => $protected, 'missing' => 999999991] as $label => $target) {
                $perm[$who]['target ' . $label] = [$codeOf($call($comments, 'check_target_post_permission', $target, new WP_REST_Request('POST', '/wp/v2/comments'), false)), $codeOf($call($comments, 'check_target_post_permission', $target, new WP_REST_Request('POST', '/wp/v2/comments'), true))];
            }
            foreach (['own' => $own, 'own closed' => $ownClosed, 'admin post' => $adminPost, 'admin closed' => $adminClosed, 'draft' => $draft] as $label => $target) {
                $request = new WP_REST_Request('POST', '/wp/v2/comments');
                $request->set_param('post', $target);
                $request->set_param('type', 'note');
                $note = $codeOf($comments->create_item_permissions_check($request));
                $request->set_param('type', 'comment');
                $perm[$who]['create ' . $label] = [$note, $codeOf($comments->create_item_permissions_check($request))];
            }
        }
        wp_set_current_user(0);
        $say('comments controller permissions', $perm);

        // Revisions: deleting with the parent not editable; dates with a zero GMT.
        $revisions = new WP_REST_Revisions_Controller('post');
        $say('revision dates', [$call($revisions, 'prepare_date_response', '0000-00-00 00:00:00', '2020-01-02 03:04:05'), $call($revisions, 'prepare_date_response', '0000-00-00 00:00:00', '0000-00-00 00:00:00'), $call($revisions, 'prepare_date_response', '2020-01-02 03:04:05', '2020-01-02 05:04:05'), $call($revisions, 'prepare_date_response', '2020-01-02 03:04:05'), $call($revisions, 'prepare_date_response', '0000-00-00 00:00:00')]);
        wp_set_current_user($admin);
        wp_update_post(['ID' => $adminPost, 'post_content' => 'zz rev 1']);
        wp_update_post(['ID' => $adminPost, 'post_content' => 'zz rev 2']);
        $revision = (int) (array_keys(wp_get_post_revisions($adminPost))[0] ?? 0);
        $noEdit = static fn ($caps, $cap, $user, $args) => $cap === 'edit_post' && (int) ($args[0] ?? 0) === $adminPost ? ['do_not_allow'] : $caps;
        add_filter('map_meta_cap', $noEdit, 10, 4);
        $request = new WP_REST_Request('DELETE', "/wp/v2/posts/{$adminPost}/revisions/{$revision}");
        $request->set_url_params(['parent' => $adminPost, 'id' => $revision]);
        $request->set_param('force', true);
        $say('revision delete: parent not editable', $codeOf($revisions->delete_item_permissions_check($request)));
        remove_filter('map_meta_cap', $noEdit, 10);
        wp_set_current_user(0);

        // Request parameters merged by priority, keys kept.
        $request = new WP_REST_Request('POST', '/zz');
        $request->set_query_params([5 => 'q5', 'a' => 'qa', 7 => 'q7']);
        $request->set_body_params([7 => 'b7', 9 => 'b9', 'a' => 'ba']);
        $request->set_url_params([5 => 'u5']);
        $say('request params by priority', $request->get_params());

        // The context param's description through gettext; _embedded among the fields.
        $mark = static fn ($translation, $text) => $text === 'Scope under which the request is made; determines fields present in response.' ? 'ZZ-TRANSLATED' : $translation;
        add_filter('gettext', $mark, 10, 2);
        $posts = new WP_REST_Posts_Controller('post');
        $context = $posts->get_context_param(['default' => 'view']);
        remove_filter('gettext', $mark, 10);
        $fields = [];
        foreach ([['_embed' => '1', '_fields' => 'id,_embedded'], ['_embed' => '1'], ['_fields' => 'id,_embedded'], []] as $params) {
            $request = new WP_REST_Request('GET', '/wp/v2/posts');
            foreach ($params as $k => $v) {
                $request->set_param($k, $v);
            }
            $list = $posts->get_fields_for_response($request);
            $fields[] = count($list) > 4 ? [in_array('_embedded', $list, true), array_slice($list, -2)] : $list;
        }
        $say('controller context description and _embedded', [$context['description'], $fields]);

        // Post format search: labels translated, slugs matched, the formats' own order.
        $formats = [];
        foreach (['link', 'image', 'audio', 'video', 'aside'] as $format) {
            if (!term_exists('post-format-' . $format, 'post_format')) {
                $t = wp_insert_term('post-format-' . $format, 'post_format');
                if (is_array($t)) {
                    $formats[] = (int) $t['term_id'];
                }
            }
        }
        $aside = static fn ($translation, $text, $context) => $text === 'Aside' && $context === 'Post format' ? 'ZZNote' : $translation;
        add_filter('gettext_with_context', $aside, 10, 3);
        $handler = new WP_REST_Post_Format_Search_Handler();
        $found = [];
        foreach (['aside', 'a', 'zznote', ''] as $search) {
            $request = new WP_REST_Request('GET', '/wp/v2/search');
            $request->set_param('search', $search);
            $request->set_param('per_page', 10);
            $request->set_param('page', 1);
            $result = $handler->search_items($request);
            $found[$search] = array_values(array_intersect($result[WP_REST_Search_Handler::RESULT_IDS], ['link', 'image', 'audio', 'video', 'aside', 'chat']));
        }
        remove_filter('gettext_with_context', $aside, 10);
        foreach ($formats as $t) {
            wp_delete_term($t, 'post_format');
        }
        $say('post format search', $found);

        // Arguments checked as they are sanitized; attachments' MIME types merged and refused.
        $checked = [];
        foreach (['typed' => ['type' => 'integer'], 'own sanitize' => ['type' => 'integer', 'sanitize_callback' => static fn ($v) => $v], 'own validate' => ['type' => 'integer', 'validate_callback' => static fn () => true], 'enum' => ['type' => 'string', 'enum' => ['a', 'b']]] as $label => $arg) {
            $request = new WP_REST_Request('GET', '/zz');
            $request->set_attributes(['args' => ['x' => $arg]]);
            $request->set_param('x', 'abc');
            $valid = $request->has_valid_params();
            $sanitized = $request->sanitize_params();
            $checked[$label] = [$codeOf($valid) === true ? true : $codeOf($valid)[0], $codeOf($sanitized) === true ? true : $codeOf($sanitized)[0], $request['x']];
        }
        $attachments = new WP_REST_Attachments_Controller('attachment');
        $mimes = [];
        foreach ([['media_type' => ['image'], 'mime_type' => ['application/pdf']], ['mime_type' => ['text/x-zz-notallowed']], ['mime_type' => ['application/pdf']]] as $params) {
            $request = new WP_REST_Request('GET', '/wp/v2/media');
            foreach ($params as $k => $v) {
                $request->set_param($k, $v);
            }
            $query = $call($attachments, 'prepare_items_query', [], $request);
            $mimes[] = isset($query['post_mime_type']) ? [count((array) $query['post_mime_type']), in_array('application/pdf', (array) $query['post_mime_type'], true)] : 'unset';
        }
        $say('request args checked; attachment mime types', [$checked, $mimes]);
    }
    if ($part('rest2')) {
        wp_set_current_user($admin);
        // Templates: a registered field on create and update; a new part's area.
        $areaTerms = static fn (): array => array_map('intval', (array) get_terms(['taxonomy' => 'wp_template_part_area', 'hide_empty' => false, 'fields' => 'ids']));
        $areasBefore = $areaTerms();
        $events = [];
        foreach (['wp_template', 'wp_template_part'] as $type) {
            register_rest_field($type, 'zzdoubtg_field', ['get_callback' => static fn () => 'got', 'update_callback' => static function ($value, $object, $name, $request, $objectType) use (&$events) {
                $events[] = ['update', $value, get_class($object), $name, $objectType];
                return true;
            }, 'schema' => ['type' => 'string', 'context' => ['view', 'edit']]]);
        }
        $after = static function () use (&$events) {
            $events[] = ['rest_after_insert'];
        };
        add_action('rest_after_insert_wp_template', $after);
        $templates = new WP_REST_Templates_Controller('wp_template');
        $request = new WP_REST_Request('POST', '/wp/v2/templates');
        foreach (['slug' => 'zzdoubtg-tpl', 'title' => 'ZZ tpl', 'content' => 'x', 'zzdoubtg_field' => 'zzv'] as $k => $v) {
            $request->set_param($k, $v);
        }
        $created = $templates->create_item($request);
        $data = is_wp_error($created) ? $created->get_error_code() : $created->get_data();
        if (is_array($data) && !empty($data['wp_id'])) {
            $made['posts'][] = (int) $data['wp_id'];
            $update = new WP_REST_Request('POST', '/wp/v2/templates/' . $data['id']);
            foreach (['id' => $data['id'], 'title' => 'ZZ tpl 2', 'zzdoubtg_field' => 'zzu'] as $k => $v) {
                $update->set_param($k, $v);
            }
            $updated = $templates->update_item($update);
            $events[] = ['updated field', is_wp_error($updated) ? $updated->get_error_code() : ($updated->get_data()['zzdoubtg_field'] ?? 'absent')];
        }
        $say('templates: a registered field on create and update', [is_array($data) ? ($data['zzdoubtg_field'] ?? 'absent') : $data, $events]);
        remove_action('rest_after_insert_wp_template', $after);
        $parts = new WP_REST_Templates_Controller('wp_template_part');
        $request = new WP_REST_Request('POST', '/wp/v2/template-parts');
        foreach (['slug' => 'zzdoubtg-part', 'title' => 'ZZ part', 'content' => 'x'] as $k => $v) {
            $request->set_param($k, $v);
        }
        $prepared = (fn () => $this->prepare_item_for_database($request))->call($parts);
        $created = $parts->create_item($request);
        $data = is_wp_error($created) ? $created->get_error_code() : $created->get_data();
        $areaOf = null;
        if (is_array($data) && !empty($data['wp_id'])) {
            $made['posts'][] = (int) $data['wp_id'];
            $areaOf = wp_get_object_terms((int) $data['wp_id'], 'wp_template_part_area', ['fields' => 'names']);
        }
        $say('template part without an area', [is_wp_error($prepared) ? $prepared->get_error_code() : array_keys((array) ($prepared->tax_input ?? [])), $prepared->tax_input['wp_template_part_area'] ?? null, $areaOf]);

        // Widgets: a widget that is not a WP_Widget saved and deleted through its control; an empty instance raw.
        $keepOption('sidebars_widgets');
        $keepOption('widget_zzdoubtg3plain');
        $GLOBALS['zzdoubtg_seen'] = [];
        $control = static function (...$args) {
            $GLOBALS['zzdoubtg_seen'][] = [$args, $_POST, array_intersect_key($_REQUEST, $_POST)];
            if (isset($_POST['widget-zzdoubtg3plain']['title'])) {
                update_option('widget_zzdoubtg3plain', $_POST['widget-zzdoubtg3plain']['title']);
            }
            if (!empty($_POST['delete_widget'])) {
                delete_option('widget_zzdoubtg3plain');
            }
        };
        register_sidebar(['id' => 'zzdoubtg-side', 'name' => 'ZZ side']);
        wp_register_sidebar_widget('zzdoubtg3plain', 'ZZ plain', static function () {
            echo 'zzplain';
        });
        wp_register_widget_control('zzdoubtg3plain', 'ZZ plain', $control);
        update_option('widget_zzdoubtg3plain', 'zzkeep');
        $sidebars = get_option('sidebars_widgets');
        $sidebars['wp_inactive_widgets'] = array_merge((array) ($sidebars['wp_inactive_widgets'] ?? []), ['zzdoubtg3plain']);
        update_option('sidebars_widgets', $sidebars);
        $widgets = new WP_REST_Widgets_Controller();
        $postBefore = $_POST;
        $request = new WP_REST_Request('PUT', '/wp/v2/widgets/zzdoubtg3plain');
        $request->set_param('id', 'zzdoubtg3plain');
        $request->set_param('form_data', ['widget-zzdoubtg3plain' => ['title' => 'zzsaved'], 'zzextra' => 'y']);
        $saved = (fn () => $this->save_widget($request, 'zzdoubtg-side'))->call($widgets);
        $afterSave = [$saved, get_option('widget_zzdoubtg3plain', null), $_POST === $postBefore];
        $request = new WP_REST_Request('DELETE', '/wp/v2/widgets/zzdoubtg3plain');
        $request->set_param('id', 'zzdoubtg3plain');
        $request->set_param('force', true);
        $deleted = $widgets->delete_item($request);
        $say('widgets: a plain widget saved and deleted through its control', [$afterSave, is_wp_error($deleted) ? $deleted->get_error_code() : [array_keys($deleted->get_data()), $deleted->get_data()['previous']['sidebar'] ?? null], get_option('widget_zzdoubtg3plain', null), $_POST === $postBefore, $GLOBALS['zzdoubtg_seen']]);
        $_POST = $postBefore;
        wp_unregister_sidebar_widget('zzdoubtg3plain');
        unregister_sidebar('zzdoubtg-side');
        if (!class_exists('Zzdoubtg_Rest_Widget')) {
            eval('class Zzdoubtg_Rest_Widget extends WP_Widget { public function __construct() { parent::__construct("zzdoubtg3w", "ZZ rest widget", ["show_instance_in_rest" => true]); } public function widget($args, $instance) { echo "zzw"; } }');
        }
        register_widget('Zzdoubtg_Rest_Widget');
        $keepOption('widget_zzdoubtg3w');
        update_option('widget_zzdoubtg3w', [2 => [], 3 => ['title' => 'zzt'], '_multiwidget' => 1]);
        $GLOBALS['wp_widget_factory']->widgets['Zzdoubtg_Rest_Widget']->_register();
        $raws = [];
        foreach (['zzdoubtg3w-2', 'zzdoubtg3w-3'] as $id) {
            $request = new WP_REST_Request('GET', '/wp/v2/widgets/' . $id);
            $request->set_param('context', 'edit');
            $response = $widgets->prepare_item_for_response(['widget_id' => $id, 'sidebar_id' => 'wp_inactive_widgets'], $request);
            $raws[] = is_wp_error($response) ? $response->get_error_code() : json_encode($response->get_data()['instance']['raw'] ?? 'none');
        }
        $say('widgets: an empty instance raw', $raws);
        unregister_widget('Zzdoubtg_Rest_Widget');

        // An embed handler's scripts: every queued script.
        wp_register_script('zzdoubtg3-pre', 'https://zz.example/zzpre.js');
        wp_register_script('zzdoubtg3-during', 'https://zz.example/zzduring.js');
        wp_enqueue_script('zzdoubtg3-pre');
        wp_embed_register_handler('zzdoubtg3', '#^https://zzembed\.example/#', static function () {
            wp_enqueue_script('zzdoubtg3-during');
            return '<b>zzembed</b>';
        });
        $oembed = new WP_oEmbed_Controller();
        $request = new WP_REST_Request('GET', '/oembed/1.0/proxy');
        foreach (['url' => 'https://zzembed.example/x', 'maxwidth' => 100, 'discover' => false] as $k => $v) {
            $request->set_param($k, $v);
        }
        $handled = $oembed->get_proxy_item($request);
        $handled = is_wp_error($handled) ? null : (object) $handled;
        wp_embed_unregister_handler('zzdoubtg3');
        wp_dequeue_script('zzdoubtg3-pre');
        wp_dequeue_script('zzdoubtg3-during');
        $say('oembed handler scripts', $handled === null ? null : array_values(array_filter((array) $handled->scripts, static fn ($s) => str_contains((string) $s, 'zz'))));
        foreach (array_diff($areaTerms(), $areasBefore) as $term) {
            wp_delete_term($term, 'wp_template_part_area');
        }
        wp_set_current_user(0);
    }
    if ($part('sitemaps')) {
        // A post's lastmod in the site's timezone.
        $zone = static fn () => 'America/New_York';
        add_filter('pre_option_timezone_string', $zone);
        $noOffset = static fn () => '';
        add_filter('pre_option_gmt_offset', $noOffset);
        $dated = wp_insert_post(['post_title' => 'ZZ sitemap dated', 'post_status' => 'publish', 'post_date' => '2026-08-27 22:21:33', 'post_date_gmt' => '2026-08-28 02:21:33']);
        $made['posts'][] = $dated;
        $GLOBALS['wpdb']->update($GLOBALS['wpdb']->posts, ['post_modified' => '2026-08-27 22:21:33', 'post_modified_gmt' => '2026-08-28 02:21:33'], ['ID' => $dated]);
        clean_post_cache($dated);
        $provider = new WP_Sitemaps_Posts();
        $only = static fn ($args) => ['post__in' => [$dated]] + $args;
        add_filter('wp_sitemaps_posts_query_args', $only);
        $list = $provider->get_url_list(1, 'post');
        remove_filter('wp_sitemaps_posts_query_args', $only);
        remove_filter('pre_option_timezone_string', $zone);
        remove_filter('pre_option_gmt_offset', $noOffset);
        $say('sitemap lastmod in the site timezone', array_column($list, 'lastmod'));

        // Subtypes named by their keys.
        if (!class_exists('Zzdoubtg_Sitemap_Provider')) {
            eval('class Zzdoubtg_Sitemap_Provider extends WP_Sitemaps_Provider { public $asked = []; public function __construct() { $this->name = "zzdoubtg"; $this->object_type = "zz"; } public function get_object_subtypes() { return ["alpha" => "x-value", "beta" => ["label" => "B"], "gamma" => (object) ["name" => "objname"]]; } public function get_url_list($page_num, $object_subtype = "") { return []; } public function get_max_num_pages($object_subtype = "") { $this->asked[] = $object_subtype; return 1; } }');
            eval('class Zzdoubtg_Sitemap_Empty extends Zzdoubtg_Sitemap_Provider { public function get_object_subtypes() { return []; } }');
            eval('class Zzdoubtg_Sitemap_Renderer extends WP_Sitemaps_Renderer { public function sheets() { return [$this->stylesheet, $this->stylesheet_index]; } }');
        }
        $typed = new Zzdoubtg_Sitemap_Provider();
        $empty = new Zzdoubtg_Sitemap_Empty();
        $say('sitemap type data by subtype keys', [$typed->get_sitemap_type_data(), $typed->asked, $empty->get_sitemap_type_data()]);
        $renderer = new Zzdoubtg_Sitemap_Renderer();
        $xml = $renderer->get_sitemap_xml([['loc' => 'https://zz.example/a']]);
        $say('sitemap renderer stylesheets', [$renderer->sheets(), $xml, $renderer->get_sitemap_index_xml([['loc' => 'https://zz.example/s.xml']])]);
    }
    if ($part('widgets')) {
        // The image widget: a custom size's caption width; an image by URL's loading attributes.
        $image = wp_insert_attachment(['post_title' => 'zzdoubtg image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_excerpt' => ''], 'zzdoubtg-image.png');
        $image = is_int($image) ? $image : 0;
        $made['posts'][] = $image;
        $ids[$image] = 'image';
        wp_update_attachment_metadata($image, ['width' => 45, 'height' => 45, 'file' => 'zzdoubtg-image.png', 'sizes' => []]);
        $widget = new WP_Widget_Media_Image();
        $media = [];
        foreach ([['size' => 'custom', 'width' => 123, 'height' => 45], ['size' => 'custom', 'width' => 500, 'height' => 0], ['size' => 'full']] as $size) {
            ob_start();
            $widget->render_media(['attachment_id' => $image, 'caption' => 'zz cap'] + $size);
            $media[] = preg_match('#width:\s*(\d+)px|style="width: (\d+)px#', (string) ob_get_clean(), $m) ? end($m) : 'none';
        }
        $contexts = [];
        $optimize = static function ($attrs, $tag, $attr, $context) use (&$contexts) {
            $contexts[] = [$tag, $context, array_keys((array) $attr)];
            return ['loading' => 'eager', 'fetchpriority' => 'high'];
        };
        ob_start();
        $widget->render_media(['url' => 'https://zz.example/a.png', 'width' => 10, 'height' => 20]);
        $plain = ob_get_clean();
        add_filter('wp_get_loading_optimization_attributes', $optimize, 10, 4);
        ob_start();
        $widget->render_media(['url' => 'https://zz.example/a.png', 'width' => 10, 'height' => 20]);
        $optimized = ob_get_clean();
        remove_filter('wp_get_loading_optimization_attributes', $optimize, 10);
        $say('image widget: custom caption width, loading attributes', [$media, $plain, $optimized, $contexts]);

        // The gallery widget's content.
        if (!class_exists('Zzdoubtg_Gallery')) {
            eval('class Zzdoubtg_Gallery extends WP_Widget_Media_Gallery { public function has($i) { return $this->has_content($i); } }');
        }
        $gallery = new Zzdoubtg_Gallery();
        $say('gallery widget content', array_map(static fn ($i) => $gallery->has($i), [['ids' => [999999991]], ['ids' => [$image, 999999991]], ['ids' => [$post]], ['ids' => [], 'attachment_id' => $image], ['ids' => [], 'url' => 'https://zz.example/a.png'], ['ids' => []], ['ids' => [$image]], ['ids' => [(string) $image]]]));

        // The recent posts widget: untitled, the_permalink, an unsafe link.
        $untitled = wp_insert_post(['post_title' => '', 'post_status' => 'publish', 'post_content' => 'zz', 'post_date' => '2030-01-01 00:00:00']);
        $made['posts'][] = $untitled;
        $ids[$untitled] = 'untitled';
        $perm = static fn ($link) => $link . '#zzpl';
        $bad = static fn ($link, $p) => $p->ID === $untitled ? 'http://zz.example/a"b<c> d' : $link;
        add_filter('the_permalink', $perm);
        add_filter('post_link', $bad, 10, 2);
        $only = static fn ($args) => ['post__in' => [$untitled]] + $args;
        add_filter('widget_posts_args', $only);
        ob_start();
        (new WP_Widget_Recent_Posts())->widget(['before_widget' => '', 'after_widget' => '', 'before_title' => '', 'after_title' => ''], ['number' => 1]);
        $recent = ob_get_clean();
        remove_filter('widget_posts_args', $only);
        remove_filter('the_permalink', $perm);
        remove_filter('post_link', $bad, 10);
        $say('recent posts widget link', preg_match('#<a [^>]*>[^<]*</a>#', (string) $recent, $m) ? $m[0] : $recent);

        // The RSS widget: the site's own address, text before "http", a feed with no title.
        $fetched = [];
        $feed = static function ($pre, $args, $url) use (&$fetched) {
            if (!str_contains($url, 'zzfeed.example')) {
                return $pre;
            }
            $fetched[] = $url;
            return ['headers' => ['content-type' => 'application/rss+xml'], 'body' => '<?xml version="1.0"?><rss version="2.0"><channel><title></title><link>http://zzfeed.example/</link><description>Zz feed description</description><item><title>Zz item</title><link>http://zzfeed.example/i</link></item></channel></rss>', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
        };
        add_filter('pre_http_request', $feed, 10, 3);
        $site = [];
        foreach ([site_url(), home_url('/'), 'junk http://zzfeed.example/feed2'] as $url) {
            ob_start();
            (new WP_Widget_RSS())->widget(['before_widget' => '', 'after_widget' => '', 'before_title' => '[', 'after_title' => ']'], ['url' => $url, 'items' => 1]);
            $out = (string) ob_get_clean();
            $site[] = $out === '' ? '' : (preg_match('#\[(.*?)\]#s', $out, $m) ? strip_tags($m[1]) . ' | ' . (preg_match('#class="rsswidget rss-widget-feed" href="([^"]*)"#', $out, $h) ? $h[1] : '') : substr($out, 0, 80));
        }
        remove_filter('pre_http_request', $feed, 10);
        delete_transient('feed_' . md5('http://zzfeed.example/feed2'));
        delete_transient('feed_mod_' . md5('http://zzfeed.example/feed2'));
        $say('rss widget', $site);
    }
    if ($part('deps')) {
        // Plugin dependencies: the API data for every dependency, names from it, the ajax check, the notices' texts.
        $pluginFiles = ['zzdoubtg2-a/zzdoubtg2-a.php' => ['ZZ A Header', 'zzdoubtg2-dep, zzdoubtg2-missing'], 'zzdoubtg2-dep/zzdoubtg2-dep.php' => ['ZZ Dep Header', ''], 'zzdoubtg2-none/zzdoubtg2-none.php' => ['ZZ None Header', ''], 'zzdoubtg2-ok/zzdoubtg2-ok.php' => ['ZZ Ok Header', 'zzdoubtg2-on'], 'zzdoubtg2-on/zzdoubtg2-on.php' => ['ZZ On Header', ''], 'zzdoubtg2-l1/zzdoubtg2-l1.php' => ['ZZ Loop One', 'zzdoubtg2-l2'], 'zzdoubtg2-l2/zzdoubtg2-l2.php' => ['ZZ Loop Two', 'zzdoubtg2-l1']];
        foreach ($pluginFiles as $file => [$name, $requires]) {
            wp_mkdir_p(WP_PLUGIN_DIR . '/' . dirname($file));
            file_put_contents(WP_PLUGIN_DIR . "/{$file}", "<?php\n/**\n * Plugin Name: {$name}\n * Version: 1.0\n" . ($requires === '' ? '' : " * Requires Plugins: {$requires}\n") . " */\n");
            $made['files'][] = WP_PLUGIN_DIR . "/{$file}";
            $made['files'][] = WP_PLUGIN_DIR . '/' . dirname($file);
        }
        wp_clean_plugins_cache(false);
        $activeFilter = static fn ($list) => array_values(array_unique([...array_filter((array) $list, static fn ($p) => !str_starts_with((string) $p, 'zzdoubtg2')), 'zzdoubtg2-on/zzdoubtg2-on.php']));
        add_filter('option_active_plugins', $activeFilter);
        $asked = [];
        $api = static function ($result, $action, $args) use (&$asked) {
            if ($action === 'plugin_information' && str_starts_with((string) ($args->slug ?? ''), 'zzdoubtg2')) {
                $asked[] = $args->slug;
                return (object) ['name' => 'API ' . $args->slug, 'slug' => $args->slug, 'version' => '9.9'];
            }
            return $result;
        };
        add_filter('plugins_api', $api, 10, 3);
        $transient = static fn () => false;
        add_filter('pre_site_transient_wp_plugin_dependencies_plugin_data', $transient);
        $stored = [];
        $store = static function ($value) use (&$stored) {
            $stored[] = array_map(static fn ($item) => is_array($item) ? array_intersect_key($item, array_flip(['name', 'Name', 'external', 'slug'])) : $item, array_filter((array) $value, static fn ($k) => str_starts_with((string) $k, 'zzdoubtg2'), ARRAY_FILTER_USE_KEY));
            return $value;
        };
        add_filter('pre_set_site_transient_wp_plugin_dependencies_plugin_data', $store);
        if (!class_exists('Zzdoubtg_Deps')) {
            eval('class Zzdoubtg_Deps extends WP_Plugin_Dependencies { public static function reset() { foreach (["plugins", "plugin_dirnames", "dependencies", "dependency_slugs", "dependent_slugs", "dependency_api_data", "dependency_filepaths", "circular_dependencies_pairs", "circular_dependencies_slugs"] as $n) { static::${$n} = null; } static::$initialized = false; } public static function call($m, ...$a) { return static::$m(...$a); } }');
        }
        $pagenowBefore = $GLOBALS['pagenow'] ?? null;
        $GLOBALS['pagenow'] = 'plugins.php';
        set_current_screen('plugins');
        Zzdoubtg_Deps::reset();
        WP_Plugin_Dependencies::initialize();
        $data = Zzdoubtg_Deps::call('get_dependency_api_data');
        $data = array_map(static fn ($item) => is_array($item) ? array_intersect_key($item, array_flip(['name', 'Name', 'external', 'slug'])) : $item, array_filter((array) $data, static fn ($k) => str_starts_with((string) $k, 'zzdoubtg2'), ARRAY_FILTER_USE_KEY));
        $timeouts = [];
        foreach (['dep', 'missing', 'l1', 'l2', 'on'] as $slug) {
            $timeouts[$slug] = [get_site_transient("wp_plugin_dependencies_plugin_timeout_zzdoubtg2-{$slug}"), abs((int) get_option("_site_transient_timeout_wp_plugin_dependencies_plugin_timeout_zzdoubtg2-{$slug}") - time() - 43200) < 30];
            delete_site_transient("wp_plugin_dependencies_plugin_timeout_zzdoubtg2-{$slug}");
        }
        $say('plugin dependencies: api data on the plugins screen', [$data, $asked, $stored, WP_Plugin_Dependencies::get_dependency_names('zzdoubtg2-a/zzdoubtg2-a.php'), $timeouts]);
        // The ajax check.
        wp_set_current_user($admin);
        $died = [];
        $dieFilter = static fn () => static function ($message) use (&$died) {
            throw new RuntimeException('zzdie');
        };
        add_filter('wp_doing_ajax', '__return_true');
        add_filter('wp_die_ajax_handler', $dieFilter, 99);
        $ajax = [];
        foreach (['zzdoubtg2-a', 'zzdoubtg2-none', 'zzdoubtg2-ok', '', 'zzdoubtg2-unknown', 'zzdoubtg2-dep', 'zzdoubtg2-l1'] as $slug) {
            $postBefore = $_POST;
            $requestBefore = $_REQUEST;
            $_POST = $_REQUEST = ['slug' => $slug, '_ajax_nonce' => wp_create_nonce('updates')];
            ob_start();
            try {
                WP_Plugin_Dependencies::check_plugin_dependencies_during_ajax();
            } catch (RuntimeException $e) {
            }
            $json = json_decode((string) ob_get_clean(), true);
            $_POST = $postBefore;
            $_REQUEST = $requestBefore;
            if (is_array($json['data'] ?? null) && isset($json['data']['activateUrl'])) {
                $json['data']['nonce for activate-plugin_'] = preg_match('/_wpnonce=([0-9a-f]+)/', $json['data']['activateUrl'], $n) ? wp_verify_nonce($n[1], 'activate-plugin_' . ($json['data']['plugin'] ?? '')) : null;
                $json['data']['activateUrl'] = preg_replace('/_wpnonce=[0-9a-f]+/', '_wpnonce={nonce}', str_replace($host, '{host}', $json['data']['activateUrl']));
            }
            $ajax[$slug] = $json;
        }
        remove_filter('wp_doing_ajax', '__return_true');
        remove_filter('wp_die_ajax_handler', $dieFilter, 99);
        $say('plugin dependencies: the ajax check', $ajax);
        // The notices' texts through gettext.
        $texts = [];
        $spy = static function ($translation, $text, $domain) use (&$texts) {
            if (str_contains($text, 'plugin') || str_contains($text, 'Plugin')) {
                $texts[] = $text;
            }
            return $translation;
        };
        add_filter('gettext', $spy, 10, 3);
        ob_start();
        WP_Plugin_Dependencies::display_admin_notice_for_unmet_dependencies();
        ob_get_clean();
        $all = static function ($translation, $text) use (&$texts) {
            $texts[] = 'circular: ' . $text;
            return $translation;
        };
        add_filter('gettext', $all, 10, 2);
        ob_start();
        WP_Plugin_Dependencies::display_admin_notice_for_circular_dependencies();
        $circular = ob_get_clean();
        remove_filter('gettext', $all, 10);
        remove_filter('gettext', $spy, 10);
        $say('plugin dependencies: notice texts', [array_values(array_unique($texts)), $circular]);
        $GLOBALS['pagenow'] = $pagenowBefore;
        $GLOBALS['current_screen'] = null;
        Zzdoubtg_Deps::reset();
        remove_filter('option_active_plugins', $activeFilter);
        remove_filter('plugins_api', $api, 10);
        remove_filter('pre_site_transient_wp_plugin_dependencies_plugin_data', $transient);
        remove_filter('pre_set_site_transient_wp_plugin_dependencies_plugin_data', $store);
        delete_site_transient('wp_plugin_dependencies_plugin_data');
        wp_set_current_user(0);
    }
    if ($part('health')) {
        require_once ABSPATH . 'wp-admin/includes/class-wp-site-health.php';
        $fired = 0;
        $count = static function ($tests) use (&$fired) {
            $fired++;
            return $tests;
        };
        add_filter('site_status_tests', $count);
        $tests = WP_Site_Health::get_tests();
        remove_filter('site_status_tests', $count);
        $shape = is_array($tests) ? array_map(static fn ($group) => array_map(static fn ($t) => array_map(static fn ($v) => is_string($v) ? $v : (is_array($v) ? 'callable' : $v), (array) $t), (array) $group), $tests) : $tests;
        $say('site health tests', [$shape, $fired, is_array($tests) ? $tests['async']['dotorg_communication']['async_direct_test'][0] === WP_Site_Health::get_instance() : null]);
        $scheduled = [];
        $none = static fn () => false;
        $intercept = static function ($pre, $event) use (&$scheduled) {
            $scheduled[] = [$event->hook, $event->schedule, $event->timestamp > time()];
            return true;
        };
        $health = WP_Site_Health::get_instance();
        add_filter('pre_get_scheduled_event', $none);
        add_filter('pre_schedule_event', $intercept, 10, 2);
        $health->maybe_create_scheduled_event();
        remove_filter('pre_get_scheduled_event', $none);
        remove_filter('pre_schedule_event', $intercept, 10);
        $say('site health scheduled check', $scheduled);
    }
} finally {
    wp_set_current_user($userBefore);
    $GLOBALS['wp_query'] = $queryBefore;
    $GLOBALS['wp_the_query'] = $queryBefore;
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            get_post_type($id) === 'attachment' ? wp_delete_attachment($id, true) : wp_delete_post($id, true);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
    foreach ($made['files'] as $file) {
        if (is_string($file) && str_contains($file, 'zzdoubtg') && is_file($file)) {
            unlink($file);
        }
    }
    foreach ($made['files'] as $file) {
        if (is_string($file) && str_contains($file, 'zzdoubtg') && is_dir($file)) {
            @rmdir($file);
        }
    }
    foreach ($made['options'] as $name => [$value]) {
        $value === '__zzdoubtg_absent__' ? delete_option($name) : update_option($name, $value);
    }
}
echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
