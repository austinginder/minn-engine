<?php
/**
 * The embed template a post's /embed/ address (or ?embed=true) prints when
 * the theme has none of its own: the header, the post's card (or the 404
 * card), the footer; each part a theme may replace (header-embed.php,
 * embed-content.php, embed-404.php, footer-embed.php).
 */

get_header('embed');

if (have_posts()) :
    while (have_posts()) :
        the_post();
        get_template_part('embed', 'content');
    endwhile;
else :
    get_template_part('embed', '404');
endif;

get_footer('embed');
