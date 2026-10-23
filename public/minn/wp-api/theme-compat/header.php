<?php
/**
 * The header a classic theme without header.php gets: noted as deprecated,
 * then the document head (the profile link, the content type, the title, the
 * theme's stylesheet, the pingback address, wp_head) and the opening page,
 * with the site's name and tagline as its banner. A singular view queues the
 * comment reply script.
 */

_deprecated_file('Theme without header.php', '3.0.0', null, 'Please include a header.php template in your theme.');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<link rel="profile" href="https://gmpg.org/xfn/11" />
<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php bloginfo('charset'); ?>" />

<title><?php echo wp_get_document_title(); ?></title>

<link rel="stylesheet" href="<?php bloginfo('stylesheet_url'); ?>" media="screen" />
<link rel="pingback" href="<?php bloginfo('pingback_url'); ?>" />

<?php
if (is_singular()) {
    wp_enqueue_script('comment-reply');
}
?>


<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div id="page">

<div id="header" role="banner">
	<div id="headerimg">
		<h1><a href="<?php echo home_url('/'); ?>"><?php bloginfo('name'); ?></a></h1>
		<div class="description"><?php bloginfo('description'); ?></div>
	</div>
</div>
<hr />
