<?php get_header(); ?>
<?php if ( have_posts() ) : ?>
	<ul class="post-list">
	<?php while ( have_posts() ) : the_post(); ?>
		<li <?php post_class(); ?>>
			<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
			<div class="excerpt"><?php the_excerpt(); ?></div>
		</li>
	<?php endwhile; ?>
	</ul>
	<?php the_posts_pagination( [ 'mid_size' => 1, 'screen_reader_text' => 'Posts navigation' ] ); ?>
<?php else : ?>
	<p class="nothing">Nothing found.</p>
<?php endif; ?>
<?php get_footer(); ?>
