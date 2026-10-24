<?php
/**
 * Plugin Name: Minn test types
 * Description: Oracle-side registrations for what minn.json declares, so live parity can see zz_note, zz_entry and zz_topic. The engine never runs this file; it registers the same from the manifest (Runtime\DeclaredTypes).
 */

add_action('init', static function (): void {
    // The taxonomy first, as the engine registers declared ones: its rules then come before zz_entry's.
    register_taxonomy('zz_topic', ['zz_entry'], [
        'label' => 'Topics',
        'labels' => [
            'name' => 'Topics',
            'singular_name' => 'Topic',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'zz-topic',
        'hierarchical' => true,
        'rewrite' => ['slug' => 'zz-wiki/topic', 'with_front' => false],
    ]);
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
    register_post_type('zz_entry', [
        'label' => 'Entries',
        'labels' => [
            'name' => 'Entries',
            'singular_name' => 'Entry',
        ],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'zz-entry',
        'hierarchical' => false,
        'rewrite' => ['slug' => 'zz-wiki', 'with_front' => false],
        'has_archive' => 'zz-wiki',
        'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments', 'revisions'],
        'taxonomies' => [],
    ]);
});
