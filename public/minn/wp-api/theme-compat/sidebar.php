<?php
/**
 * The sidebar a classic theme without sidebar.php gets: noted as deprecated,
 * then the widgets of the first sidebar when it has any. Without them, a
 * search form; on an error page, a listing or a later page of one, a note
 * on what is being browsed; the pages, the monthly archives and the
 * categories with their counts; and on the posts page or a page, the
 * bookmarks and a Meta box (register or the dashboard, log in or out).
 */

_deprecated_file('Theme without sidebar.php', '3.0.0', null, 'Please include a sidebar.php template in your theme.');

echo "\t<div id=\"sidebar\" role=\"complementary\">\n\t\t<ul>\n\t\t\t";
if (!dynamic_sidebar()) {
    echo "\t\t\t<li>\n\t\t\t\t";
    get_search_form();
    echo "\t\t\t</li>\n\n\t\t\t<!-- Author information is disabled per default. Uncomment and fill in your details if you want to use it.\n"
        . "\t\t\t<li><h2>Author</h2>\n\t\t\t<p>A little something about you, the author. Nothing lengthy, just an overview.</p>\n\t\t\t</li>\n\t\t\t-->\n\n";
    if (is_404() || is_category() || is_day() || is_month() || is_year() || is_search() || is_paged()) {
        echo "\t\t\t\t\t\t\t<li>\n\n";
        $minnHome = '<a href="' . home_url('/') . '">' . get_bloginfo('name') . '</a>';
        $minnNote = match (true) {
            is_404() => null,
            is_category() => 'You are currently browsing the archives for the ' . single_cat_title('', false) . ' category.',
            is_day() => 'You are currently browsing the ' . $minnHome . ' blog archives for the day ' . get_the_time('l, F jS, Y') . '.',
            is_month() => 'You are currently browsing the ' . $minnHome . ' blog archives for ' . get_the_time('F, Y') . '.',
            is_year() => 'You are currently browsing the ' . $minnHome . ' blog archives for the year ' . get_the_time('Y') . '.',
            is_search() => 'You have searched the ' . $minnHome . ' blog archives for <strong>&#8216;' . get_search_query() . '&#8217;</strong>. If you are unable to find anything in these search results, you can try one of these links.',
            !empty($_GET['paged']) => 'You are currently browsing the ' . $minnHome . ' blog archives.',
            default => '',
        };
        echo match ($minnNote) {
            null => "\t\t\t\t\t\t\t\t",
            '' => "\t\t\t\t\t",
            default => "\t\t\t\t\t\t\t\t\t<p>\n\t\t\t\t" . $minnNote . "\t\t\t\t</p>\n\n\t\t\t",
        };
        echo "\n\t\t\t</li>\n\t\t\t\t\t</ul>\n";
    } else {
        echo "\t\t\t\t\t\t</ul>\n";
    }
    echo "\t\t<ul role=\"navigation\">\n\t\t\t\t";
    wp_list_pages('title_li=<h2>Pages</h2>');
    echo "\n\t\t\t<li><h2>Archives</h2>\n\t\t\t\t<ul>\n\t\t\t\t";
    wp_get_archives(['type' => 'monthly']);
    echo "\t\t\t\t</ul>\n\t\t\t</li>\n\n\t\t\t\t";
    wp_list_categories('show_count=1&title_li=<h2>Categories</h2>');
    echo "\t\t</ul>\n\t\t<ul>\n";
    if (is_home() || is_page()) {
        echo "\t\t\t\t\t\t\t\t\t";
        wp_list_bookmarks();
        echo "\n\t\t\t\t<li><h2>Meta</h2>\n\t\t\t\t<ul>\n\t\t\t\t\t";
        wp_register();
        echo "\t\t\t\t\t<li>";
        wp_loginout();
        echo "</li>\n\t\t\t\t\t";
        wp_meta();
        echo "\t\t\t\t</ul>\n\t\t\t\t</li>\n\t\t\t\n\t\t\t";
    } else {
        echo "\t\t\t\t\n\t\t\t";
    }
}
echo "\t\t</ul>\n\t</div>\n";
