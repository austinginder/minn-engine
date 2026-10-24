<?php

declare(strict_types=1);

use Minn\Runtime\DeclaredTypes;

/**
 * minn.json's "types" and "taxonomies" rows as the registration arguments a
 * plugin would pass (Runtime\DeclaredTypes), Minn on its own. What those
 * registrations then do is compared with WordPress in the declared-types suite.
 */
return [
    'a bare type row gets the labels, REST and supports a plugin\'s fixture registers' => static fn () => DeclaredTypes::typeArgs('zz_note', ['name' => 'Notes', 'singular' => 'Note', 'rest_base' => 'zz-note', 'taxonomies' => ['category', 'post_tag']]) === [
        'label' => 'Notes',
        'labels' => ['name' => 'Notes', 'singular_name' => 'Note'],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'zz-note',
        'hierarchical' => false,
        'has_archive' => false,
        'supports' => ['title', 'editor', 'author', 'thumbnail', 'excerpt', 'custom-fields', 'comments', 'revisions'],
        'taxonomies' => ['category', 'post_tag'],
    ],
    'the slug stands in for a missing name and base, and the name for a missing singular' => static function (): bool {
        $args = DeclaredTypes::typeArgs('zz_bare', []);
        return $args['label'] === 'zz_bare' && $args['labels']['singular_name'] === 'zz_bare' && $args['rest_base'] === 'zz_bare';
    },
    'an archive path stays a path, and true stays true' => static fn () => DeclaredTypes::typeArgs('w', ['has_archive' => 'wiki'])['has_archive'] === 'wiki'
        && DeclaredTypes::typeArgs('w', ['has_archive' => true])['has_archive'] === true,
    'a rewrite slug becomes the rewrite argument; an object passes as given; false turns rewrites off' => static fn () => DeclaredTypes::typeArgs('w', ['rewrite' => '/wiki/'])['rewrite'] === ['slug' => 'wiki']
        && DeclaredTypes::typeArgs('w', ['rewrite' => ['slug' => 'wiki', 'with_front' => false]])['rewrite'] === ['slug' => 'wiki', 'with_front' => false]
        && DeclaredTypes::typeArgs('w', ['rewrite' => false])['rewrite'] === false
        && !array_key_exists('rewrite', DeclaredTypes::typeArgs('w', [])),
    'a description and an icon pass through when given' => static fn () => DeclaredTypes::typeArgs('w', ['description' => 'D', 'icon' => 'dashicons-book'])['description'] === 'D'
        && DeclaredTypes::typeArgs('w', ['icon' => 'dashicons-book'])['menu_icon'] === 'dashicons-book',
    'a taxonomy row gets labels, REST, its hierarchy and rewrite, and nothing a type has' => static fn () => DeclaredTypes::taxonomyArgs('zz_topic', ['name' => 'Topics', 'singular' => 'Topic', 'rest_base' => 'zz-topic', 'hierarchical' => true, 'rewrite' => ['slug' => 'zz-wiki/topic', 'with_front' => false]]) === [
        'label' => 'Topics',
        'labels' => ['name' => 'Topics', 'singular_name' => 'Topic'],
        'public' => true,
        'show_in_rest' => true,
        'rest_base' => 'zz-topic',
        'hierarchical' => true,
        'rewrite' => ['slug' => 'zz-wiki/topic', 'with_front' => false],
    ],
];
