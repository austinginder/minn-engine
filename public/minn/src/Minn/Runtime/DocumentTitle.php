<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Http\Response;
use Minn\I18n\Gettext;
use Minn\Objects\Post;
use Minn\Objects\Term;

/**
 * The page's title as WordPress's title functions make it from the main
 * query: the document title and its parts (what is shown, the page number,
 * the site or its tagline), and the title tags of a post type archive, a
 * term archive and a single post, printed or returned.
 */
final class DocumentTitle
{
    /** The document title (wp_get_document_title), through the title filters, ready to print inside <title>. */
    public static function get(): string
    {
        $hooks = Runtime::hooks();
        $title = $hooks->filter('pre_get_document_title', ['']);
        if (!empty($title)) {
            return (string) $title;
        }
        $sep = $hooks->filter('document_title_separator', ['-']);
        $parts = $hooks->filter('document_title_parts', [self::parts()]);
        return (string) $hooks->filter('document_title', [implode(" $sep ", array_filter((array) $parts))]);
    }

    /**
     * The document title's parts from the main query's state: the title of
     * what is shown, the page number past the first, then the tagline on
     * the front page or the site's name elsewhere.
     *
     * @return array<string, mixed>
     */
    public static function parts(): array
    {
        $query = MainQuery::current();
        $title = match (true) {
            $query->is_404() => Gettext::text('Page not found'),
            $query->is_search() => sprintf(Gettext::text('Search Results for &#8220;%s&#8221;'), MainQuery::searchQuery()),
            $query->is_front_page() => SiteInfo::get('name', 'display'),
            $query->is_post_type_archive() => self::postTypeArchive('', false),
            $query->is_tax() => self::term('', false),
            $query->is_home() || $query->is_singular() => self::post('', false),
            $query->is_category() || $query->is_tag() => self::term('', false),
            $query->is_author() && $query->get_queried_object() => $query->get_queried_object()->display_name,
            $query->is_year() => PostTags::date(Gettext::inContext('Y', 'yearly archives date format')),
            $query->is_month() => PostTags::date(Gettext::inContext('F Y', 'monthly archives date format')),
            $query->is_day() => PostTags::date(),
            default => '',
        };
        $parts = ['title' => $title];
        $paged = max((int) ($GLOBALS['paged'] ?? 0), (int) ($GLOBALS['page'] ?? 0));
        if ($paged >= 2 && !$query->is_404()) {
            $parts += ['page' => sprintf(Gettext::text('Page %s'), $paged)];
        }
        return $parts + ($query->is_front_page() ? ['tagline' => SiteInfo::get('description', 'display')] : ['site' => SiteInfo::get('name', 'display')]);
    }

    /** On a post type archive, the type's plural name after a prefix (post_type_archive_title), printed or returned; null elsewhere. */
    public static function postTypeArchive(mixed $prefix = '', mixed $display = true): mixed
    {
        $query = MainQuery::current();
        if (!$query->is_post_type_archive()) {
            return null;
        }
        $type = $query->get('post_type');
        $type = is_array($type) ? (string) reset($type) : (string) $type;
        $object = Registered::postType($type);
        if ($object === null) {
            return null;
        }
        return self::shown($prefix . Runtime::hooks()->filter('post_type_archive_title', [$object->labels->name, $type]), $display);
    }

    /** The queried term's name after a prefix (single_term_title), printed or returned; null off a term archive. */
    public static function term(mixed $prefix = '', mixed $display = true): mixed
    {
        $query = MainQuery::current();
        $term = $query->get_queried_object();
        if (!$term instanceof Term || !($query->is_category() || $query->is_tag() || $query->is_tax())) {
            return null;
        }
        $filter = match ($term->taxonomy) { 'category' => 'single_cat_title', 'post_tag' => 'single_tag_title', default => 'single_term_title' };
        return self::shown($prefix . Runtime::hooks()->filter($filter, [$term->name]), $display);
    }

    /** The queried post's title after a prefix (single_post_title), printed or returned; null when no post is queried. */
    public static function post(mixed $prefix = '', mixed $display = true): mixed
    {
        $post = MainQuery::current()->get_queried_object();
        if (!$post instanceof Post) {
            return null;
        }
        return self::shown($prefix . Runtime::hooks()->filter('single_post_title', [$post->post_title, $post]), $display);
    }

    /** A title printed (null back) or returned. */
    private static function shown(string $title, mixed $display): ?string
    {
        if ($display) {
            Response::write($title);
            return null;
        }
        return $title;
    }
}
