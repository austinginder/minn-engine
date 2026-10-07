<?php
/**
 * One post's embed card: its featured image (a wide one above the title, a
 * square one beside it), the title, the excerpt (the_excerpt_embed),
 * embed_content, and the footer with the site's name and the buttons
 * embed_content_meta prints.
 */

$minn_embed_thumbnail = Minn\Front\EmbedCard::thumbnail();
echo "\t<div ";
post_class('wp-embed');
echo ">\n\t\t";
_minn_embed_featured_image($minn_embed_thumbnail, 'rectangular');
echo "\n\t\t<p class=\"wp-embed-heading\">\n\t\t\t<a href=\"";
the_permalink();
echo "\" target=\"_top\">\n\t\t\t\t";
the_title();
echo "\t\t\t</a>\n\t\t</p>\n\n\t\t";
_minn_embed_featured_image($minn_embed_thumbnail, 'square');
echo "\n\t\t<div class=\"wp-embed-excerpt\">";
the_excerpt_embed();
echo "</div>\n\n\t\t";
do_action('embed_content');
echo "\n\t\t<div class=\"wp-embed-footer\">\n\t\t\t";
the_embed_site_title();
echo "\n\t\t\t<div class=\"wp-embed-meta\">\n\t\t\t\t";
do_action('embed_content_meta');
echo "\t\t\t</div>\n\t\t</div>\n\t</div>\n";
