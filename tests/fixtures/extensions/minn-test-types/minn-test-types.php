<?php
/**
 * Plugin Name: Minn test types
 * Description: Oracle-side CPT so live parity can see zz_note. The engine never runs this file.
 */

add_action('init', static function (): void {
    register_post_type('zz_note', [
        'label' => 'Notes',
        'labels' => [
            'name' => 'Notes',
            'singular_name' => 'Note',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'zz-note',
        'hierarchical' => false,
        'has_archive' => false,
        'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments', 'revisions'],
        'taxonomies' => ['category', 'post_tag'],
    ]);
});
