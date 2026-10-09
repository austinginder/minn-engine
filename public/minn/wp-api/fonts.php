<?php
/**
 * The font library's functions (probe fonts): font faces printed from the
 * theme or given fonts, collections registered with the library, the
 * fonts directory under uploads, and a font family's faces and files
 * removed with it.
 */

function wp_print_font_faces($fonts = [])
{
    $fonts = empty($fonts) ? WP_Font_Face_Resolver::get_fonts_from_theme_json() : $fonts;
    if (!empty($fonts)) {
        (new WP_Font_Face())->generate_and_print((array) $fonts);
    }
}

function wp_print_font_faces_from_style_variations()
{
    $fonts = WP_Font_Face_Resolver::get_fonts_from_style_variations();
    if (!empty($fonts)) {
        wp_print_font_faces($fonts);
    }
}

function wp_register_font_collection(string $slug, array $args)
{
    return WP_Font_Library::get_instance()->register_font_collection($slug, $args);
}

function wp_unregister_font_collection(string $slug)
{
    return WP_Font_Library::get_instance()->unregister_font_collection($slug);
}

/**
 * The reference registers its Google Fonts collection here, read from
 * wordpress.org. The engine talks to no wordpress.org address, so it
 * registers none; a site adds its own with wp_register_font_collection().
 */
function _wp_register_default_font_collections()
{
}

/** The fonts directory: the uploads directory with /fonts as its base and no dated subdirectory, through font_dir. */
function wp_font_dir($create_dir = true)
{
    add_filter('upload_dir', '_wp_filter_font_directory');
    $font_dir = $create_dir ? wp_upload_dir() : wp_get_upload_dir();
    remove_filter('upload_dir', '_wp_filter_font_directory');
    return $font_dir;
}

function _wp_filter_font_directory($font_dir)
{
    if (doing_filter('font_dir')) {
        return $font_dir;
    }
    $font_dir = ['path' => untrailingslashit($font_dir['basedir']) . '/fonts', 'url' => untrailingslashit($font_dir['baseurl']) . '/fonts', 'subdir' => '', 'basedir' => untrailingslashit($font_dir['basedir']) . '/fonts', 'baseurl' => untrailingslashit($font_dir['baseurl']) . '/fonts', 'error' => false];
    return apply_filters('font_dir', $font_dir);
}

/** A deleted font family takes its font faces with it. */
function _wp_after_delete_font_family($post_id, $post)
{
    if (!$post instanceof WP_Post || $post->post_type !== 'wp_font_family') {
        return;
    }
    foreach (get_posts(['post_type' => 'wp_font_face', 'post_parent' => (int) $post_id, 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'suppress_filters' => true]) as $face_id) {
        wp_delete_post((int) $face_id, true);
    }
}

/** A font face about to be deleted removes its file from the fonts directory. */
function _wp_before_delete_font_face($post_id, $post)
{
    if (!$post instanceof WP_Post || $post->post_type !== 'wp_font_face') {
        return;
    }
    $file = (string) get_post_meta((int) $post_id, '_wp_font_face_file', true);
    if ($file !== '') {
        wp_delete_file(path_join(wp_font_dir(false)['path'], $file));
    }
}
