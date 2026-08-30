<?php get_header(); ?>
<h1 class="search-title">Search: <?php echo esc_html( get_search_query() ); ?></h1>
<?php if ( have_posts() ) : ?>
	<ul class="post-list">
	<?php while ( have_posts() ) : the_post(); ?>
		<li <?php post_class(); ?>><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
	<?php endwhile; ?>
	</ul>
<?php else : ?>
	<p class="nothing">Nothing found.</p>
<?php endif; ?>
<?php get_footer(); ?>
