<?php
/**
 * Minn site theme: the one thing WordPress needs from PHP is the stylesheet
 * enqueue. Minn Engine links a block theme's style.css on its own and never
 * runs theme PHP, so everything else (the theme toggle, the reveal animation)
 * lives inline in the template parts where both stacks print it verbatim.
 */

add_action( 'wp_enqueue_scripts', static function (): void {
	wp_enqueue_style( 'minn-site-style', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
} );
