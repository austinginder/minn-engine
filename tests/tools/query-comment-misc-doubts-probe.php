<?php
/**
 * The query, comment and misc doubts measured and settled (probe
 * query-comment-misc-doubts): archive and document titles (post format
 * archives, the taxonomy prefix, a day from m, a static front page), an old
 * slug's type guessed from the attachment and pagename vars; the comment
 * form's fields and the hooks around them, comments_popup_link's texts,
 * closed and protected posts, the personal data eraser's messages; the misc
 * helpers (edit_post_link, oEmbed data for a URL, list_files, signature
 * failures, wp_filesize, custom CSS by stylesheet name, user counts, opcache,
 * revision titles, wp_recursive_ksort, wp_reset_vars, wp_sprintf_l); copy_dir,
 * move_dir and directory invalidation through a recording filesystem; when the
 * maintenance check asks its filter. Every row it makes (zzqcm) is removed,
 * its files are in a temp folder, and the maintenance file lives for one call.
 */

$log = [];
$say = static function (string $section, array $rows) use (&$log): void {
    foreach ($rows as $label => $value) {
        $log[] = [$section . ': ' . $label, $value];
    }
};

// query
$say('query', (static function () {
    $out = [];
    $made = [];
    try {
        $page = wp_insert_post(['post_type' => 'page', 'post_title' => 'zzqcm front', 'post_status' => 'publish']);
        $made[] = $page;
        $front = static fn () => 'page';
        $pid = static fn () => $page;
        add_filter('pre_option_show_on_front', $front);
        add_filter('pre_option_page_on_front', $pid);
        query_posts(['page_id' => $page]);
        $out['front title'] = [is_front_page(), is_page(), wp_title('|', false)];
        remove_filter('pre_option_show_on_front', $front);
        remove_filter('pre_option_page_on_front', $pid);
        foreach ([['m' => '20240302'], ['year' => 2024], ['m' => '2024'], ['m' => '202403'], ['year' => 2024, 'monthnum' => 3, 'day' => 4]] as $q) {
            query_posts($q + ['post_type' => 'zzqcm_none']);
            $out['date title ' . json_encode($q)] = wp_title('|', false);
        }
        $ctx = [];
        $g = static function ($t, $text, $c) use (&$ctx) { if (str_contains((string) $c, 'archive title prefix')) { $ctx[] = [$text, $c]; } return $t; };
        add_filter('gettext_with_context', $g, 10, 3);
        query_posts(['post_format' => 'post-format-aside']);
        $out['format archive'] = [is_tax(), get_the_archive_title()];
        query_posts(['post_format' => 'post-format-gallery']);
        $out['format archive gallery'] = get_the_archive_title();
        register_taxonomy('zzqcm_tax', 'post', ['public' => true, 'labels' => ['singular_name' => 'Zz Kind']]);
        $t = wp_insert_term('zzqcm term', 'zzqcm_tax');
        $tid = is_array($t) ? $t['term_id'] : 0;
        query_posts(['zzqcm_tax' => 'zzqcm-term']);
        $out['tax archive'] = [is_tax(), get_the_archive_title()];
        remove_filter('gettext_with_context', $g, 10);
        $out['ctx'] = $ctx;
        if ($tid) { wp_delete_term($tid, 'zzqcm_tax'); }
        // old slug: an attachment and a page that moved
        $att = wp_insert_post(['post_type' => 'attachment', 'post_title' => 'zzqcm att', 'post_name' => 'zzqcm-att-new', 'post_status' => 'inherit', 'post_mime_type' => 'image/png']);
        $made[] = $att;
        add_post_meta($att, '_wp_old_slug', 'zzqcm-att-old');
        $post = wp_insert_post(['post_type' => 'post', 'post_title' => 'zzqcm post', 'post_name' => 'zzqcm-post-new', 'post_status' => 'publish']);
        $made[] = $post;
        add_post_meta($post, '_wp_old_slug', 'zzqcm-post-old');
        $seen = null;
        $catch = static function ($url) use (&$seen) { $seen = $url; return false; };
        add_filter('old_slug_redirect_url', $catch);
        foreach (['post' => ['name' => 'zzqcm-post-old'], 'attachment var' => ['name' => 'zzqcm-att-old', 'attachment' => 'zzqcm-att-old'], 'attachment var only' => ['attachment' => 'zzqcm-att-old'], 'pagename' => ['name' => 'zzqcm-post-old', 'pagename' => 'zzqcm-post-old']] as $k => $vars) {
            $seen = null;
            $GLOBALS['wp_query'] = new WP_Query();
            $GLOBALS['wp_query']->query_vars = $vars + $GLOBALS['wp_query']->fill_query_vars([]);
            $GLOBALS['wp_query']->set_404();
            wp_old_slug_redirect();
            $out['old slug ' . $k] = $seen === null ? null : str_replace([home_url(), (string) $att, (string) $post], ['{home}', '{att}', '{post}'], $seen);
        }
        remove_filter('old_slug_redirect_url', $catch);
    } finally {
        foreach ($made as $id) { if (is_int($id) && $id > 0) { wp_delete_post($id, true); } }
        wp_reset_query();
    }
    return $out;
})());

// post format archives
$say('post format archives', (static function () {
    $out = []; $made = [];
    foreach (['aside', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio', 'chat', 'standard', 'zzbogus'] as $f) {
        $slug = 'post-format-' . $f;
        if (!term_exists($slug, 'post_format')) { $t = wp_insert_term($slug, 'post_format', ['slug' => $slug]); if (is_array($t)) { $made[] = $t['term_id']; } }
        query_posts(['post_format' => $slug]);
        $out[$f] = [is_tax(), get_the_archive_title()];
    }
    $ctx = [];
    $g = static function ($t, $text, $c) use (&$ctx) { $ctx[] = [$text, $c]; return $t; };
    add_filter('gettext_with_context', $g, 10, 3);
    query_posts(['post_format' => 'post-format-aside']); get_the_archive_title();
    remove_filter('gettext_with_context', $g, 10);
    $out['ctx'] = $ctx;
    foreach ($made as $id) { wp_delete_term($id, 'post_format'); }
    wp_reset_query();
    return $out;
})());

// comments
$say('comments', (static function () {
    $out = []; $made = ['posts' => [], 'comments' => []];
    $prev = get_current_user_id();
    $heard = [];
    $listen = static function (string $hook) use (&$heard) { return static function (...$a) use (&$heard, $hook) { $heard[] = $hook; return $a[0] ?? null; }; };
    try {
        $open = wp_insert_post(['post_title' => 'zzqcm open', 'post_status' => 'publish', 'comment_status' => 'open']);
        $closed = wp_insert_post(['post_title' => 'zzqcm closed', 'post_status' => 'publish', 'comment_status' => 'closed', 'ping_status' => 'closed']);
        $locked = wp_insert_post(['post_title' => 'zzqcm locked', 'post_status' => 'publish', 'comment_status' => 'open', 'post_password' => 'pw']);
        $made['posts'] = [$open, $closed, $locked];
        // comment form: comment field last, signed out and in
        $last = static function ($f) { $c = $f['comment'] ?? null; unset($f['comment']); if ($c !== null) { $f['comment'] = $c; } return $f; };
        foreach (['signed out' => 0, 'signed in' => 1] as $who => $uid) {
            wp_set_current_user($uid);
            $keys = null;
            $spy = static function ($f) use (&$keys) { $keys = array_keys($f); $f['zzextra'] = '<p>zzqcm extra</p>'; return $f; };
            add_filter('comment_form_fields', $last, 9);
            add_filter('comment_form_fields', $spy, 11);
            $heard = [];
            foreach (['comment_form_before_fields', 'comment_form_after_fields'] as $h) { add_action($h, $f = $listen($h)); $fns[$h] = $f; }
            ob_start(); comment_form([], $open); $html = ob_get_clean();
            foreach ($fns as $h => $f) { remove_action($h, $f); }
            remove_filter('comment_form_fields', $last, 9); remove_filter('comment_form_fields', $spy, 11);
            $out["form $who"] = [$keys, $heard, str_contains($html, 'zzqcm extra'), preg_match_all('/<p class="comment-form-[a-z]+/', $html, $m) ? $m[0] : []];
        }
        wp_set_current_user(0);
        // comments_popup_link
        $pop = static function ($post, ...$args) { $GLOBALS['post'] = get_post($post); setup_postdata($GLOBALS['post']); ob_start(); comments_popup_link(...$args); return str_replace((string) $post, '{id}', preg_replace('/href="[^"]*"/', 'href="{x}"', ob_get_clean())); };
        $out['popup closed none false'] = $pop($closed);
        $out['popup closed none given'] = $pop($closed, false, false, false, 'zc', 'No way');
        $out['popup closed none empty'] = $pop($closed, false, false, false, '', '');
        $out['popup zero given only'] = $pop($open, 'Zero!', false, false);
        $out['popup locked'] = $pop($locked);
        $out['popup locked custom'] = $pop($locked, 'Z', 'O', 'M', 'zc');
        $c1 = wp_insert_comment(['comment_post_ID' => $open, 'comment_content' => 'zzqcm one', 'comment_approved' => 1, 'comment_author_email' => 'zzqcm@example.test', 'comment_author' => 'Zz']);
        $made['comments'][] = $c1;
        $out['popup one, one unset'] = $pop($open, 'Z', false, 'M');
        $out['popup one, defaults'] = $pop($open);
        wp_reset_postdata();
        // eraser: a refusal message with markup, and a row already anonymized
        $refuse = static fn () => '<b>zzqcm no</b>';
        add_filter('wp_anonymize_comment', $refuse);
        $out['eraser refusal'] = wp_comments_personal_data_eraser('zzqcm@example.test');
        remove_filter('wp_anonymize_comment', $refuse);
        $c2 = wp_insert_comment(['comment_post_ID' => $open, 'comment_content' => 'zzqcm two', 'comment_approved' => 1, 'comment_author_email' => 'zzqcm2@example.test', 'comment_author' => 'Anonymous', 'comment_author_IP' => '', 'comment_agent' => '', 'comment_author_url' => '', 'user_id' => 0]);
        $made['comments'][] = $c2;
        $out['eraser nothing to change'] = wp_comments_personal_data_eraser('zzqcm2@example.test');
        $out['eraser changed'] = wp_comments_personal_data_eraser('zzqcm@example.test');
        // notify: a note under wp_notes_notify
        $mails = [];
        $catch = static function ($r, $atts) use (&$mails) { $mails[] = $atts['subject']; return true; };
        add_filter('pre_wp_mail', $catch, 10, 2);
        $notesOn = static fn () => true; $notesOff = static fn () => false;
        $off = static fn () => '0';
        add_filter('pre_option_comments_notify', $off);
        $note = wp_insert_comment(['comment_post_ID' => $open, 'comment_content' => 'zzqcm note', 'comment_approved' => 0, 'comment_type' => 'note', 'user_id' => 1]);
        $made['comments'][] = $note;
        $res = [];
        foreach (['notes on' => $notesOn, 'notes off' => $notesOff, 'notes unset' => null] as $k => $f) {
            $mails = [];
            $f && add_filter('wp_notes_notify', $f);
            $r = wp_new_comment_notify_postauthor($note);
            $f && remove_filter('wp_notes_notify', $f);
            $res[$k] = [$r, count($mails)];
        }
        remove_filter('pre_option_comments_notify', $off);
        $mails = [];
        $res['comment, notify off'] = [wp_new_comment_notify_postauthor($c1), count($mails)];
        remove_filter('pre_wp_mail', $catch, 10);
        $out['notify a note'] = $res;
    } finally {
        wp_set_current_user($prev);
        foreach ($made['comments'] as $id) { if (is_int($id) && $id > 0) { wp_delete_comment($id, true); } }
        foreach ($made['posts'] as $id) { if (is_int($id) && $id > 0) { wp_delete_post($id, true); } }
    }
    return $out;
})());

// comment form
$say('comment form', (static function () {
    $out = []; $made = [];
    $prev = get_current_user_id();
    try {
        $open = wp_insert_post(['post_title' => 'zzqcm open', 'post_status' => 'publish', 'comment_status' => 'open']);
        $made[] = $open;
        $last = static function ($f) { $c = $f['comment'] ?? null; unset($f['comment']); if ($c !== null) { $f['comment'] = $c; } return $f; };
        $seq = [];
        $hooks = ['comment_form_before_fields', 'comment_form_after_fields', 'comment_form_field_comment', 'comment_form_field_author', 'comment_form_field_zzextra', 'comment_form_field_cookies'];
        $fns = [];
        foreach ($hooks as $h) { $fns[$h] = static function ($v = null) use (&$seq, $h) { $seq[] = $h; return $v; }; add_filter($h, $fns[$h]); }
        foreach (['out default' => [0, false], 'out last' => [0, true], 'in default' => [1, false], 'in last' => [1, true], 'out last bare' => [0, 'bare'], 'in last bare' => [1, 'bare']] as $k => [$uid, $isLast]) {
            wp_set_current_user($uid);
            $keys = null;
            $spy = static function ($f) use (&$keys, $isLast) { $keys = array_keys($f); if ($isLast !== 'bare') { $f['zzextra'] = '<p class="comment-form-zzextra">x</p>'; } return $f; };
            $isLast && add_filter('comment_form_fields', $last, 9);
            add_filter('comment_form_fields', $spy, 11);
            $seq = [];
            ob_start(); comment_form([], $open); $html = ob_get_clean();
            $isLast && remove_filter('comment_form_fields', $last, 9);
            remove_filter('comment_form_fields', $spy, 11);
            preg_match_all('/<p class="comment-form-[a-z]+/', $html, $m);
            $out[$k] = [$keys, $seq, $m[0]];
        }
        foreach ($fns as $h => $f) { remove_filter($h, $f); }
        wp_set_current_user(0);
        $GLOBALS['post'] = get_post($open); setup_postdata($GLOBALS['post']);
        $p = static function (...$a) use ($open) { ob_start(); comments_popup_link(...$a); return str_replace((string) $open, '{id}', preg_replace('/href="[^"]*"/', 'href="{x}"', ob_get_clean())); };
        $out['class'] = $p(false, false, false, 'zc');
        $attr = static fn () => 'data-zz="1"';
        add_filter('comments_popup_link_attributes', $attr);
        $out['attr'] = $p();
        $out['attr class'] = $p('Z', false, false, 'zc');
        remove_filter('comments_popup_link_attributes', $attr);
        $out['zero more'] = $p(false, 'O', 'M');
        $out['empty strings'] = $p('', '', '');
        wp_reset_postdata();
    } finally {
        wp_set_current_user($prev);
        foreach ($made as $id) { if (is_int($id) && $id > 0) { wp_delete_post($id, true); } }
    }
    return $out;
})());

// popup texts
$say('popup texts', (static function () {
    $made = []; $ids = [];
    $g = static function ($t, $text) use (&$ids) { $ids[] = $text; return $t; };
    try {
        $open = wp_insert_post(['post_title' => 'zzqcm open', 'post_status' => 'publish']);
        $closed = wp_insert_post(['post_title' => 'zzqcm closed', 'post_status' => 'publish', 'comment_status' => 'closed', 'ping_status' => 'closed']);
        $both = wp_insert_post(['post_title' => 'zzqcm both', 'post_status' => 'publish', 'comment_status' => 'closed', 'ping_status' => 'closed', 'post_password' => 'pw']);
        $pingOnly = wp_insert_post(['post_title' => 'zzqcm ping', 'post_status' => 'publish', 'comment_status' => 'closed', 'ping_status' => 'open']);
        $made = [$open, $closed, $both, $pingOnly];
        $out = [];
        foreach (['open' => $open, 'closed' => $closed, 'closed+pw' => $both, 'ping open' => $pingOnly] as $k => $id) {
            $GLOBALS['post'] = get_post($id); setup_postdata($GLOBALS['post']);
            $ids = [];
            add_filter('gettext', $g, 10, 2); add_filter('ngettext', $n = static function ($t, $s, $p) use (&$ids) { $ids[] = 'N:' . $s . '|' . $p; return $t; }, 10, 3);
            ob_start(); comments_popup_link(); $html = ob_get_clean();
            remove_filter('gettext', $g, 10);
            $out[$k] = [preg_replace('/href="[^"]*"/', 'href="{x}"', $html), array_values(array_filter($ids, static fn ($t) => stripos($t, 'comment') !== false || stripos($t, 'password') !== false))];
        }
        wp_reset_postdata();
        return $out;
    } finally {
        foreach ($made as $id) { if (is_int($id) && $id > 0) { wp_delete_post($id, true); } }
    }
})());

// misc
$say('misc', (static function () {
    $out = []; $made = [];
    $dir = sys_get_temp_dir() . '/zzqcm-' . getmypid();
    $prevUser = get_current_user_id();
    try {
        // edit_post_link default text through gettext
        $ids = [];
        $g = static function ($t, $text) use (&$ids) { if ($text === 'Edit This') { $ids[] = $text; } return $t; };
        wp_set_current_user(1);
        $p = wp_insert_post(['post_title' => 'zzqcm misc', 'post_status' => 'publish']);
        $made[] = $p;
        add_filter('gettext', $g, 10, 2);
        ob_start(); edit_post_link(null, '', '', $p); $html = ob_get_clean();
        remove_filter('gettext', $g, 10);
        $out['edit_post_link text'] = [$ids, str_contains($html, '>Edit This<')];
        // oembed data for a url
        $d = get_oembed_response_data_for_url(get_permalink($p), []);
        $d2 = get_oembed_response_data_for_url(get_permalink($p), ['width' => 50]);
        $out['oembed data for url'] = [gettype($d), is_object($d) || is_array($d) ? ((array) $d)['width'] ?? null : null, ((array) $d2)['width'] ?? null];
        // list_files
        @mkdir("$dir/sub/deep", 0777, true); @mkdir("$dir/empty"); @mkdir("$dir/.hiddendir");
        foreach (['a.txt', '.dot', 'sub/b.txt', 'sub/.dot2', 'sub/deep/c.txt', 'sub/x.log', 'x.log', '.hiddendir/h.txt'] as $f) { file_put_contents("$dir/$f", 'x'); }
        $norm = static function ($l) use ($dir) { if (!is_array($l)) { return $l; } $l = array_map(static fn ($f) => str_replace($dir, '{dir}', $f), $l); sort($l); return $l; };
        $out['list_files'] = $norm(list_files($dir));
        $out['list_files hidden'] = $norm(list_files($dir, 100, [], true));
        $out['list_files exclude x.log'] = $norm(list_files($dir, 100, ['x.log']));
        $out['list_files exclude sub/x.log'] = $norm(list_files($dir, 100, ['sub/x.log', 'deep']));
        $out['list_files levels 1'] = $norm(list_files($dir, 1));
        // verify_file_signature failure data
        $sig = verify_file_signature("$dir/a.txt", [base64_encode(str_repeat('z', 64)), 'notbase64!!', base64_encode('short')]);
        $data = is_wp_error($sig) ? $sig->get_error_data() : null;
        $out['verify failure'] = is_wp_error($sig) ? [$sig->get_error_code(), is_array($data) ? array_keys($data) : $data, is_array($data) ? array_diff_key($data, ['hash' => 1, 'php' => 1, 'sodium' => 1, 'keys' => 1]) : null, is_array($data) ? [gettype($data['keys'] ?? null), count((array) ($data['keys'] ?? [])), gettype($data['php'] ?? null), gettype($data['sodium'] ?? null), strlen((string) ($data['hash'] ?? ''))] : null] : $sig;
        $none = verify_file_signature("$dir/a.txt", []);
        $out['verify none'] = [$none->get_error_code(), $none->get_error_data()];
        // wp_filesize
        $sizes = [];
        $heard = 0; $after = static function ($s) use (&$heard) { $heard++; return $s; };
        add_filter('wp_filesize', $after);
        foreach (['zero' => 0, 'five' => 5, 'null' => null, 'string' => '7', 'false' => false] as $k => $v) {
            $heard = 0; $pre = static fn () => $v;
            add_filter('pre_wp_filesize', $pre);
            $sizes[$k] = [wp_filesize("$dir/a.txt"), $heard];
            remove_filter('pre_wp_filesize', $pre);
        }
        remove_filter('wp_filesize', $after);
        $out['wp_filesize'] = $sizes;
        // custom css post by a stylesheet name that is not a slug
        $css = wp_insert_post(['post_type' => 'custom_css', 'post_title' => 'Zz Qcm Theme', 'post_name' => 'zz-qcm-theme', 'post_status' => 'publish', 'post_content' => 'b{}']);
        $made[] = $css;
        $found = wp_get_custom_css_post('Zz Qcm Theme');
        $out['custom css post'] = [$found ? $found->post_name : null, (bool) wp_get_custom_css_post('zz-qcm-theme')];
        // user counts filtered off
        $offc = static fn () => false;
        add_filter('enable_live_network_counts', $offc);
        $out['user counts off'] = wp_maybe_update_user_counts();
        remove_filter('enable_live_network_counts', $offc);
        // opcache filter asked only for php
        $asked = [];
        $ask = static function ($v, $f) use (&$asked, $dir) { $asked[] = str_replace($dir, '{dir}', $f); return $v; };
        add_filter('wp_opcache_invalidate_file', $ask, 10, 2);
        file_put_contents("$dir/s.php", '<?php');
        $out['opcache'] = [wp_opcache_invalidate("$dir/a.txt"), wp_opcache_invalidate("$dir/s.php"), wp_opcache_invalidate("$dir/nope.php")];
        $out['opcache asked'] = $asked;
        remove_filter('wp_opcache_invalidate_file', $ask, 10);
        // revision title
        wp_update_post(['ID' => $p, 'post_content' => 'changed']);
        $revs = wp_get_post_revisions($p);
        $rid = array_key_first($revs);
        $ids = [];
        $g2 = static function ($t, $text) use (&$ids) { if (str_contains($text, 'Revision') || str_contains($text, 'Autosave') || str_contains($text, '@')) { $ids[] = $text; } return $t; };
        add_filter('gettext', $g2, 10, 2);
        $mask = static fn ($s) => is_string($s) ? str_replace([admin_url(), (string) $p, (string) $rid], ['{admin}', '{post}', '{rev}'], preg_replace('/[A-Z][a-z]+ \d{1,2}, \d{4} @ [\d:]+/', '{date}', $s)) : $s;
        $out['revision title current'] = $mask(wp_post_revision_title($p));
        $out['revision title rev'] = $mask(wp_post_revision_title($rid));
        $out['revision title no link'] = is_string(wp_post_revision_title($rid, false));
        $noEdit = static fn () => '';
        add_filter('get_edit_post_link', $noEdit);
        $out['revision title no edit link'] = $mask(wp_post_revision_title($p));
        remove_filter('get_edit_post_link', $noEdit);
        remove_filter('gettext', $g2, 10);
        $out['revision title msgids'] = array_values(array_unique($ids));
        // ksort
        $arr = ['b' => 1, 'a' => ['d' => 1, 'c' => 2]];
        $r = wp_recursive_ksort($arr);
        $out['ksort'] = [$r, $arr];
        // reset vars
        $_POST = ['zzqcm_a' => '', 'zzqcm_b' => 'post b']; $_GET = ['zzqcm_a' => 'get a', 'zzqcm_b' => 'get b', 'zzqcm_c' => 'get c'];
        wp_reset_vars(['zzqcm_a', 'zzqcm_b', 'zzqcm_c', 'zzqcm_d']);
        $out['reset vars'] = [$GLOBALS['zzqcm_a'] ?? 'unset', $GLOBALS['zzqcm_b'] ?? 'unset', $GLOBALS['zzqcm_c'] ?? 'unset', $GLOBALS['zzqcm_d'] ?? 'unset'];
        $_POST = $_GET = [];
        // sprintf_l
        $out['sprintf_l'] = [wp_sprintf_l('%l', []), wp_sprintf_l('%l', 'one'), wp_sprintf_l('%l tail', ['a', 'b']), wp_sprintf_l('%l tail', []), wp_sprintf_l('x %l', ['a']), wp_sprintf('%l and more', []), wp_sprintf('%s: %l!', 'L', ['a', 'b', 'c']), wp_sprintf('%l', 'solo')];
    } finally {
        wp_set_current_user($prevUser);
        foreach ($made as $id) { if (is_int($id) && $id > 0) { wp_delete_post($id, true); } }
        exec('rm -rf ' . escapeshellarg($dir));
    }
    return $out;
})());

// signatures
$say('signatures', (static function () {
    $f = tempnam(sys_get_temp_dir(), 'zzqcm'); file_put_contents($f, 'x');
    $keys = static fn () => [base64_encode(str_repeat('k', 32)), base64_encode('badkey'), 'not base64!', base64_encode(str_repeat('j', 32))];
    add_filter('wp_trusted_keys', $keys);
    $out = [];
    foreach (['two good sigs' => [base64_encode(str_repeat('z', 64)), base64_encode(str_repeat('y', 64))], 'one bad sig' => ['bad'], 'mixed' => [base64_encode(str_repeat('z', 64)), 'bad', base64_encode('x')]] as $k => $sigs) {
        $r = verify_file_signature($f, $sigs, 'zz.zip');
        $d = $r->get_error_data();
        $out[$k] = [$r->get_error_code(), $d['keys'] ?? null, $d['skipped_key'] ?? null, $d['skipped_sig'] ?? null];
    }
    // a real signature
    $pair = sodium_crypto_sign_keypair();
    $pub = base64_encode(sodium_crypto_sign_publickey($pair));
    add_filter('wp_trusted_keys', static fn ($k) => array_merge($k, [$pub]), 11);
    $real = base64_encode(sodium_crypto_sign_detached(hash_file('sha384', $f, true), sodium_crypto_sign_secretkey($pair)));
    $out['real'] = verify_file_signature($f, ['bad', $real], 'zz.zip');
    unlink($f);
    return $out;
})());

// filesystem
$say('filesystem', (static function () {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    $GLOBALS['zzqcm_fs'] = ['log' => [], 'fail' => []];
    $fs = new class () extends WP_Filesystem_Base {
        private function rec(string $op, array $args, $result) { $GLOBALS['zzqcm_fs']['log'][] = array_merge([$op], $args, ['=>', $result]); return $result; }
        private function ok(string $op, string $path): bool { return !in_array("$op $path", $GLOBALS['zzqcm_fs']['fail'], true); }
        public function dirlist($path, $include_hidden = true, $recursive = false) {
            $tree = ['/zz/src' => ['a.txt' => 'f', 'sub' => 'd', 'skipme' => 'd'], '/zz/src/sub' => ['b.txt' => 'f', 'c.txt' => 'f'], '/zz/src/skipme' => [], '/zz/dst' => ['x.php' => 'f', 'y.txt' => 'f', 'd' => 'd']];
            if ($recursive && $path === '/zz/dst') { $out = ['x.php' => ['name' => 'x.php', 'type' => 'f'], 'y.txt' => ['name' => 'y.txt', 'type' => 'f'], 'd' => ['name' => 'd', 'type' => 'd', 'files' => ['z.php' => ['name' => 'z.php', 'type' => 'f']]]]; return $this->rec('dirlist', [$path, $include_hidden, $recursive], array_keys($out)) ? $out : $out; }
            $list = $this->ok('dirlist', $path) ? ($tree[$path] ?? false) : false;
            $out = $list === false ? false : array_combine(array_keys($list), array_map(static fn ($n, $t) => ['name' => $n, 'type' => $t], array_keys($list), $list));
            return $this->rec('dirlist', [$path, $include_hidden, $recursive], $out === false ? false : array_keys($out)) === false ? false : $out;
        }
        public function exists($path) { return $this->rec('exists', [$path], $this->ok('exists', $path)); }
        public function is_dir($path) { return $this->rec('is_dir', [$path], $this->ok('is_dir', $path)); }
        public function is_file($path) { return $this->rec('is_file', [$path], $this->ok('is_file', $path)); }
        public function mkdir($path, $chmod = false, $chown = false, $chgrp = false) { return $this->rec('mkdir', [$path, $chmod], $this->ok('mkdir', $path)); }
        public function copy($s, $d, $o = false, $m = false) { return $this->rec('copy', [$s, $d, $o, $m], $this->ok('copy', $s)); }
        public function move($s, $d, $o = false) { return $this->rec('move', [$s, $d, $o], $this->ok('move', $s)); }
        public function delete($f, $r = false, $t = false) { return $this->rec('delete', [$f, $r, $t], $this->ok('delete', $f)); }
        public function chmod($f, $m = false, $r = false) { return $this->rec('chmod', [$f, $m, $r], true); }
    };
    defined('FS_CHMOD_DIR') || define('FS_CHMOD_DIR', 0755);
    defined('FS_CHMOD_FILE') || define('FS_CHMOD_FILE', 0644);
    $prev = $GLOBALS['wp_filesystem'] ?? null;
    $GLOBALS['wp_filesystem'] = $fs;
    $out = [];
    $run = static function (array $fail, callable $call) {
        $GLOBALS['zzqcm_fs'] = ['log' => [], 'fail' => $fail];
        $r = $call();
        return [is_wp_error($r) ? [$r->get_error_code(), $r->get_error_message(), $r->get_error_data()] : $r, $GLOBALS['zzqcm_fs']['log']];
    };
    $out['copy ok'] = $run([], fn () => copy_dir('/zz/src', '/zz/dst', ['skipme', 'sub/c.txt', 'a.txt/x']));
    $out['copy dest missing, made'] = $run(['exists /zz/dst/'], fn () => copy_dir('/zz/src', '/zz/dst', ['sub', 'skipme']));
    $out['copy dest missing, mkdir fails'] = $run(['exists /zz/dst/', 'mkdir /zz/dst/'], fn () => copy_dir('/zz/src', '/zz/dst'));
    $out['copy sub mkdir fails'] = $run(['is_dir /zz/dst/sub', 'mkdir /zz/dst/sub'], fn () => copy_dir('/zz/src', '/zz/dst', ['skipme']));
    $out['copy file fails'] = $run(['copy /zz/src/a.txt'], fn () => copy_dir('/zz/src', '/zz/dst', ['skipme']));
    $out['copy list fails'] = $run(['dirlist /zz/src', 'dirlist /zz/src/'], fn () => copy_dir('/zz/src', '/zz/dst'));
    $out['move: move fails, copy fails'] = $run(['exists /zz/dst', 'move /zz/src', 'copy /zz/src/a.txt'], fn () => move_dir('/zz/src', '/zz/dst'));
    $out['move: move fails, copy ok'] = $run(['exists /zz/dst', 'move /zz/src'], fn () => move_dir('/zz/src', '/zz/dst'));
    $out['move ok'] = $run(['exists /zz/dst'], fn () => move_dir('/zz/src', '/zz/dst'));
    $out['move: move fails, no dir, mkdir fails'] = $run(['exists /zz/dst', 'move /zz/src', 'is_dir /zz/dst', 'mkdir /zz/dst'], fn () => move_dir('/zz/src', '/zz/dst'));
    $out['move: move fails, no dir, made'] = $run(['exists /zz/dst', 'move /zz/src', 'is_dir /zz/dst'], fn () => move_dir('/zz/src', '/zz/dst'));
    $asked = [];
    $ask = static function ($v, $f) use (&$asked) { $asked[] = $f; return false; };
    add_filter('wp_opcache_invalidate_file', $ask, 10, 2);
    $out['invalidate directory'] = $run([], fn () => wp_opcache_invalidate_directory('/zz/dst'));
    $out['invalidate directory asked'] = $asked;
    $asked = [];
    $out['invalidate directory bad'] = [$run([], fn () => @wp_opcache_invalidate_directory('')), $run([], fn () => @wp_opcache_invalidate_directory(['x']))];
    remove_filter('wp_opcache_invalidate_file', $ask, 10);
    $GLOBALS['wp_filesystem'] = $prev;
    return $out;
})());

// maintenance
$say('maintenance', (static function () {
    $file = ABSPATH . '.maintenance';
    if (file_exists($file)) {
        return ['present' => true];
    }
    $out = [];
    $asked = [];
    $f = static function ($v, $up) use (&$asked) { $asked[] = time() - $up; return $v; };
    add_filter('enable_maintenance_mode', $f, 10, 2);
    try {
        foreach ([599, 600, 601, 0, -5] as $age) {
            $asked = [];
            file_put_contents($file, '<?php $upgrading = ' . (time() - $age) . ';');
            $r = wp_is_maintenance_mode();
            unlink($file);
            // The answer itself is WP-CLI's on the reference (it keeps maintenance off); when the filter is asked is not.
            $out[$age] = $asked;
        }
    } finally {
        if (file_exists($file)) { unlink($file); }
        remove_filter('enable_maintenance_mode', $f, 10);
    }
    return $out;
})());

// invalidate notice
$say('invalidate notice', (static function () {
    $heard = [];
    foreach (['doing_it_wrong_run', 'wp_trigger_error_run', 'deprecated_function_run'] as $h) { add_action($h, static function (...$a) use (&$heard, $h) { $heard[] = [$h, $a]; }, 10, 3); }
    $m = [];
    add_filter('gettext', static function ($t, $text) use (&$m) { if (str_contains($text, 'non-empty')) { $m[] = $text; } return $t; }, 10, 2);
    @wp_opcache_invalidate_directory('');
    return [$heard, $m];
})());

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), "\n";
