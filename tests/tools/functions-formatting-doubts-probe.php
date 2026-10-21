<?php
/**
 * The facade doubts of functions.php, formatting.php and pluggable.php,
 * each as measured on the reference (probe functions-formatting-doubts):
 * durations, file names, Apache modules, the dir-size cache, date_i18n,
 * weeks, admin notices, prefixed ids, temporary names, the referer field,
 * cache ids, wp_die's handlers and input, emoji styles, the font folder,
 * basic auth contexts, failed privacy requests, upload permissions and
 * enclosures; rel attributes, splitting, array paths, antispambot,
 * smilies, entities, ISO 8601, translations and emoji in mail; referer
 * checks, password hashing, author and moderator notices, the password
 * change notice, relative redirects and the auth cookie's security. Every
 * request is answered by a filter and no mail leaves; the probe's rows
 * (zzfnc) and files are removed after.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$host = (string) parse_url(home_url(), PHP_URL_HOST);
$unhost = static fn ($value) => json_decode(str_replace([$host, 'https://', 'http://'], ['{host}', '{s}://', '{s}://'], (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)), true);
$notices = [];
$wrong = static function ($fn, $msg) use (&$notices) {
    $notices[] = ['wrong', $fn, strip_tags((string) $msg)];
};
add_action('doing_it_wrong_run', $wrong, 10, 2);
add_filter('doing_it_wrong_trigger_error', '__return_false');
set_error_handler(static function ($no, $str) use (&$notices) {
    if ($no === E_USER_NOTICE || $no === E_USER_WARNING) {
        $notices[] = ['err', $no, strip_tags($str)];
        return true;
    }
    return false;
});
$catch = static function (callable $f) use (&$notices) {
    $notices = [];
    try {
        $r = $f();
    } catch (Throwable $e) {
        $r = 'THROW ' . get_class($e);
    }
    return [$r, $notices];
};
$mails = [];
$catchMail = static function ($pre, $atts) use (&$mails) {
    $mails[] = $atts;
    return true;
};
add_filter('pre_wp_mail', $catchMail, 10, 2);
$made = ['users' => [], 'posts' => [], 'comments' => [], 'files' => []];
$before = get_current_user_id();
$keep = ['uri' => $_SERVER['REQUEST_URI'] ?? null, 'host' => $_SERVER['HTTP_HOST'] ?? null, 'https' => $_SERVER['HTTPS'] ?? null, 'apache' => $GLOBALS['is_apache'] ?? null, 'search' => $GLOBALS['wp_smiliessearch'] ?? null, 'trans' => $GLOBALS['wpsmiliestrans'] ?? null, 'hasher' => $GLOBALS['wp_hasher'] ?? null];

try {
    // functions.php
    $say('durations', array_map('human_readable_duration', ['0:05:30', '1:00:00', '05:30', '00:00', '1:2:3', '-1:05', '100:00:00', '12:60', '0:00:00', ' 2:03 ', '1:01:01:01', '', 'abc', '00:00:05']));
    $say('existing file names', [_wp_check_existing_file_names('.jpg', ['-1x1.jpg', '.jpg']), _wp_check_existing_file_names('a.jpg', ['a-10x10.jpg']), _wp_check_existing_file_names('a.jpg', ['a-scaled.jpg']), _wp_check_existing_file_names('a', ['a-10x10']), _wp_check_existing_file_names('a.jpg', ['A-10X10.JPG']), _wp_check_existing_file_names('a.jpg', ['a-10x10.png'])]);
    $apache = [];
    foreach ([true, false] as $flag) {
        $GLOBALS['is_apache'] = $flag;
        $apache[] = [apache_mod_loaded('mod_rewrite'), apache_mod_loaded('mod_rewrite', true)];
    }
    $GLOBALS['is_apache'] = $keep['apache'];
    $say('apache modules', $apache);
    $say('dir size cache paths', array_map(static fn ($p) => $catch(static fn () => clean_dirsize_cache($p)), [5, '', [], null, '/zz/fnc']));
    $stamps = [];
    $dateFilter = static function ($date, $format, $stamp, $gmt) use (&$stamps) {
        $stamps[] = gettype($stamp);
        return $date;
    };
    add_filter('date_i18n', $dateFilter, 10, 4);
    $ny = static fn () => 'America/New_York';
    add_filter('pre_option_timezone_string', $ny);
    $dates = [];
    foreach ([['U', 1000000, false], ['U', 1000000, true], ['U-Y', 1000000, false], ['U', '1000000', false], ['U', 1000000.7, false], ['U', -5, false], ['Y-m-d H:i T', 1000000, false], ['Z', 1000000, true], ['O P T e', 1000000, true]] as [$f, $t, $g]) {
        $r = date_i18n($f, $t, $g);
        $dates[] = [$f, $r, gettype($r)];
    }
    $now = date_i18n('U');
    $dates[] = [gettype($now), abs((int) $now - current_time('timestamp')) < 5];
    date_i18n('Y', 'abc');
    date_i18n('Y', false, true);
    remove_filter('pre_option_timezone_string', $ny);
    remove_filter('date_i18n', $dateFilter, 10);
    $say('date_i18n', [$dates, $stamps]);
    $weeks = [];
    foreach (['abc', '', '1', 0, '7', -1, 'x1'] as $sow) {
        $weeks[] = array_map(static fn ($t) => gmdate('Y-m-d', $t), get_weekstartend('2024-03-13 10:00:00', $sow));
    }
    $say('week start and end', $weeks);
    $say('admin notices', array_map(static fn ($a) => $catch(static fn () => wp_get_admin_notice('Msg', $a)), [['type' => ' warning '], ['type' => 'a b'], ['attributes' => ['data-on' => true, 'data-off' => false, 'role' => 'alert', 0 => 'hidden', 5 => 'x', 'data-n' => 0, 'data-e' => '']], ['id' => 'zzid', 'dismissible' => true, 'additional_classes' => ['a', 'b c'], 'paragraph_wrap' => false], ['type' => 5], ['additional_classes' => 'str']]));
    $say('prefixed ids', array_map(static fn ($p) => $catch(static fn () => preg_replace('/\d+$/', 'N', (string) wp_unique_prefixed_id($p))), [5, [], 'zzfnc-', null, 1.5, true]));
    $names = [];
    foreach (['zzfile.txt', '', 'zz.tar.gz', 'a/b/zzpath.php', '.hidden'] as $n) {
        $t = wp_tempnam($n);
        $names[] = [(string) preg_replace(['/^[0-9a-f]{13}-/', '/[A-Za-z0-9]{6}(?=\.tmp$)/'], ['{uniqid}-', 'XXXXXX'], basename($t)), is_file($t)];
        @unlink($t);
    }
    $say('temporary names', $names);
    $fields = [];
    foreach (['/zz/?a=1&_wp_http_referer=%2Fx&b="<x>"', '/zz/?a=1&b=2', '/zz/"<>\'', '/zz/?_wp_http_referer=1'] as $uri) {
        $_SERVER['REQUEST_URI'] = $uri;
        $fields[] = wp_referer_field(false);
    }
    $_SERVER['REQUEST_URI'] = $keep['uri'];
    $say('referer field', $fields);
    $heard = [];
    $lastChanged = static function (...$a) use (&$heard) {
        $heard[] = [$a[0], count($a)];
    };
    add_action('wp_cache_set_last_changed', $lastChanged, 10, 3);
    wp_cache_delete('last_changed', 'zzfnc');
    wp_cache_get_last_changed('zzfnc');
    wp_cache_get_last_changed('zzfnc');
    remove_action('wp_cache_set_last_changed', $lastChanged, 10);
    $say('last changed told once', $heard);
    $say('non-cached ids', $catch(static fn () => _get_non_cached_ids(['abc', 2.5, 3, '4', 3, 0, -1, '5x', true, null, 999999991], 'zzfnc_group')));
    $sent = [];
    foreach (['status_header', 'nocache_headers'] as $h) {
        add_filter($h, static function ($v) use ($h, &$sent) {
            $sent[] = $h;
            return $v;
        });
    }
    $handlers = [];
    foreach (['_ajax_wp_die_handler', '_json_wp_die_handler', '_jsonp_wp_die_handler'] as $handler) {
        $sent = [];
        ob_start();
        $handler('zz msg', 'zz title', ['exit' => false, 'response' => 403]);
        $handlers[] = [$handler, ob_get_clean(), $sent];
    }
    $say('die handlers', $handlers);
    $error = new WP_Error('c1', 'm1', ['status' => 418]);
    $error->add('c1', 'm2');
    $error->add('c2', 'm3', 'd3');
    $say('die input', [_wp_die_process_input($error), _wp_die_process_input($error, '', ['code' => 'mine']), _wp_die_process_input('plain', '', ['code' => 'mine', 'response' => 404])]);
    $got = [];
    $recorder = static function ($m, $t, $a) use (&$got) {
        $got[] = [is_string($m) ? strip_tags($m) : $m, $t, is_array($a) ? $a : [$a]];
    };
    $pick = static fn () => $recorder;
    add_filter('wp_die_handler', $pick);
    add_filter('wp_die_ajax_handler', $pick);
    foreach ([['m', 404, 500], ['m', 404, []], ['m', 'T', 500], ['m', 404, ['response' => 402]]] as $call) {
        wp_die(...$call);
    }
    wp_redirect('/zz', 200);
    add_filter('wp_doing_ajax', '__return_true');
    $_REQUEST['_ajax_nonce'] = 'bad';
    check_ajax_referer('zzfnc');
    remove_filter('wp_doing_ajax', '__return_true');
    unset($_REQUEST['_ajax_nonce']);
    remove_filter('wp_die_ajax_handler', $pick);
    remove_filter('wp_die_handler', $pick);
    $say('wp_die arguments, a redirect that is not 3xx, a refused ajax nonce', $got);
    add_action('wp_print_styles', 'print_emoji_styles');
    wp_enqueue_emoji_styles();
    $say('emoji styles', [wp_style_is('wp-emoji-styles', 'enqueued'), has_action('wp_print_styles', 'print_emoji_styles'), wp_styles()->get_data('wp-emoji-styles', 'after')]);
    $seen = [];
    $late = static function ($u) use (&$seen) {
        $seen[] = [basename((string) $u['path']), $u['subdir'], basename((string) $u['basedir'])];
        return $u;
    };
    add_filter('upload_dir', $late, 99);
    $font = wp_get_font_dir();
    remove_filter('upload_dir', $late, 99);
    $say('font folder', [$seen, basename($font['path']), $font['subdir']]);
    $contexts = [];
    foreach ([[false, 'index.php'], [true, 'index.php'], [true, 'wp-login.php'], [false, 'wp-login.php']] as [$ajax, $page]) {
        $prevPage = $GLOBALS['pagenow'] ?? null;
        $GLOBALS['pagenow'] = $page;
        $af = static fn () => $ajax;
        add_filter('wp_doing_ajax', $af);
        $ctx = null;
        $cf = static function ($v, $c) use (&$ctx) {
            $ctx = $c;
            return $v;
        };
        add_filter('wp_is_site_protected_by_basic_auth', $cf, 10, 2);
        wp_is_site_protected_by_basic_auth();
        remove_filter('wp_is_site_protected_by_basic_auth', $cf, 10);
        remove_filter('wp_doing_ajax', $af);
        $GLOBALS['pagenow'] = $prevPage;
        $contexts[] = $ctx;
    }
    $say('basic auth contexts', $contexts);
    $request = wp_insert_post(['post_type' => 'user_request', 'post_status' => 'request-pending', 'post_title' => 'zzfnc@example.test', 'post_name' => 'export_personal_data', 'post_password' => 'zzfnc-key']);
    if (is_int($request) && $request > 0) {
        $made['posts'][] = $request;
        global $wpdb;
        $old = gmdate('Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS);
        $wpdb->update($wpdb->posts, ['post_modified_gmt' => $old, 'post_modified' => $old], ['ID' => $request]);
        clean_post_cache($request);
        wp_privacy_personal_data_cleanup_requests();
        clean_post_cache($request);
        $failed = get_post($request);
        $say('an old request failed', [$failed->post_status, $failed->post_password]);
    }
    $dir = sys_get_temp_dir() . '/zzfnc-up-' . getmypid();
    @mkdir($dir);
    chmod($dir, 0770);
    $folder = static fn ($u) => ['path' => $dir, 'url' => 'http://zz.example/up', 'subdir' => '', 'basedir' => $dir, 'baseurl' => 'http://zz.example/up', 'error' => false];
    add_filter('upload_dir', $folder);
    $upload = wp_upload_bits('zzfnc.txt', null, 'zz');
    remove_filter('upload_dir', $folder);
    $made['files'][] = $upload['file'];
    $made['files'][] = $dir;
    $say('uploaded file permissions', decoct(fileperms($upload['file']) & 0777));
    $asked = [];
    $answer = static function ($pre, $args, $url) use (&$asked) {
        $asked[] = $url;
        $types = ['a.mp3' => 'application/octet-stream', 'b.mp4' => 'text/html', 'c.txt' => 'audio/mpeg', 'd.ogg' => 'audio/ogg'];
        $type = str_contains($url, 'vid') ? 'video/mp4' : 'text/html';
        foreach ($types as $end => $t) {
            $type = str_ends_with($url, $end) ? $t : $type;
        }
        return ['headers' => ['content-type' => $type, 'content-length' => '123'], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
    };
    add_filter('pre_http_request', $answer, 10, 3);
    $enclosures = [];
    foreach (['see http://zzfnc.example/a.mp3 and http://zzfnc.example and http://zzfnc.example/b.mp4 and http://zzfnc.example/c.txt and http://zzfnc.example/d.ogg and https://zzfnc.example/', 'http://zzfnc.example/a.mp3 http://zzfnc.example/page2', 'http://zzfnc.example/vid http://zzfnc.example/page3'] as $content) {
        $post = wp_insert_post(['post_title' => 'zzfnc enclose', 'post_status' => 'publish', 'post_content' => 'x']);
        if (is_int($post) && $post > 0) {
            $made['posts'][] = $post;
            $asked = [];
            do_enclose($content, $post);
            $enclosures[] = [$asked, get_post_meta($post, 'enclosure')];
        }
    }
    remove_filter('pre_http_request', $answer, 10);
    $say('enclosures', $enclosures);

    // formatting.php
    $rels = [];
    $inner = static function ($t) use (&$rels) {
        $rels[] = _make_clickable_rel_attr('http://zz.example/');
        return $t;
    };
    $outer = static function ($t) use (&$rels) {
        $rels[] = _make_clickable_rel_attr('http://zz.example/');
        return apply_filters('zzfnc_inner', $t);
    };
    add_filter('zzfnc_inner', $inner);
    add_filter('comment_text', $outer);
    apply_filters('comment_text', 'x', null, []);
    remove_filter('comment_text', $outer);
    remove_filter('zzfnc_inner', $inner);
    $say('rel attributes in and under comment_text', $rels);
    $say('split by whitespace', array_map(static fn ($a) => _split_str_by_whitespace(...$a), [['abcd 0', 4], ['a b c', 1], ['0', 1], ['ab 0 cd', 2], ['ab cd 0', 3], ['', 3]]));
    $tree = ['a' => ['b' => 1, 2 => 'two', '' => 'empty'], 1 => 'one'];
    $say('array paths read', array_map(static fn ($p) => _wp_array_get($tree, $p, 'D'), [['a', 'b'], [['a']], [(object) []], [1.0], [true], [null], ['a', 2], ['a', '2'], ['a', ''], 'a', []]));
    $sets = [];
    foreach ([['x', 'y'], ['a', ['b']], ['a', 1.5], ['a', null], ['a', true], ['n', 3]] as $path) {
        $copy = $tree;
        _wp_array_set($copy, $path, 'V');
        $sets[] = $copy;
    }
    $say('array paths written', $sets);
    $forms = [];
    foreach ([0, 1] as $hex) {
        $kinds = [];
        for ($i = 0; $i < 60; $i++) {
            $out = antispambot('abcdefghij@zz.example', $hex);
            preg_match_all('/&#x[0-9a-f]+;|&#\d+;|%[0-9a-f]{2}|[^&%]/', $out, $m);
            foreach ($m[0] as $tok) {
                $kinds[str_starts_with($tok, '&#x') ? 'hex' : (str_starts_with($tok, '&#') ? 'decimal' : ($tok[0] === '%' ? 'escaped' : 'plain'))] = true;
            }
            $kinds['decodes ' . (html_entity_decode(rawurldecode($out)) === 'abcdefghij@zz.example' ? 'back' : 'wrong')] = true;
        }
        ksort($kinds);
        $forms[] = array_keys($kinds);
    }
    $say('antispambot forms', $forms);
    $on = static fn () => '1';
    add_filter('pre_option_use_smilies', $on);
    smilies_init();
    $smiles = [convert_smilies('hi :) there')];
    $GLOBALS['wp_smiliessearch'] = '';
    $smiles[] = convert_smilies('hi :) there');
    $GLOBALS['wp_smiliessearch'] = '/(?:\s|^)(:zzq:)(?:\s|$)/m';
    $GLOBALS['wpsmiliestrans'] = [':zzq:' => 'icon_smile.gif', ':)' => '🙂'];
    $smiles[] = convert_smilies('hi :) there :zzq: x <code> :zzq: </code>');
    $GLOBALS['wpsmiliestrans'] = [':zz1:' => 'a.svg', ':zz2:' => 'b.jpe', ':zz3:' => 'c.avif', ':zz4:' => 'd.webp', ':zz5:' => '😀', ':zz6:' => 'e.GIF', ':zz7:' => 'f.bmp', ':zz8:' => 'g.tif', ':zz9:' => 'h.png?x=1'];
    $smiles[] = array_map(static fn ($s) => translate_smiley([' ' . $s . ' ']), [':zz1:', ':zz2:', ':zz3:', ':zz4:', ':zz5:', ':zz6:', ':zz7:', ':zz8:', ':zz9:', ':none:']);
    remove_filter('pre_option_use_smilies', $on);
    $GLOBALS['wp_smiliessearch'] = $keep['search'];
    $GLOBALS['wpsmiliestrans'] = $keep['trans'];
    $say('smilies', $unhost($smiles));
    $say('htmlentities2', array_map('htmlentities2', ['&a; &ab; &abcdefg; &abcdefgh; &a1; &1a; &_a; &;', '&#1; &#12; &#123; &#1234; &#x41; &copy; &amp;amp;', '< é "\' a&b &a']));
    $say('time zone offsets', array_map('iso8601_timezone_to_offset', ['+05:30', '+0530', '+05', '-0530', '-05:30', 'Z', '+5', '05:00', '+05:00:00', '', '+1:00']));
    $say('ISO 8601 zones', array_map(static fn ($t) => iso8601_to_datetime('20240102T03:04:05+0100', $t), ['user', 'gmt', 'GMT', 'other', '', 'User']));
    $translated = [];
    $gettext = static function ($tr, $text) use (&$translated) {
        $translated[] = $text;
        return $tr;
    };
    add_filter('gettext', $gettext, 10, 2);
    wp_privacy_anonymize_data('text');
    wp_privacy_anonymize_data('longtext');
    $query = $GLOBALS['wp_query'];
    $wasEmbed = $query->is_embed;
    $query->is_embed = true;
    wp_embed_excerpt_more(' [&hellip;]');
    $query->is_embed = $wasEmbed;
    remove_filter('gettext', $gettext, 10);
    $say('translated texts', array_values(array_filter($translated, static fn ($t) => in_array($t, ['[deleted]', 'This content was deleted by the author.', 'Continue reading %s'], true))));
    $staticized = [];
    foreach ([["Content-Type: text/html\nContent-Type: text/plain"], ["Content-Type: text/plain\nContent-Type: text/html"], [['Content-Type: text/html', 'content-type: text/plain']], ["X: y\ncontent-type: text/html; charset=UTF-8"]] as [$headers]) {
        $mail = wp_staticize_emoji_for_email(['to' => 'a@b.c', 'subject' => 's', 'message' => 'smile 😀', 'headers' => $headers]);
        $staticized[] = str_contains($mail['message'], '<img');
    }
    $say('emoji in mail: the first content type', $staticized);

    // pluggable.php
    $_REQUEST['_wpnonce'] = wp_create_nonce(-1);
    $say('admin referer without an action', $catch(static fn () => check_admin_referer()));
    $_REQUEST['zzq'] = '';
    $_REQUEST['_ajax_nonce'] = wp_create_nonce('zzfnc');
    $say('ajax referer: an empty named argument', [check_ajax_referer('zzfnc', 'zzq', false), check_ajax_referer('zzfnc', false, false), check_ajax_referer('zzfnc', 'zznone', false)]);
    unset($_REQUEST['zzq'], $_REQUEST['_ajax_nonce'], $_REQUEST['_wpnonce']);
    $padded = wp_hash_password(' zzpw ');
    $plain = wp_hash_password('zzpw');
    $say('passwords hashed trimmed, checked as given', [wp_check_password('zzpw', $padded), wp_check_password(' zzpw ', $padded), wp_check_password(' zzpw ', $plain), wp_check_password('zzpw', wp_hash_password("\tzzpw\n"))]);
    $GLOBALS['wp_hasher'] = new class () {
        public function HashPassword($p)
        {
            return 'zzhash:' . $p;
        }
    };
    $say('a plugin hasher', wp_hash_password(' zz '));
    $GLOBALS['wp_hasher'] = $keep['hasher'];
    foreach (['author', 'none'] as $role) {
        $id = wp_insert_user(['user_login' => 'zzfnc_' . $role, 'user_pass' => wp_generate_password(), 'role' => $role === 'none' ? '' : $role, 'user_email' => "zzfnc_{$role}@example.test"]);
        if (is_wp_error($id)) {
            throw new RuntimeException($id->get_error_message());
        }
        $made['users'][$role] = $id;
    }
    foreach (['author', 'none'] as $role) {
        $post = wp_insert_post(['post_title' => 'zzfnc ' . $role, 'post_status' => 'publish', 'post_author' => $made['users'][$role], 'post_content' => 'x']);
        $made['posts'][] = $post;
        $made['comments'][$role] = wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => 'Zz Visitor', 'comment_author_email' => 'zzvisitor@example.test', 'comment_content' => 'zz', 'comment_approved' => 1]);
        foreach ([['', 'a@example.test', 'comment'], ['Zz "Q" Name', '', 'comment'], ['Zz N', 'b@example.test', 'note']] as $i => [$name, $email, $type]) {
            if ($role === 'author') {
                $made['comments']["{$role}{$i}"] = wp_insert_comment(['comment_post_ID' => $post, 'comment_author' => $name, 'comment_author_email' => $email, 'comment_content' => 'zz', 'comment_approved' => 1, 'comment_type' => $type]);
            }
        }
    }
    $notices2 = [];
    foreach (['author', 'author0', 'author1', 'author2'] as $key) {
        $mails = [];
        $r = wp_notify_postauthor($made['comments'][$key]);
        $notices2[] = [$r, $unhost(array_map(static fn ($m) => [$m['to'], $m['headers']], $mails))];
    }
    $say('author notices and their headers', $notices2);
    $mails = [];
    wp_set_current_user($made['users']['author']);
    $say('the author signed in is not told', [wp_notify_postauthor($made['comments']['author']), count($mails)]);
    wp_set_current_user($before);
    $mails = [];
    $say('an author who cannot read is not told', [wp_notify_postauthor($made['comments']['none']), count($mails)]);
    $mails = [];
    $moderation = static fn () => '1';
    add_filter('pre_option_moderation_notify', $moderation);
    add_filter('notify_moderator', '__return_false');
    $say('moderators filtered off', [wp_notify_moderator($made['comments']['author']), count($mails)]);
    remove_filter('notify_moderator', '__return_false');
    remove_filter('pre_option_moderation_notify', $moderation);
    $mails = [];
    $blogname = static fn () => 'Zz %s %d 100%';
    add_filter('pre_option_blogname', $blogname);
    $subjects = [];
    $subjectFilter = static function ($e) use (&$subjects) {
        $subjects[] = $e['subject'];
        return $e;
    };
    add_filter('wp_password_change_notification_email', $subjectFilter);
    $changed = $catch(static fn () => wp_password_change_notification(get_userdata($made['users']['author'])));
    remove_filter('wp_password_change_notification_email', $subjectFilter);
    remove_filter('pre_option_blogname', $blogname);
    $say('password change notice', [$changed[0], $subjects, array_map(static fn ($m) => $m['subject'], $mails)]);
    $redirects = [];
    foreach (['/zzdir/page', '/zzdir/', '/a/b/c?x=1', ''] as $uri) {
        $_SERVER['REQUEST_URI'] = $uri;
        $redirects[] = array_map(static fn ($l) => str_replace($host, '{host}', (string) wp_validate_redirect($l, 'FB')), ['foo/bar', './foo', '../foo', '?a=1', '#frag', '', 'a:b', '//' . $host . '/x', 'http://u@' . $host . '/', 'http://u@ser@' . $host . '/', 'http://' . $host . '@evil.example/', 'javascript:alert(1)', '\\\\evil.example', '/\\evil.example', 'http:///x']);
    }
    $_SERVER['REQUEST_URI'] = $keep['uri'];
    $say('redirects validated', $redirects);
    $_SERVER['HTTPS'] = 'on';
    $httpHome = static fn () => 'http://' . (string) parse_url(get_option('siteurl'), PHP_URL_HOST);
    add_filter('pre_option_home', $httpHome);
    $secure = [];
    $secureFilter = static function ($v) use (&$secure) {
        $secure[] = $v;
        return $v;
    };
    add_filter('secure_logged_in_cookie', $secureFilter);
    $minted = 0;
    $mintFilter = static function ($c) use (&$minted) {
        $minted++;
        return $c;
    };
    add_filter('auth_cookie', $mintFilter);
    add_filter('send_auth_cookies', '__return_false');
    wp_set_auth_cookie($made['users']['author'], false, '', 'zzfnctoken');
    remove_filter('send_auth_cookies', '__return_false');
    remove_filter('auth_cookie', $mintFilter);
    remove_filter('secure_logged_in_cookie', $secureFilter);
    remove_filter('pre_option_home', $httpHome);
    $_SERVER['HTTPS'] = $keep['https'];
    $say('auth cookies on an https request to an http home', [$secure, $minted]);
} finally {
    wp_set_current_user($before);
    remove_filter('pre_wp_mail', $catchMail, 10);
    restore_error_handler();
    $_SERVER['REQUEST_URI'] = $keep['uri'];
    $_SERVER['HTTPS'] = $keep['https'];
    foreach ($made['comments'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_comment($id, true);
        }
    }
    foreach ($made['posts'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_post($id, true);
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 0) {
            wp_delete_user($id);
        }
    }
    foreach ($made['files'] as $path) {
        is_dir($path) ? @rmdir($path) : @unlink($path);
    }
}

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
