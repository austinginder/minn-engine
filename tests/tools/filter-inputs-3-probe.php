<?php
/**
 * What media, formatting and the smaller helpers hand their filters (probe
 * filter-inputs-3), recorded as probe filter-inputs records them: the
 * gallery's styles, image sizes and srcset, loading attributes, attachment
 * lookups, texturize, entities, sprintf, nonces, unique file names, upload
 * bits, JSON schema keywords, cron lookups, transients, user post counts and
 * the date and meta query helpers. The rows and files the probe makes are
 * removed again.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$home = rtrim(home_url(), '/');
$host = (string) parse_url($home, PHP_URL_HOST);
$ids = [];
// Paths differ between the stacks (and name the machine): the uploads folder is masked.
$uploads = rtrim((string) wp_get_upload_dir()['basedir'], '/');
$describe = static function ($value) use (&$describe, $host, &$ids, $uploads) {
    if ($value instanceof WP_Post) {
        return "post:{$value->post_type}:{$value->post_name}";
    }
    if ($value instanceof WP_Term) {
        return "term:{$value->taxonomy}:{$value->slug}";
    }
    if ($value instanceof WP_Comment) {
        return 'comment:' . (array_search((int) $value->comment_ID, $ids, true) ?: 'other');
    }
    if ($value instanceof WP_User) {
        return 'user:' . $value->user_login;
    }
    if (is_object($value)) {
        return 'object:' . get_class($value);
    }
    if (is_array($value)) {
        return array_map($describe, $value);
    }
    if (is_int($value) && in_array($value, $ids, true)) {
        return '{' . array_search($value, $ids, true) . '}';
    }
    if (is_string($value)) {
        $value = str_replace($uploads, '{uploads}', $value);
        $value = (string) preg_replace('#https?://' . preg_quote($host, '#') . '#', '{home}', $value);
        foreach ($ids as $name => $id) {
            $value = (string) preg_replace('/\b' . $id . '\b/', '{' . $name . '}', $value);
        }
        return $value;
    }
    return $value;
};
$heard = [];
$listen = static function (array $hooks) use (&$heard, $describe): void {
    foreach ($hooks as $hook) {
        add_filter($hook, static function (...$args) use ($hook, &$heard, $describe) {
            $heard[] = [$hook, array_map($describe, $args)];
            return $args[0] ?? null;
        }, 10, 9);
    }
};
$run = static function (string $label, callable $call) use (&$heard, $say): void {
    $heard = [];
    $call();
    $say($label, $heard);
};

$made = [];
register_shutdown_function(static function () use (&$made): void {
    foreach (array_reverse($made) as [$kind, $id]) {
        if ($kind === 'file') {
            @unlink($id);
        } elseif (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    delete_transient('zz_fi3');
    delete_site_transient('zz_fi3');
});
$post = wp_insert_post(['post_title' => 'zz-fi3', 'post_name' => 'zz-fi3', 'post_status' => 'publish', 'post_content' => 'zz']);
if (!is_int($post) || $post <= 1) {
    fwrite(STDERR, "could not make the probe's post\n");
    exit(1);
}
$made[] = ['post', $post];
$ids['zz-fi3'] = $post;
$GLOBALS['post'] = get_post($post);
setup_postdata($GLOBALS['post']);

$listen(['use_default_gallery_style', 'gallery_style']);
$image = wp_insert_attachment(['post_title' => 'zz-fi3-image', 'post_name' => 'zz-fi3-image', 'post_mime_type' => 'image/png', 'post_status' => 'inherit'], 'zz-fi3.png', $post);
if (is_int($image) && $image > 1) {
    $made[] = ['post', $image];
    $ids['zz-fi3-image'] = $image;
}
$run('gallery', static function () use ($ids): void {
    gallery_shortcode(['ids' => (string) ($ids['zz-fi3-image'] ?? 0)]);
});

$listen(['editor_max_image_size', 'wp_image_resize_identical_dimensions', 'max_srcset_image_width', 'pre_wp_get_loading_optimization_attributes', 'wp_loading_optimization_force_header_contexts', 'pre_attachment_url_to_postid']);
$run('image helpers', static function (): void {
    image_constrain_size_for_editor(2000, 1000, 'large', 'display');
    image_resize_dimensions(100, 100, 100, 100, false);
    wp_calculate_image_srcset([1200, 600], home_url('/wp-content/uploads/zz.png'), ['width' => 1200, 'height' => 600, 'file' => 'zz.png', 'sizes' => ['medium' => ['file' => 'zz-300x150.png', 'width' => 300, 'height' => 150, 'mime-type' => 'image/png']]], 0);
    wp_get_loading_optimization_attributes('img', ['width' => 100, 'height' => 100], 'the_content');
    attachment_url_to_postid(home_url('/wp-content/uploads/zz-nothing.png'));
});

$listen(['run_wptexturize', 'no_texturize_tags', 'format_to_edit', 'pre_ent2ncr', 'wp_sprintf', 'nonce_life']);
$run('formatting', static function (): void {
    wptexturize('"zz" -- \'quoted\'', true);
    format_to_edit('zz <b>x</b>');
    ent2ncr('&hellip; &amp;');
    wp_sprintf('%s and %l', 'zz', ['a', 'b']);
    wp_create_nonce('zz-fi3');
});

$listen(['pre_wp_unique_filename_file_list', 'wp_json_schema_allowed_keywords', 'wp_next_scheduled', 'pre_count_many_users_posts', 'date_query_valid_columns', 'meta_query_find_compatible_table_alias']);
$run('helpers', static function (): void {
    wp_unique_filename(wp_get_upload_dir()['path'], 'zz-fi3-unique.txt');
    wp_get_json_schema_allowed_keywords();
    wp_next_scheduled('zz_fi3_nothing');
    count_many_users_posts([1]);
    new WP_Date_Query([['column' => 'post_date', 'after' => '2001-01-01']]);
    $meta = new WP_Meta_Query([['key' => 'zz_a', 'value' => '1'], ['key' => 'zz_b', 'value' => '2', 'compare' => '!=']]);
    $meta->get_sql('post', 'wp_posts', 'ID');
});

$listen(['pre_set_transient_zz_fi3', 'set_transient', 'setted_transient', 'pre_set_site_transient_zz_fi3', 'set_site_transient', 'setted_site_transient']);
$run('transients', static function (): void {
    set_transient('zz_fi3', 'x', 60);
    set_site_transient('zz_fi3', 'y', 60);
});

$listen(['wp_upload_bits']);
$run('upload bits', static function () use (&$made): void {
    $result = wp_upload_bits('zz-fi3-bits.txt', null, 'zz');
    if (is_array($result) && !empty($result['file'])) {
        $made[] = ['file', $result['file']];
    }
});

// Cases whose results count as much as what the filters hear: each case gets the filters' arguments from its own call only.
$cases = static function (string $label, array $calls) use (&$heard, $say): void {
    $results = [];
    foreach ($calls as $name => $call) {
        $heard = [];
        $result = $call();
        $results[] = [$name, $result, $heard];
    }
    $say($label, $results);
};

// The srcset width cap: sizes wider than it drop out unless they are the source, a falsy cap is none,
// and it is asked once the size is known, before the folder is checked.
$cap = null;
add_filter('max_srcset_image_width', static function ($width) use (&$cap) {
    return $cap ?? $width;
});
$srcset = static function (array $size, string $file, array $widths, string $metaFile = 'zz.png') use ($home) {
    $sizes = [];
    foreach ($widths as $width) {
        $sizes["w{$width}"] = ['file' => "zz-{$width}.png", 'width' => $width, 'height' => intdiv($width, 2), 'mime-type' => 'image/png'];
    }
    $result = wp_calculate_image_srcset($size, $home . '/wp-content/uploads/' . $file, ['width' => 3000, 'height' => 1500, 'file' => $metaFile, 'sizes' => $sizes]);
    return is_string($result) ? str_replace($home, '{home}', $result) : $result;
};
$wide = [300, 2048, 2560];
$cases('srcset cap', [
    'no sizes' => static fn () => $srcset([1200, 600], 'zz.png', []),
    'no width' => static fn () => $srcset([0, 600], 'zz.png', [300]),
    'another folder' => static fn () => $srcset([1200, 600], '2002/zz.png', [300], '2001/zz.png'),
    'wide sizes' => static fn () => $srcset([1200, 600], 'zz.png', $wide),
    'a wide source' => static fn () => $srcset([2560, 1280], 'zz-2560.png', $wide),
    'cap 0' => static function () use ($srcset, &$cap, $wide) {
        $cap = 0;
        return $srcset([1200, 600], 'zz.png', $wide);
    },
    'cap 500' => static function () use ($srcset, &$cap, $wide) {
        $cap = 500;
        return $srcset([1200, 600], 'zz.png', $wide);
    },
    'cap as a string' => static function () use ($srcset, &$cap) {
        $cap = '1000';
        return $srcset([1200, 600], 'zz.png', [300, 900, 2560]);
    },
    'cap false' => static function () use ($srcset, &$cap, $wide) {
        $cap = false;
        $result = $srcset([1200, 600], 'zz.png', $wide);
        $cap = null;
        return $result;
    },
]);

// Loading attributes: a plugin's array answers first, a caller's own decoding, fetchpriority and loading
// are kept, an element needs both dimensions, and the header contexts are the filter's list.
$pre = false;
$header = null;
add_filter('pre_wp_get_loading_optimization_attributes', static function ($value) use (&$pre) {
    return $pre;
});
add_filter('wp_loading_optimization_force_header_contexts', static function ($contexts) use (&$header) {
    return $header ?? $contexts;
});
$loading = static function (string $tag, array $attr, string $context, $answer = false, $contexts = null) use (&$pre, &$header): array {
    $pre = $answer;
    $header = $contexts;
    wp_high_priority_element_flag(true);
    $result = wp_get_loading_optimization_attributes($tag, $attr, $context);
    $pre = false;
    $header = null;
    return [$result, wp_high_priority_element_flag()];
};
$big = ['width' => 800, 'height' => 800];
$small = ['width' => 100, 'height' => 100];
$cases('loading optimization', [
    'an image in the content' => static fn () => $loading('img', $small, 'the_content'),
    'an iframe' => static fn () => $loading('iframe', $small, 'the_content'),
    'a div' => static fn () => $loading('div', $small, 'the_content'),
    'the template' => static fn () => $loading('img', $big + ['loading' => 'eager'], 'template'),
    'a header part' => static fn () => $loading('img', $big, 'template_part_header'),
    'a small header image' => static fn () => $loading('img', $small, 'get_header_image_tag'),
    'a header part, no size' => static fn () => $loading('img', [], 'template_part_header'),
    'an iframe in the header' => static fn () => $loading('iframe', $big, 'template_part_header'),
    'a widget' => static fn () => $loading('img', $big, 'widget_text_content'),
    'eager' => static fn () => $loading('img', $big + ['loading' => 'eager'], 'the_content'),
    'lazy' => static fn () => $loading('img', $big + ['loading' => 'lazy'], 'the_content'),
    'loading false' => static fn () => $loading('img', $big + ['loading' => false], 'the_content'),
    'loading null' => static fn () => $loading('img', $big + ['loading' => null], 'the_content'),
    'loading LAZY' => static fn () => $loading('img', $big + ['loading' => 'LAZY'], 'the_content'),
    'fetchpriority high' => static fn () => $loading('img', $big + ['fetchpriority' => 'high'], 'the_content'),
    'fetchpriority low' => static fn () => $loading('img', $big + ['fetchpriority' => 'low'], 'the_content'),
    'fetchpriority auto' => static fn () => $loading('img', $big + ['fetchpriority' => 'auto'], 'the_content'),
    'fetchpriority HIGH' => static fn () => $loading('img', $big + ['fetchpriority' => 'HIGH'], 'the_content'),
    'fetchpriority empty' => static fn () => $loading('img', $big + ['fetchpriority' => ''], 'the_content'),
    'fetchpriority high, eager' => static fn () => $loading('img', $big + ['fetchpriority' => 'high', 'loading' => 'eager'], 'the_content'),
    'fetchpriority low, lazy' => static fn () => $loading('img', $big + ['fetchpriority' => 'low', 'loading' => 'lazy'], 'the_content'),
    'fetchpriority low, eager' => static fn () => $loading('img', $big + ['fetchpriority' => 'low', 'loading' => 'eager'], 'the_content'),
    'fetchpriority auto, eager' => static fn () => $loading('img', $big + ['fetchpriority' => 'auto', 'loading' => 'eager'], 'the_content'),
    'fetchpriority auto, lazy' => static fn () => $loading('img', $big + ['fetchpriority' => 'auto', 'loading' => 'lazy'], 'the_content'),
    'fetchpriority high, no size' => static fn () => $loading('img', ['fetchpriority' => 'high'], 'the_content'),
    'lazy, no size' => static fn () => $loading('img', ['loading' => 'lazy'], 'the_content'),
    'width only' => static fn () => $loading('img', ['width' => 800], 'the_content'),
    'width zero' => static fn () => $loading('img', ['width' => 0, 'height' => 800], 'the_content'),
    'sizes as strings' => static fn () => $loading('img', ['width' => '800', 'height' => '800'], 'the_content'),
    'decoding sync' => static fn () => $loading('img', $big + ['decoding' => 'sync'], 'the_content'),
    'decoding empty' => static fn () => $loading('img', $big + ['decoding' => ''], 'the_content'),
    'an iframe with decoding' => static fn () => $loading('iframe', $big + ['decoding' => 'sync'], 'the_content'),
    'an iframe, eager' => static fn () => $loading('iframe', $big + ['loading' => 'eager'], 'the_content'),
    'an iframe, fetchpriority high' => static fn () => $loading('iframe', $big + ['fetchpriority' => 'high'], 'the_content'),
    'an iframe, fetchpriority low' => static fn () => $loading('iframe', $big + ['fetchpriority' => 'low'], 'the_content'),
    'an iframe, no size' => static fn () => $loading('iframe', [], 'the_content'),
    'the content as a header' => static fn () => $loading('img', $big, 'the_content', false, ['the_content' => true]),
    'a small image as a header' => static fn () => $loading('img', $small, 'the_content', false, ['the_content' => true]),
    'no header contexts' => static fn () => $loading('img', $big, 'template_part_header', false, []),
    'a header context set false' => static fn () => $loading('img', $big, 'template_part_header', false, ['template_part_header' => false]),
    'header contexts as a string' => static fn () => $loading('img', $big, 'template_part_header', false, 'zz'),
    'a plugin answers' => static fn () => $loading('img', $small, 'the_content', ['loading' => 'zz']),
    'a plugin answers empty' => static fn () => $loading('img', $small, 'the_content', []),
    'a plugin answers a string' => static fn () => $loading('img', $small, 'the_content', 'zz'),
    'a plugin answers true' => static fn () => $loading('img', $small, 'the_content', true),
]);

// Nonce lifetimes: the action as the caller gave it, a short lifetime moving the tick, and the
// nonce made under one lifetime checked under the same one.
$life = null;
add_filter('nonce_life', static function ($lifetime) use (&$life) {
    return $life ?? $lifetime;
});
$cases('nonce lifetime', [
    'made, no action' => static fn () => strlen(wp_create_nonce()),
    'made' => static fn () => strlen(wp_create_nonce('zz-fi3')),
    'checked' => static fn () => wp_verify_nonce(wp_create_nonce('zz-fi3'), 'zz-fi3'),
    'the tick' => static fn () => wp_nonce_tick() === ceil(time() / 43200),
    'the tick, an int action' => static fn () => wp_nonce_tick(5) === ceil(time() / 43200),
    'the tick, an hour' => static function () use (&$life) {
        $life = 3600;
        $result = wp_nonce_tick('zz-fi3') === ceil(time() / 1800);
        $life = null;
        return $result;
    },
    'checked, an hour' => static function () use (&$life) {
        $life = 3600;
        $result = wp_verify_nonce(wp_create_nonce('zz-fi3'), 'zz-fi3');
        $life = null;
        return $result;
    },
    'the tick, a lifetime as a string' => static function () use (&$life) {
        $life = '7200';
        $result = wp_nonce_tick('zz-fi3') === ceil(time() / 3600);
        $life = null;
        return $result;
    },
    'an hour makes another nonce' => static function () use (&$life) {
        $day = wp_create_nonce('zz-fi3-b');
        $life = 3600;
        $hour = wp_create_nonce('zz-fi3-b');
        $life = null;
        return $day !== $hour;
    },
]);

// Texturize: run_wptexturize asked once and again on a reset, a no holding for the rest of the
// request; the skipped elements and quiet shortcodes read on every text. Last, since the no holds.
$texturize = null;
$skipped = null;
add_filter('run_wptexturize', static function ($value) use (&$texturize) {
    return $texturize ?? $value;
});
add_filter('no_texturize_tags', static function ($tags) use (&$skipped) {
    return $skipped ?? $tags;
});
$listen(['no_texturize_shortcodes']);
$text = '"a" -- b & c <pre>"p" -- &</pre> <textarea>"t" -- &</textarea> <b>"b" -- &</b> [code]"s"[/code]';
$cases('texturize', [
    'reset' => static fn () => wptexturize($text, true),
    'again' => static fn () => wptexturize($text),
    'empty' => static fn () => wptexturize('', true),
    'zero' => static fn () => wptexturize('0', true),
    'null' => static fn () => wptexturize(null, true),
    'spaces' => static fn () => wptexturize('   ', true),
    'b skipped' => static function () use ($text, &$skipped) {
        $skipped = ['b'];
        return wptexturize($text);
    },
    'b skipped, reset' => static fn () => wptexturize($text, true),
    'nothing skipped' => static function () use ($text, &$skipped) {
        $skipped = [];
        $result = wptexturize($text);
        $skipped = null;
        return $result;
    },
    'off, no reset' => static function () use ($text, &$texturize) {
        $texturize = false;
        return wptexturize($text);
    },
    'off, reset' => static fn () => wptexturize($text, true),
    'off, after' => static fn () => wptexturize($text),
    'back on, reset' => static function () use ($text, &$texturize) {
        $texturize = null;
        return wptexturize($text, true);
    },
]);

echo json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
