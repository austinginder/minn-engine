<?php
/**
 * What the security sweep of 2026-10-20 settled (docs/security-review-2026-10-20.md),
 * each as the reference does it: a protected post in the latest-posts block
 * (one sentence, in either place); the caption shortcode (an attribute's
 * caption through the post allowlist, ids as classes and once a page); a
 * nonce asks nonce_user_logged_out only for a visitor, and the front end's
 * cookie signs no one in to the blog admin; settings errors'
 * ids and classes escaped, a section's surrounding markup through the post
 * allowlist; create_post_autosave defines no DOING_AUTOSAVE; a draft another
 * user has locked is autosaved in the author's slot, not in place; comments
 * nested deep through kses; an audio cover's file named for its bytes. Same
 * protocol as api-probe.php; the probe's rows (zzsweep) are removed after.
 */

require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/image.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$made = ['posts' => [], 'users' => []];
try {
    // The latest-posts block and two protected posts, the newest on the site.
    $made['posts'][] = wp_insert_post(['post_title' => 'zzsweep secret', 'post_content' => '<!-- wp:paragraph --><p>zzsweep hidden body</p><!-- /wp:paragraph -->', 'post_status' => 'publish', 'post_password' => 'pw']);
    $made['posts'][] = wp_insert_post(['post_title' => 'zzsweep classic', 'post_content' => 'zzsweep hidden classic', 'post_status' => 'publish', 'post_password' => 'pw']);
    foreach (['full_post', 'excerpt'] as $mode) {
        $html = do_blocks('<!-- wp:latest-posts {"displayPostContent":true,"displayPostContentRadio":"' . $mode . '","postsToShow":2} /-->');
        $say("latest posts showing {$mode}: protected posts", [str_contains($html, 'hidden'), substr_count($html, 'This content is password protected.')]);
    }

    // The caption shortcode.
    $caption = static fn (array $attr, string $content = '<img src="a.jpg">'): string => img_caption_shortcode($attr + ['width' => 100], $content);
    $say('a caption attribute with markup the post allowlist refuses', $caption(['caption' => '<b onclick="x()">b</b><script>s()</script><a href="javascript:alert(1)">j</a><img src="i.png" onerror="e()"><iframe src="//x"></iframe><span style="color:red">c</span>', 'id' => 'attachment_5']));
    $say('a caption written as the content', do_shortcode('[caption width="100"]<img src="a.jpg"> <b onclick="x()">kept</b>[/caption]'));
    $say('a caption id that is not a class', $caption(['caption' => 'c', 'caption_id' => 'zz cap"<x>']));
    $ids = [];
    foreach ([['id' => 'attachment_5'], ['id' => 'attachment_5'], ['id' => 'attachment_5-2'], ['id' => 'shared'], ['id' => 'q', 'caption_id' => 'shared'], ['id' => 'q'], ['id' => 'a b"c']] as $attr) {
        $ids[] = preg_replace('/^<figure ([^>]*?)style.*$/s', '$1', $caption($attr + ['caption' => 'c']));
    }
    $say('ids given again on the page', $ids);

    // Nonces: the visitor's id is the only one nonce_user_logged_out is asked for.
    $asked = [];
    $map = static function ($uid, $action) use (&$asked) {
        $asked[] = [$uid, $action];
        return $action === 'zzsweep' ? 0 : $uid;
    };
    add_filter('nonce_user_logged_out', $map, 10, 2);
    $before = get_current_user_id();
    wp_set_current_user(0);
    $guest = wp_create_nonce('zzsweep');
    $visitor = $asked;
    $asked = [];
    wp_set_current_user(1);
    $say('a visitor\'s nonce: asked of the filter, then checked for user 1', [$visitor, wp_verify_nonce($guest, 'zzsweep'), wp_verify_nonce(wp_create_nonce('zzsweep'), 'zzsweep'), $asked]);
    remove_filter('nonce_user_logged_out', $map, 10);
    wp_set_current_user($before);

    // Settings screens.
    add_settings_error('zzsweep', 'a"b<c', 'Msg <em>x</em>', 'err"or');
    add_settings_error('zzsweep', 'plain', 'Two', 'updated');
    ob_start();
    settings_errors('zzsweep');
    $say('settings errors with quotes in their code and type', ob_get_clean());
    add_settings_section('zzsweep', 'Title <b>t</b>', '__return_null', 'zzsweep-page', ['before_section' => '<div class="%s" onclick="x()"><script>s()</script>', 'after_section' => '</div><img src=x onerror=y()>', 'section_class' => 'k"l']);
    ob_start();
    do_settings_sections('zzsweep-page');
    $say('a section\'s surrounding markup', ob_get_clean());

    // Autosaves: a slot saved alone, then a draft under another user's lock.
    $author = wp_insert_user(['user_login' => 'zzsweep_author', 'user_pass' => wp_generate_password(), 'role' => 'author', 'user_email' => 'zzsweep_a@example.com']);
    $editor = wp_insert_user(['user_login' => 'zzsweep_editor', 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => 'zzsweep_e@example.com']);
    $made['users'] = [$author, $editor];
    $published = $made['posts'][] = wp_insert_post(['post_title' => 'zzsweep published', 'post_content' => 'original', 'post_status' => 'publish', 'post_author' => 1]);
    wp_set_current_user(1);
    $slot = (new WP_REST_Autosaves_Controller('post'))->create_post_autosave(['ID' => $published, 'post_content' => 'changed', 'post_title' => 'zzsweep published']);
    $say('create_post_autosave: a slot, and DOING_AUTOSAVE after it', [is_int($slot) && $slot > 0, defined('DOING_AUTOSAVE')]);
    wp_update_post(['ID' => $published, 'post_content' => 'later write']);
    $say('a later write keeps its revision', count(wp_get_post_revisions($published)));
    $draft = $made['posts'][] = wp_insert_post(['post_title' => 'zzsweep draft', 'post_content' => 'original', 'post_status' => 'draft', 'post_author' => $author]);
    foreach (['no one' => null, 'the editor' => $editor, 'the author' => $author, 'the editor, an hour ago' => -1] as $label => $holder) {
        wp_update_post(['ID' => $draft, 'post_content' => 'original']);
        delete_post_meta($draft, '_edit_lock');
        if ($holder !== null) {
            update_post_meta($draft, '_edit_lock', ($holder === -1 ? time() - 3600 : time()) . ':' . ($holder === -1 ? $editor : $holder));
        }
        wp_set_current_user($author);
        $request = new WP_REST_Request('POST', "/wp/v2/posts/{$draft}/autosaves");
        $request->set_body_params(['content' => 'from the author', 'title' => 'zzsweep draft']);
        $response = rest_do_request($request);
        $kept = wp_get_post_autosave($draft, $author);
        $say("the author's autosave of their draft, locked by {$label}", [$response->get_status(), get_post($draft)->post_content, $kept ? $kept->post_content : null, wp_check_post_lock($draft) === $editor]);
        if ($kept) {
            wp_delete_post_revision($kept->ID);
        }
    }
    wp_set_current_user($before);

    // The front end's cookie signs a user in, except in the blog admin.
    $_COOKIE[LOGGED_IN_COOKIE] = wp_generate_auth_cookie(1, time() + 3600, 'logged_in');
    $outside = wp_validate_logged_in_cookie(false);
    set_current_screen('dashboard');
    $say('the front end\'s cookie in the blog admin, and outside it', [wp_validate_logged_in_cookie(false), $outside, is_blog_admin()]);
    unset($_COOKIE[LOGGED_IN_COOKIE], $GLOBALS['current_screen']);

    // Comments nested deep, through kses.
    foreach ([300, 3000] as $depth) {
        foreach (['wp_filter_kses', 'wp_kses_post'] as $filter) {
            $out = $filter(str_repeat('<!--', $depth) . '-->');
            $say("{$depth} comment openers and one close through {$filter}", [strlen($out), md5($out)]);
        }
    }

    // An audio file's cover, named for what its bytes are.
    add_post_type_support('attachment:audio', 'thumbnail');
    $image = imagecreatetruecolor(8, 6);
    $encode = static function (string $writer, int $colour) use ($image): string {
        imagefilledrectangle($image, 0, 0, 7, 5, $colour);
        ob_start();
        $writer($image);
        return (string) ob_get_clean();
    };
    $frames = str_repeat(pack('N', (0x7FF << 21) | (2 << 19) | (1 << 17) | (1 << 16) | (2 << 12) | (1 << 10) | (3 << 6)) . str_repeat("\0", 44), 60);
    $folder = wp_upload_dir()['path'];
    foreach (['a PNG named a GIF' => [$encode('imagepng', 11), 'image/gif'], 'a GIF named a PNG' => [$encode('imagegif', 12), 'image/png'], 'a JPEG named HTML' => [$encode('imagejpeg', 13), 'text/html'], 'a WebP' => [$encode('imagewebp', 14), 'image/webp'], 'a script named a PNG' => ['<script>alert(1)</script>', 'image/png']] as $label => [$bytes, $named]) {
        $body = "\x00" . $named . "\x00\x03\x00" . $bytes;
        $tag = 'APIC' . pack('N', strlen($body)) . "\x00\x00" . $body;
        $size = strlen($tag);
        $path = $folder . '/zzsweep-cover-' . count($made['posts']) . '.mp3';
        file_put_contents($path, "ID3\x03\x00\x00" . chr(($size >> 21) & 0x7F) . chr(($size >> 14) & 0x7F) . chr(($size >> 7) & 0x7F) . chr($size & 0x7F) . $tag . $frames);
        $audio = $made['posts'][] = wp_insert_attachment(['post_mime_type' => 'audio/mpeg', 'post_title' => 'zzsweep cover', 'post_status' => 'inherit'], $path);
        @wp_generate_attachment_metadata($audio, $path);
        $cover = (int) get_post_meta($audio, '_thumbnail_id', true);
        if ($cover > 0) {
            $made['posts'][] = $cover;
        }
        $say("a cover that is {$label}", $cover > 0 ? [pathinfo((string) get_attached_file($cover), PATHINFO_EXTENSION), get_post_mime_type($cover)] : null);
    }
} finally {
    foreach (array_reverse($made['posts']) as $id) {
        if (is_int($id) && $id > 0) {
            get_post_type($id) === 'attachment' ? wp_delete_attachment($id, true) : wp_delete_post($id, true);
        }
    }
    foreach ($made['users'] as $id) {
        if (is_int($id) && $id > 3) {
            wp_delete_user($id);
        }
    }
}
echo json_encode($log);
