<?php
/**
 * What WP-CLI's bundled commands reach through the runtime, where its own
 * feature tests found the engine apart from the reference (tests/tools/cli-features.php):
 * a post's categories (wp_set_post_categories, post_category on a save), a
 * draft's dates (edit_date), the slashed data wp_update_comment and
 * wp_insert_user take, a comment status set twice, the "$generic$" hash
 * through wp_check_password, a post reassigned on wp_delete_user, and
 * get_post handed an object without an ID. Same protocol as api-probe.php;
 * the probe's rows (zzclirt) are removed after.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'terms' => [], 'comments' => [], 'users' => []];
$warnings = [];
set_error_handler(static function ($number, $message) use (&$warnings) {
    $warnings[] = $message;
    return true;
}, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE);
try {
    // Categories: wp_set_post_categories by what it is handed.
    $term = wp_insert_term('zzclirt cat', 'category');
    $tid = is_array($term) ? (int) $term['term_id'] : 0;
    $made['terms'][] = $tid;
    $named = static fn ($list) => is_array($list) ? array_map(static fn ($t) => (int) $t === $tid ? 'T' : (int) $t, $list) : (is_wp_error($list) ? 'error' : $list);
    $terms = static fn ($id) => $named(wp_get_object_terms($id, 'category', ['fields' => 'ids']));
    register_post_type('zzclirt_t', ['taxonomies' => ['category']]);
    $inputs = ['a space' => [' '], 'a zero string' => ['0'], 'two blanks' => ['', ''], 'one blank' => [''], 'a zero' => [0], 'an empty list' => [], 'null' => null, 'false' => false, 'a blank string' => '', 'a space string' => ' ', 'a word' => ['abc'], 'the id' => $tid, 'the id as text' => (string) $tid, 'a comma list' => "{$tid},1", 'a negative' => [-5], 'an unknown id' => [999999], 'a blank and the id' => ['', $tid], 'a nested list' => [[$tid]]];
    foreach (['post', 'zzclirt_t'] as $type) {
        $p = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt cats', 'post_status' => 'publish', 'post_type' => $type, 'post_category' => [$tid]]);
        $out = [];
        foreach ($inputs as $name => $value) {
            foreach (['' => false, ', appended' => true] as $suffix => $append) {
                wp_set_post_categories($p, [$tid]);
                $out[$name . $suffix] = [$named(wp_set_post_categories($p, $value, $append)), $terms($p)];
            }
        }
        $say("wp_set_post_categories on a {$type}: what it answers, what the post has", $out);
    }

    // Categories on a save: an update, a new post, a new auto-draft.
    foreach (['post', 'zzclirt_t'] as $type) {
        $p = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt cats', 'post_status' => 'publish', 'post_type' => $type, 'post_category' => [$tid]]);
        $out = [];
        foreach (['a space' => [' '], 'a zero string' => ['0'], 'two blanks' => ['', ''], 'an empty list' => [], 'a zero string alone' => '0', 'a word' => ['abc'], 'a null in a list' => [null], 'a space and a blank' => [' ', ''], 'false' => false] as $name => $value) {
            wp_set_post_categories($p, [$tid]);
            wp_update_post(['ID' => $p, 'post_category' => $value]);
            $new = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt new', 'post_status' => 'publish', 'post_type' => $type, 'post_category' => $value]);
            $draft = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt draft', 'post_status' => 'auto-draft', 'post_type' => $type, 'post_category' => $value]);
            $out[$name] = [$terms($p), $terms($new), $terms($draft)];
        }
        $say("post_category on a {$type}: an update, a new post, a new auto-draft", $out);
    }
    $out = [];
    foreach (['auto-draft', 'draft', 'pending', 'trash', 'inherit', 'publish', 'private'] as $status) {
        $q = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt status', 'post_status' => $status, 'post_category' => [$tid]]);
        $had = $terms($q);
        $answer = $named(wp_set_post_categories($q, []));
        $news = [];
        foreach ([[], ['0'], [' ']] as $value) {
            $news[] = $terms($made['posts'][] = wp_insert_post(['post_title' => 'zzclirt status', 'post_status' => $status, 'post_category' => $value]));
        }
        wp_set_object_terms($q, [$tid], 'category');
        wp_update_post(['ID' => $q, 'post_category' => ['0']]);
        $out[$status] = [$had, $answer, $news, $terms($q)];
    }
    $say('categories by status: what a post had, none set, new posts given none, a zero or a space, an update given a zero', $out);

    $heard = [];
    $hear = static function ($id, $list, $tt, $taxonomy) use (&$heard, $named) {
        $heard[] = [$taxonomy, $named($list)];
    };
    add_action('set_object_terms', $hear, 10, 4);
    $out = [];
    foreach (['post', 'zzclirt_t'] as $type) {
        $p = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt saves', 'post_status' => 'publish', 'post_type' => $type, 'post_category' => [$tid]]);
        foreach (['wp_update_post naming no categories' => ['post_title' => 'zzclirt saves 2'], 'wp_update_post with an empty list' => ['post_category' => []]] as $name => $change) {
            $heard = [];
            wp_update_post(['ID' => $p] + $change);
            $out["{$type}: {$name}"] = [$heard, $terms($p)];
        }
        $heard = [];
        wp_insert_post(['ID' => $p, 'post_title' => 'zzclirt saves 3']);
        $out["{$type}: wp_insert_post with an ID and no categories"] = [$heard, $terms($p)];
        foreach (['none' => null, 'an empty list' => [], 'a zero string' => ['0']] as $name => $value) {
            $heard = [];
            $made['posts'][] = $n = wp_insert_post(['post_title' => 'zzclirt saves new', 'post_status' => 'publish', 'post_type' => $type] + ($value === null ? [] : ['post_category' => $value]));
            $out["{$type}: a new post given {$name}"] = [$heard, $terms($n)];
        }
    }
    $paged = static fn ($types) => array_merge($types, ['page']);
    add_filter('default_category_post_types', $paged);
    register_taxonomy_for_object_type('category', 'page');
    foreach (['none' => null, 'an empty list' => [], 'a zero string' => ['0']] as $name => $value) {
        $heard = [];
        $made['posts'][] = $n = wp_insert_post(['post_title' => 'zzclirt page', 'post_status' => 'publish', 'post_type' => 'page'] + ($value === null ? [] : ['post_category' => $value]));
        $out["page with categories, a new one given {$name}"] = [$heard, $terms($n)];
        $heard = [];
        wp_update_post(['ID' => $n, 'post_title' => 'zzclirt page 2']);
        $out["page with categories, then updated, given {$name}"] = $heard;
    }
    unregister_taxonomy_for_object_type('category', 'page');
    remove_filter('default_category_post_types', $paged);
    remove_action('set_object_terms', $hear, 10);
    $say('set_object_terms heard on saves, and the categories after', $out);

    $asked = [];
    $status = static function ($s) use (&$asked) {
        $asked[] = $s;
        return $s === 'auto-draft' ? 'publish' : $s;
    };
    $types = static function ($t) use (&$asked) {
        $asked[] = 'default_category_post_types';
        return $t;
    };
    $p = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt asked', 'post_status' => 'publish']);
    $q = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt asked', 'post_status' => 'auto-draft']);
    add_filter('get_post_status', $status);
    add_filter('default_category_post_types', $types);
    foreach ([[], [5], ['']] as $value) {
        wp_set_post_categories($p, $value);
    }
    $asked[] = 'then an auto-draft the filter publishes';
    $answer = wp_set_post_categories($q, []);
    remove_filter('get_post_status', $status);
    remove_filter('default_category_post_types', $types);
    $say('wp_set_post_categories: the status and the types it asks for', [$asked, $named($answer)]);
    unregister_post_type('zzclirt_t');

    // A draft's dates on an update: given a date, it keeps none unless edit_date says so (a draft never dated is dated when saved).
    $word = static function ($date) {
        if ($date === '0000-00-00 00:00:00' || in_array($date, ['2005-01-24 09:52:00', '2010-05-05 05:05:05', '2011-06-06 06:06:06', '2030-01-24 09:52:00', '2031-05-05 05:05:05'], true)) {
            return $date;
        }
        if (abs(strtotime($date) - current_time('timestamp')) < 120) {
            return 'now';
        }
        return abs(strtotime($date) - time()) < 120 ? 'now, GMT' : $date;
    };
    $changes = [
        'a date and publish' => ['post_date' => '2005-01-24 09:52:00', 'post_status' => 'publish'],
        'a date' => ['post_date' => '2005-01-24 09:52:00'],
        'a date and a GMT date' => ['post_date' => '2005-01-24 09:52:00', 'post_date_gmt' => '2005-01-24 09:52:00'],
        'a GMT date' => ['post_date_gmt' => '2005-01-24 09:52:00'],
        'a date, edit_date 0' => ['post_date' => '2005-01-24 09:52:00', 'edit_date' => 0],
        'a date, edit_date false' => ['post_date' => '2005-01-24 09:52:00', 'edit_date' => false],
        'a date, edit_date "x"' => ['post_date' => '2005-01-24 09:52:00', 'edit_date' => 'x'],
        'an ISO date, edit_date 1 and publish' => ['post_date' => '2005-01-24T09:52:00.000Z', 'edit_date' => 1, 'post_status' => 'publish'],
        'a title' => ['post_title' => 'zzclirt dated 2'],
        'a later date and future' => ['post_date' => '2030-01-24 09:52:00', 'post_status' => 'future'],
    ];
    foreach (['draft', 'pending', 'auto-draft', 'publish', 'private', 'future'] as $status) {
        $out = [];
        foreach (['no GMT date' => false, 'a GMT date' => true] as $gmtName => $gmt) {
            foreach ($changes as $name => $change) {
                $arr = ['post_title' => 'zzclirt dated', 'post_status' => $status, 'post_date' => $status === 'future' ? '2031-05-05 05:05:05' : '2010-05-05 05:05:05'];
                if ($gmt) {
                    $arr['post_date_gmt'] = $status === 'future' ? '2031-05-05 05:05:05' : '2011-06-06 06:06:06';
                }
                $d = $made['posts'][] = wp_insert_post($arr);
                $before = get_post($d);
                wp_update_post(['ID' => $d] + $change);
                $after = get_post($d);
                $out["{$gmtName}, given {$name}"] = [[$word($before->post_date), $word($before->post_date_gmt), $before->post_status], [$word($after->post_date), $word($after->post_date_gmt), $after->post_status]];
            }
        }
        $say("wp_update_post of a {$status} post: its dates and status before and after", $out);
    }
    $out = [];
    foreach ([-3600, -30, 30, 120, 3600] as $offset) {
        $gmt = gmdate('Y-m-d H:i:s', time() + $offset);
        $statuses = [];
        foreach ([['future', true], ['publish', true], ['future', false]] as [$status, $withGmt]) {
            $made['posts'][] = $f = wp_insert_post(['post_title' => 'zzclirt scheduled', 'post_status' => $status, 'post_date' => get_date_from_gmt($gmt)] + ($withGmt ? ['post_date_gmt' => $gmt] : []));
            $statuses[] = get_post_status($f);
        }
        $out[(string) $offset] = $statuses;
    }
    $made['posts'][] = $f = wp_insert_post(['post_title' => 'zzclirt scheduled', 'post_status' => 'future']);
    $say('future, publish, and future without a GMT date, by seconds from now; then future with no date', [$out, get_post_status($f)]);

    // wp_update_comment takes slashed data: the fields filtered slashed, the stored comment slashed beside them, stored unslashed.
    $p = $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt comments', 'post_status' => 'publish']);
    $c = $made['comments'][] = wp_insert_comment(wp_slash(['comment_post_ID' => $p, 'comment_content' => 'My\Comment', 'comment_approved' => 0]));
    $first = get_comment($c)->comment_content;
    $updated = wp_update_comment(wp_slash(['comment_ID' => $c, 'comment_content' => 'My\New\Comment']));
    clean_comment_cache($c);
    $say('a comment made and changed from slashed content', [$first, $updated, get_comment($c)->comment_content]);
    $updated = wp_update_comment(['comment_ID' => $c, 'comment_content' => 'Un\\\\slashed\\"x']);
    clean_comment_cache($c);
    $say('a comment changed from content already unslashed', [$updated, get_comment($c)->comment_content]);
    $c = $made['comments'][] = wp_insert_comment(['comment_post_ID' => $p, 'comment_content' => 'orig \\o', 'comment_author' => "O'Neil \\x", 'comment_approved' => 1, 'comment_author_url' => 'http://example.com/a', 'comment_agent' => "ag'ent"]);
    $heard = [];
    $listen = [];
    foreach (['pre_comment_author_name', 'pre_comment_content', 'pre_comment_user_agent', 'pre_comment_author_url', 'comment_save_pre', 'pre_comment_user_ip', 'pre_comment_author_email', 'pre_user_id'] as $filter) {
        $listen[$filter] = static function ($value) use ($filter, &$heard) {
            $heard[] = [$filter, $value];
            return $value;
        };
        add_filter($filter, $listen[$filter], 1);
    }
    $listen['data'] = static function ($data, $comment, $arr) use (&$heard) {
        $heard[] = ['wp_update_comment_data', $data['comment_content'], $data['comment_author'], $data['comment_agent'], $comment['comment_author'], $arr['comment_content'], $arr['comment_author'], $data['filtered'] ?? null];
        return $data;
    };
    add_filter('wp_update_comment_data', $listen['data'], 10, 3);
    $listen['edit'] = static function ($id, $data) use (&$heard) {
        $heard[] = ['edit_comment', $data['comment_content'] ?? null, $data['comment_author'] ?? null];
    };
    add_action('edit_comment', $listen['edit'], 10, 2);
    foreach (['slashed, with a quote' => wp_slash(['comment_ID' => $c, 'comment_content' => 'a\\b "q"']), 'unslashed, with markup' => ['comment_ID' => $c, 'comment_content' => '<b>b</b><script>x</script> c\\\\d'], 'slashed, an author too' => wp_slash(['comment_ID' => $c, 'comment_content' => 'z \\ <i>y</i>', 'comment_author' => 'Z\\z'])] as $name => $change) {
        $heard = [];
        $updated = wp_update_comment($change);
        clean_comment_cache($c);
        $stored = get_comment($c);
        $say("wp_update_comment {$name}: what its filters hear, then what is stored", [$heard, [$updated, $stored->comment_content, $stored->comment_author, $stored->comment_agent]]);
    }
    foreach ($listen as $filter => $callback) {
        remove_filter(in_array($filter, ['data', 'edit'], true) ? ($filter === 'data' ? 'wp_update_comment_data' : 'edit_comment') : $filter, $callback, in_array($filter, ['data', 'edit'], true) ? 10 : 1);
    }

    // kses on the content while it is filtered, for a comment whose own author may not post unfiltered HTML.
    $names = static function ($hook) {
        global $wp_filter;
        $out = [];
        foreach (isset($wp_filter[$hook]) ? $wp_filter[$hook]->callbacks : [] as $priority => $callbacks) {
            foreach ($callbacks as $callback) {
                $out[] = $priority . ':' . (is_string($callback['function']) ? $callback['function'] : 'closure');
            }
        }
        return $out;
    };
    $say('filters on comment_save_pre and pre_comment_content', [$names('comment_save_pre'), $names('pre_comment_content')]);
    $inside = null;
    $peek = static function ($value) use (&$inside, $names) {
        $inside = $names('pre_comment_content');
        return $value;
    };
    add_filter('pre_comment_content', $peek, 1);
    $subscriber = $made['users'][] = wp_insert_user(['user_login' => 'zzclirt_sub', 'user_email' => 'zzclirt_sub@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
    $out = [];
    foreach (['nobody' => [0, null, ''], 'an administrator' => [1, null, ''], 'nobody, given an administrator' => [0, 1, ''], 'an administrator, given nobody' => [1, 0, ''], 'a subscriber' => [$subscriber, null, ''], 'an unknown user' => [999999, null, ''], 'nobody, kses already on' => [0, null, 'wp_filter_kses'], 'nobody, post kses on' => [0, null, 'wp_filter_post_kses']] as $name => [$author, $givenUser, $already]) {
        $k = $made['comments'][] = wp_insert_comment(['comment_post_ID' => $p, 'comment_content' => 'orig', 'comment_approved' => 1, 'user_id' => $author]);
        if ($already !== '') {
            add_filter('pre_comment_content', $already);
        }
        $inside = null;
        wp_update_comment(['comment_ID' => $k, 'comment_content' => '<b>b</b><script>x</script><a href="http://e.com" onclick="y">l</a>'] + ($givenUser === null ? [] : ['user_id' => $givenUser]));
        $after = $names('pre_comment_content');
        if ($already !== '') {
            remove_filter('pre_comment_content', $already);
        }
        clean_comment_cache($k);
        $out[$name] = [get_comment($k)->comment_content, $inside, $after];
    }
    remove_filter('pre_comment_content', $peek, 1);
    $say('wp_update_comment of a comment by: the content stored, the content filters inside, and after', $out);

    $heard = [];
    $saved = static fn ($value) => $value . "'!";
    $listen = static function ($data, $comment, $arr) use (&$heard) {
        $heard[] = [$data['comment_content'], $arr['comment_content'], array_keys($data), array_keys($arr), array_keys($comment)];
        return $data;
    };
    add_filter('comment_save_pre', $saved, 50);
    add_filter('wp_update_comment_data', $listen, 10, 3);
    $k = $made['comments'][] = wp_insert_comment(['comment_post_ID' => $p, 'comment_content' => 'orig', 'comment_approved' => 1]);
    wp_update_comment(wp_slash(['comment_ID' => $k, 'comment_content' => "x'y", 'zzclirt_extra' => 'e']));
    remove_filter('comment_save_pre', $saved, 50);
    remove_filter('wp_update_comment_data', $listen, 10);
    clean_comment_cache($k);
    $say('wp_update_comment_data: the data after comment_save_pre, the change before it, and the keys of each', [$heard, get_comment($k)->comment_content]);

    // A comment status set to the one it has is no update.
    $k = $made['comments'][] = wp_insert_comment(['comment_post_ID' => $p, 'comment_content' => 'zzclirt status', 'comment_approved' => 0]);
    $heard = [];
    $actions = [];
    foreach (['wp_set_comment_status', 'transition_comment_status', 'comment_approved_comment', 'comment_unapproved_comment', 'wp_update_comment_count', 'clean_comment_cache', 'edit_comment', 'trashed_comment', 'spammed_comment', 'untrashed_comment', 'unspammed_comment'] as $action) {
        $actions[$action] = static function () use ($action, &$heard) {
            $heard[] = $action;
        };
        add_action($action, $actions[$action], 10, 0);
    }
    $out = [];
    foreach ([['approve', true], ['approve', true], ['approve', false], ['1', true], ['hold', true], ['0', true], ['hold', false], ['spam', true], ['spam', false], ['trash', true], ['trash', true], ['approve', true], ['nonsense', true]] as [$status, $wpError]) {
        $heard = [];
        $result = wp_set_comment_status($k, $status, $wpError);
        $out[] = [$status, $wpError, is_wp_error($result) ? [$result->get_error_code(), $result->get_error_message(), $result->get_error_data()] : $result, $heard, get_comment($k)->comment_approved];
    }
    foreach (['wp_trash_comment', 'wp_trash_comment', 'wp_untrash_comment', 'wp_untrash_comment', 'wp_spam_comment', 'wp_spam_comment', 'wp_unspam_comment', 'wp_unspam_comment'] as $function) {
        $heard = [];
        clean_comment_cache($k);
        $result = $function($k);
        clean_comment_cache($k);
        $out[] = [$function, $result, $heard, get_comment($k)->comment_approved];
    }
    foreach ($actions as $action => $callback) {
        remove_action($action, $callback, 10);
    }
    $say('wp_set_comment_status, then the trash and spam functions, each twice: the answer, the actions, the status', $out);

    // wp_insert_user takes slashed data: the password is hashed as given.
    $raw = "test\"user3b's\\pass\\!";
    $checks = static fn ($id) => [wp_check_password($raw, get_userdata($id)->user_pass, $id), wp_check_password(wp_slash($raw), get_userdata($id)->user_pass, $id)];
    $a = $made['users'][] = wp_insert_user(wp_slash(['user_login' => 'zzclirt_a', 'user_email' => 'zzclirt_a@example.com', 'user_pass' => $raw]));
    $out = ['wp_insert_user, slashed' => $checks($a)];
    $b = $made['users'][] = wp_insert_user(['user_login' => 'zzclirt_b', 'user_email' => 'zzclirt_b@example.com', 'user_pass' => $raw]);
    $out['wp_insert_user, as it is'] = $checks($b);
    wp_update_user(wp_slash(['ID' => $b, 'user_pass' => $raw]));
    clean_user_cache($b);
    $out['wp_update_user, slashed'] = $checks($b);
    $object = new stdClass();
    $object->ID = $b;
    $object->user_pass = wp_slash($raw . '2');
    wp_update_user($object);
    clean_user_cache($b);
    $out['wp_update_user, a plain object, slashed (the second password)'] = [wp_check_password($raw . '2', get_userdata($b)->user_pass), wp_check_password(wp_slash($raw . '2'), get_userdata($b)->user_pass)];
    $object = new stdClass();
    $object->user_login = 'zzclirt_c';
    $object->user_email = 'zzclirt_c@example.com';
    $object->user_pass = wp_slash($raw);
    $made['users'][] = $c3 = wp_insert_user($object);
    $out['wp_insert_user, a plain object, slashed'] = $checks($c3);
    $made['users'][] = $d = wp_create_user('zzclirt_d', wp_slash($raw), 'zzclirt_d@example.com');
    $out['wp_create_user, slashed'] = $checks($d);
    $say('a password with quotes and backslashes: whether it checks as given, and slashed', $out);

    $e = $made['users'][] = wp_insert_user(['user_login' => 'zzclirt_e', 'user_email' => 'zzclirt_e@example.com', 'user_pass' => 'first']);
    wp_insert_user(['ID' => $e, 'user_login' => 'zzclirt_e', 'user_email' => 'zzclirt_e@example.com', 'user_pass' => 'zzclirt plain']);
    clean_user_cache($e);
    $stored = get_userdata($e)->user_pass;
    $say('wp_insert_user updating the password: stored as given', [$stored === 'zzclirt plain', wp_check_password('zzclirt plain', $stored)]);
    $heard = [];
    $hashOrNot = static fn ($v) => is_string($v) && str_starts_with($v, '$') ? 'a hash' : $v;
    $listeners = [
        ['wp_set_password', static function ($password, $id, $old = null) use (&$heard, $hashOrNot) {
            $heard[] = ['wp_set_password', $hashOrNot($password), is_object($old) ? $hashOrNot($old->user_pass) : $old];
        }, 3],
        ['pre_user_login', static function ($v) use (&$heard) {
            $heard[] = 'pre_user_login';
            return $v;
        }, 1],
        ['wp_pre_insert_user_data', static function ($data, $update, $id, $userdata) use (&$heard, $hashOrNot) {
            $heard[] = ['wp_pre_insert_user_data', $hashOrNot($data['user_pass']), $hashOrNot($userdata['user_pass'] ?? null)];
            return $data;
        }, 4],
        ['send_password_change_email', static function ($send, $user, $userdata) use (&$heard, $hashOrNot) {
            $heard[] = ['send_password_change_email', $hashOrNot($user['user_pass']), $hashOrNot($userdata['user_pass'] ?? null)];
            return false;
        }, 3],
        ['profile_update', static function ($id, $old, $userdata) use (&$heard, $hashOrNot) {
            $heard[] = ['profile_update', $hashOrNot($userdata['user_pass'] ?? null)];
        }, 3],
        ['wp_update_user', static function ($id, $userdata) use (&$heard, $hashOrNot) {
            $heard[] = ['wp_update_user', $hashOrNot($userdata['user_pass'] ?? null)];
        }, 2],
    ];
    foreach ($listeners as [$hook, $callback, $args]) {
        add_filter($hook, $callback, 10, $args);
    }
    $out = [];
    wp_insert_user(['ID' => $e, 'user_login' => 'zzclirt_e', 'user_email' => 'zzclirt_e@example.com', 'user_pass' => 'zzclirt plain 2']);
    $out['wp_insert_user with an ID and a password'] = $heard;
    $heard = [];
    wp_update_user(['ID' => $e, 'user_pass' => "a'b"]);
    $out['wp_update_user with a new password'] = $heard;
    $heard = [];
    wp_update_user(['ID' => $e, 'user_pass' => get_userdata($e)->user_pass]);
    $out['wp_update_user with the stored hash'] = $heard;
    $heard = [];
    wp_update_user(['ID' => $e, 'user_pass' => '']);
    clean_user_cache($e);
    $out['wp_update_user with an empty password'] = [$heard, wp_check_password("a'b", get_userdata($e)->user_pass)];
    foreach ($listeners as [$hook, $callback]) {
        remove_filter($hook, $callback, 10);
    }
    $say('a password changed: what the save hooks hear (a hash or the text)', $out);

    // The profile fields: filtered slashed, the row and the meta stored unslashed.
    $s = "O'B\\c\"d";
    $heard = [];
    $listeners = [];
    foreach (['pre_user_login', 'pre_user_nicename', 'pre_user_email', 'pre_user_url', 'pre_user_nickname', 'pre_user_first_name', 'pre_user_last_name', 'pre_user_display_name', 'pre_user_description'] as $filter) {
        $listeners[] = [$filter, static function ($v) use ($filter, &$heard) {
            $heard[] = [$filter, $v];
            return $v;
        }, 1, 1];
    }
    $listeners[] = ['wp_pre_insert_user_data', static function ($data, $update, $id, $userdata) use (&$heard) {
        $heard[] = ['wp_pre_insert_user_data', array_diff_key($data, ['user_pass' => 1, 'user_registered' => 1, 'user_activation_key' => 1]), array_diff_key((array) $userdata, ['user_pass' => 1, 'ID' => 1, 'user_registered' => 1])];
        return $data;
    }, 10, 4];
    $listeners[] = ['insert_user_meta', static function ($meta) use (&$heard) {
        $heard[] = ['insert_user_meta', array_intersect_key($meta, ['nickname' => 1, 'first_name' => 1, 'last_name' => 1, 'description' => 1, 'locale' => 1])];
        return $meta;
    }, 10, 1];
    $listeners[] = ['user_register', static function ($id, $userdata) use (&$heard) {
        $heard[] = ['user_register', array_intersect_key((array) $userdata, ['first_name' => 1, 'display_name' => 1, 'description' => 1, 'zzclirt_meta' => 1, 'user_login' => 1])];
    }, 10, 2];
    $listeners[] = ['profile_update', static function ($id, $old, $userdata) use (&$heard) {
        $heard[] = ['profile_update', $old->display_name, array_intersect_key((array) $userdata, ['first_name' => 1, 'display_name' => 1, 'description' => 1, 'user_login' => 1, 'last_name' => 1])];
    }, 10, 3];
    foreach ($listeners as [$hook, $callback, $priority, $args]) {
        add_filter($hook, $callback, $priority, $args);
    }
    $stored = static function ($id) {
        clean_user_cache($id);
        $u = get_userdata($id);
        return [$u->user_login, $u->display_name, $u->first_name, $u->last_name, $u->nickname, $u->description, $u->user_nicename];
    };
    $f = $made['users'][] = wp_insert_user(wp_slash(['user_login' => "zzclirt_f'q", 'user_email' => 'zzclirt_f@example.com', 'user_pass' => $s, 'first_name' => $s, 'last_name' => $s, 'description' => $s, 'zzclirt_meta' => $s]));
    $say('wp_insert_user of slashed fields: what its hooks hear, then what is stored', [$heard, $stored($f)]);
    $heard = [];
    wp_update_user(wp_slash(['ID' => $f, 'first_name' => $s . '2']));
    $say('wp_update_user of a slashed field: what its hooks hear, then what is stored', [$heard, $stored($f)]);
    $heard = [];
    wp_insert_user(['ID' => $f, 'user_login' => get_userdata($f)->user_login, 'first_name' => $s . '3', 'display_name' => $s]);
    $say('wp_insert_user with an ID of unslashed fields: what is stored', $stored($f));
    $user = get_userdata($f);
    $user->first_name = $s . '4';
    $heard = [];
    wp_update_user($user);
    $say('wp_update_user of a user object: what its hooks hear, then what is stored', [$heard, $stored($f)]);
    foreach ($listeners as [$hook, $callback, $priority]) {
        remove_filter($hook, $callback, $priority);
    }

    // wp_delete_user hands the posts on to the account named, as a string too.
    require_once ABSPATH . 'wp-admin/includes/user.php';
    $bob = $made['users'][] = wp_insert_user(['user_login' => 'zzclirt_bob', 'user_email' => 'zzclirt_bob@example.com', 'user_pass' => wp_generate_password(), 'role' => 'author']);
    $sally = $made['users'][] = wp_insert_user(['user_login' => 'zzclirt_sally', 'user_email' => 'zzclirt_sally@example.com', 'user_pass' => wp_generate_password(), 'role' => 'editor']);
    for ($i = 0; $i < 3; $i++) {
        $made['posts'][] = wp_insert_post(['post_title' => 'zzclirt bob ' . $i, 'post_status' => 'publish', 'post_author' => $bob]);
    }
    $deleted = is_int($bob) && $bob > 3 && is_int($sally) ? wp_delete_user($bob, (string) $sally) : null;
    $query = new WP_Query(['author' => $sally, 'post_type' => 'post', 'post_status' => 'any', 'fields' => 'ids']);
    $say('wp_delete_user with the reassignee as a string: the answer, the posts they now have', [$deleted, count($query->posts), (int) count_user_posts($sally)]);

    // A role no one defined is stored as given (WP-CLI only warns): the account has no role it can name.
    $g = $made['users'][] = wp_insert_user(['user_login' => 'zzclirt_role', 'user_email' => 'zzclirt_role@example.com', 'user_pass' => wp_generate_password(), 'role' => 'subscriber']);
    $updated = wp_update_user(['ID' => $g, 'role' => 'zzclirt_banana']);
    clean_user_cache($g);
    $made['users'][] = $h = wp_insert_user(['user_login' => 'zzclirt_role2', 'user_email' => 'zzclirt_role2@example.com', 'user_pass' => wp_generate_password(), 'role' => 'zzclirt_banana']);
    $say('an undefined role on wp_update_user and wp_insert_user: the answer, the roles, the capabilities stored', [is_wp_error($updated) ? $updated->get_error_code() : $updated === $g, get_userdata($g)->roles, is_wp_error($h) ? $h->get_error_code() : get_userdata($h)->roles, is_int($h) ? get_user_meta($h, $GLOBALS['wpdb']->prefix . 'capabilities', true) : null]);

    // A "$generic$" hash (an application password's) is not one wp_check_password takes.
    $secret = 'PAYJLrq7WhMKzKJWZU1iEIVW';
    $fast = wp_fast_hash($secret);
    $heard = [];
    $listen = static function ($check, $password, $hash, $id) use (&$heard) {
        $heard[] = [$check, $id];
        return $check;
    };
    add_filter('check_password', $listen, 10, 4);
    $answers = [wp_check_password($secret, $fast, 2), wp_check_password($secret . 'x', $fast, 2), wp_check_password($secret, $fast), wp_password_needs_rehash($fast)];
    remove_filter('check_password', $listen, 10);
    $say('wp_check_password with a "$generic$" hash: the secret, another, no user; whether it needs a rehash; check_password heard', [$answers, $heard, wp_verify_fast_hash($secret, $fast)]);

    // get_post of an object without an ID is a post of none (WP-CLI's menu item delete cleans a child's cache this way); an array is no post.
    $out = [];
    $shape = static fn ($post) => is_object($post) ? [get_class($post), $post->ID, $post->post_title, $post->post_type, $post->post_status, $post->filter] : $post;
    foreach (['an object holding a post_id' => (object) ['post_id' => (string) $p], 'an object with only a title' => (object) ['post_title' => 'x'], 'an object with ID 0' => (object) ['ID' => 0, 'post_title' => 'z'], 'an array without an ID' => ['post_title' => 'x'], 'an empty array' => [], 'an array with ID 0' => ['ID' => 0, 'post_title' => 'y'], 'an array with ID 1' => ['ID' => 1], 'an object with an ID and a display filter' => (object) ['post_title' => 'g', 'filter' => 'display', 'ID' => 1]] as $name => $value) {
        $warnings = [];
        $post = get_post($value);
        $out[$name] = [$shape($post), $warnings];
    }
    $warnings = [];
    clean_post_cache((object) ['post_id' => (string) $p]);
    $out['clean_post_cache of an object holding a post_id: warnings'] = $warnings;
    $say('get_post of objects and arrays: the post, the warnings', $out);
} finally {
    restore_error_handler();
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
    foreach ($made['terms'] as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_term($id, 'category');
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 3) {
            wp_delete_user($id);
        }
    }
    if (post_type_exists('zzclirt_t')) {
        unregister_post_type('zzclirt_t');
    }
}
echo json_encode($log);
