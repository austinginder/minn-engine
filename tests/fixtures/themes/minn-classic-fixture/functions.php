<?php
add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	register_nav_menus( [ 'primary' => 'Primary' ] );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'minn-classic-fixture', get_stylesheet_uri(), [], '1.0' );
} );

add_filter( 'body_class', function ( $classes ) {
	$classes[] = 'classic-fixture';
	return $classes;
} );
