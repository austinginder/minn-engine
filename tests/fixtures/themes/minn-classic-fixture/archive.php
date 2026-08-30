<?php get_header(); ?>
<h1 class="archive-title"><?php the_archive_title(); ?></h1>
<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
<?php if ( have_posts() ) : ?>
	<ul class="post-list">
	<?php while ( have_posts() ) : the_post(); ?>
		<li <?php post_class(); ?>><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
	<?php endwhile; ?>
	</ul>
	<?php the_posts_pagination( [ 'mid_size' => 1 ] ); ?>
<?php endif; ?>
<?php get_footer(); ?>
