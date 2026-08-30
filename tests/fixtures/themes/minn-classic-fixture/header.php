<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
	<nav class="primary-nav">
		<?php wp_nav_menu( [ 'theme_location' => 'primary', 'container' => false, 'fallback_cb' => false ] ); ?>
	</nav>
</header>
<main id="content">
