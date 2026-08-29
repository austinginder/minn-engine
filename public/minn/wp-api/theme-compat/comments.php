<?php
/**
 * The comments template a theme without comments.php gets: the heading,
 * the paged navigation, the list, and the form, in the reference's shape.
 */

$minnCount = (int) ($GLOBALS['wp_query']->comment_count ?? 0);
echo "\n<!-- You can start editing here. -->\n\n";
if ($minnCount > 0) {
    $minnTitle = '&#8220;' . get_the_title() . '&#8221;';
    $minnHeading = $minnCount === 1 ? 'One response to ' . $minnTitle : $minnCount . ' responses to ' . $minnTitle;
    echo "\t<h3 id=\"comments\">\n\t\t" . $minnHeading . "\t</h3>\n\n";
    echo "\t<div class=\"navigation\">\n\t\t<div class=\"alignleft\">" . (string) get_previous_comments_link() . "</div>\n\t\t<div class=\"alignright\">" . (string) get_next_comments_link() . "</div>\n\t</div>\n\n";
    echo "\t<ol class=\"commentlist\">\n\t";
    wp_list_comments();
    echo "\t</ol>\n\n";
    echo "\t<div class=\"navigation\">\n\t\t<div class=\"alignleft\">" . (string) get_previous_comments_link() . "</div>\n\t\t<div class=\"alignright\">" . (string) get_next_comments_link() . "</div>\n\t</div>\n\n";
} elseif (!comments_open() && !is_page()) {
    echo "\t<p class=\"nocomments\">Comments are closed.</p>\n";
}
comment_form();
