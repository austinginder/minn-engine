<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\I18n\Gettext;
use Minn\Runtime\Bookmarks;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteInfo;
use Minn\Runtime\TermLookup;
use Minn\Support\Escape;
use Minn\Support\Whole;

/**
 * wp-links-opml.php: the site's links as an OPML outline, as the reference
 * prints it (contracts/front/probes.md, "Entry scripts"). The link
 * categories with links (all of them, or the one link_cat names), each
 * with its visible links by name; whatever opml_head prints (the generator
 * comment, by default) in the head. The whitespace is the reference's to
 * the tab: a category opens flush, each link sits after a tab, and the
 * run that closes one item opens the next.
 */
final class LinksOpml
{
    /** The page for a request's query (its link_cat), under its content type. */
    public static function page(array $query): string
    {
        $out = "<?xml version=\"1.0\"?>\n<opml version=\"1.0\">\n\t<head>\n\t\t<title>\n\t\t"
            . sprintf(Gettext::text('Links for %s'), Escape::attr(SiteInfo::get('name', 'display')))
            . "\t\t</title>\n\t\t<dateCreated>" . gmdate('D, d M Y H:i:s') . " GMT</dateCreated>\n\t\t"
            . Runtime::capture('opml_head')
            . "\t</head>\n\t<body>\n";
        $categories = TermLookup::categories(self::categoryArgs($query['link_cat'] ?? ''));
        foreach (is_array($categories) ? $categories : [] as $category) {
            $out .= self::category($category);
        }
        return $out . "</body>\n</opml>\n";
    }

    /**
     * What the categories are asked with: every link category, or the one
     * link_cat names by its whole number made positive; 'all' is passed on
     * as it is and finds none, and an empty or '0' link_cat asks for all.
     *
     * @return array<string, mixed>
     */
    private static function categoryArgs(mixed $asked): array
    {
        $args = ['taxonomy' => 'link_category', 'hierarchical' => 0];
        if (is_string($asked) && $asked !== '' && $asked !== '0') {
            $args['include'] = $asked === 'all' ? 'all' : Whole::abs($asked);
        }
        return $args;
    }

    /** One category's outline, its visible links inside. */
    private static function category(object $category): string
    {
        $title = Runtime::hooks()->filter('link_category', [$category->name]);
        $out = '<outline type="category" title="' . Escape::attr($title) . "\">\n";
        $links = Bookmarks::find(['category' => $category->term_id]);
        foreach (is_array($links) ? $links : [] as $link) {
            $updated = (string) ($link->link_updated ?? '') === '0000-00-00 00:00:00' ? '' : (string) ($link->link_updated ?? '');
            $out .= "\t<outline text=\"" . Escape::attr(Runtime::hooks()->filter('link_title', [$link->link_name])) . '" type="link" xmlUrl="'
                . Escape::url($link->link_rss ?? '') . '" htmlUrl="' . Escape::url($link->link_url ?? '') . "\" updated=\"\n\t\t\t\t\t\t\t{$updated}\" />\n\t";
        }
        return $out . "\t</outline>\n\t";
    }
}
