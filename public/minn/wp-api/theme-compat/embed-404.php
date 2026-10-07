<?php
/**
 * The embed card when the address embeds nothing: a word saying so and the
 * way to the site, with embed_content and the site's name.
 */

echo "<div class=\"wp-embed\">\n\t<p class=\"wp-embed-heading\">" . __('Oops! That embed cannot be found.') . "</p>\n\n\t<div class=\"wp-embed-excerpt\">\n\t\t<p>\n\t\t\t";
printf(__('It looks like nothing was found at this location. Maybe try visiting %s directly?'), '<strong><a href="' . esc_url(home_url()) . '">' . esc_html(get_bloginfo('name')) . '</a></strong>');
echo "\t\t</p>\n\t</div>\n\n\t";
do_action('embed_content');
echo "\n\t<div class=\"wp-embed-footer\">\n\t\t";
the_embed_site_title();
echo "\t</div>\n</div>\n";
