<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\TagBalancer;
use Minn\Objects\Post;
use Minn\Objects\Query;

/**
 * The loop's view of a post, as the reference's generate_postdata and
 * get_the_content give it (probes the-content, excerpt): the content split
 * into pages at <!--nextpage--> (the line breaks beside the marker go with
 * it) and handed to content_pagination, the page the query asks for, and
 * "more" when the main query shows a single post, a page or a feed; then
 * the content of that page, cut at the more tag with a more link (or run
 * on past it when "more" is set), the teaser dropped when asked or marked
 * <!--noteaser-->.
 */
final class PostData
{
    /**
     * A post made the one being read (setup_postdata): through the query
     * being answered when there is one (which tells the_post), else its
     * globals set here; false for no post.
     */
    public static function setup(mixed $post): mixed
    {
        $query = $GLOBALS['wp_query'] ?? null;
        if ($query instanceof Query) {
            return $query->setup_postdata($post);
        }
        $post = Records::post($post);
        if ($post === null) {
            return false;
        }
        $GLOBALS['post'] = $post;
        Runtime::current()->set('post', $post);
        self::globals($post);
        return true;
    }

    /** The loop's globals for a post: its id, author, dates, pages and whether more shows. */
    public static function globals(Post $post): void
    {
        $elements = self::generate($post);
        foreach (['id', 'authordata', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages'] as $name) {
            $GLOBALS[$name] = $elements[$name];
        }
    }

    /**
     * id, authordata, currentday, currentmonth, page, pages, multipage,
     * more, numpages. The page comes from the query given (the main query
     * otherwise); more is set on a single, a page or a feed, or past the
     * first page of a post split into pages (probe query-compat).
     *
     * @return array<string, mixed>
     */
    public static function generate(Post $post, ?Query $query = null): array
    {
        $content = str_replace("\n<!--nextpage-->\n", '<!--nextpage-->', (string) $post->post_content);
        $content = str_replace(["\n<!--nextpage-->", "<!--nextpage-->\n"], '<!--nextpage-->', $content);
        if (str_starts_with($content, '<!--nextpage-->')) {
            $content = substr($content, strlen('<!--nextpage-->'));
        }
        $pages = (array) Runtime::hooks()->filter('content_pagination', [explode('<!--nextpage-->', $content), $post]);
        $page = max(1, (int) ($query === null ? MainQuery::current()->get('page') : $query->get('page')));
        return [
            'id' => (int) $post->ID,
            'authordata' => Handoff::user((int) $post->post_author),
            'currentday' => SiteTime::fromStored('d.m.y', $post->post_date, false),
            'currentmonth' => SiteTime::fromStored('m', $post->post_date, false),
            'page' => $page,
            'pages' => $pages,
            'multipage' => count($pages) > 1 ? 1 : 0,
            'more' => MainQuery::current()->is_single() || MainQuery::current()->is_page() || MainQuery::current()->is_feed() || (count($pages) > 1 && $page > 1) ? 1 : 0,
            'numpages' => count($pages),
        ];
    }

    /**
     * The content get_the_content gives: the asked page, cut at the more tag
     * unless "more" is set.
     *
     * @param array<string, mixed> $elements as generate() gives them, with strip_teaser when the teaser should go
     */
    public static function content(?string $moreLinkText, Post $post, array $elements): string
    {
        $stripTeaser = !empty($elements['strip_teaser']);
        $moreLinkText ??= sprintf('<span aria-label="%1$s">%2$s</span>', sprintf(\Minn\I18n\Gettext::text('Continue reading %s'), PostTags::titleAttribute(['echo' => false, 'post' => $post])), \Minn\I18n\Gettext::text('(more&hellip;)'));
        $pages = array_values((array) $elements['pages']);
        $content = (string) ($pages[min(max(1, (int) $elements['page']), max(1, count($pages))) - 1] ?? '');
        $parts = [$content];
        if (preg_match('/<!--more(.*?)?-->/', $content, $matches)) {
            $content = (string) preg_replace('/<!-- \/?wp:more(.*?) -->/', '', $content);
            $parts = explode($matches[0], $content, 2);
            if (!empty($matches[1]) && $moreLinkText !== '') {
                $moreLinkText = strip_tags(KsesRules::withoutNull(trim($matches[1])));
            }
        }
        if (str_contains((string) $post->post_content, '<!--noteaser-->') && (!$elements['multipage'] || (int) $elements['page'] === 1)) {
            $stripTeaser = true;
        }
        $output = $elements['more'] && $stripTeaser && count($parts) > 1 ? '' : $parts[0];
        if (count($parts) < 2) {
            return $output;
        }
        if ($elements['more']) {
            return $output . '<span id="more-' . $post->ID . '"></span>' . $parts[1];
        }
        if ($moreLinkText !== '') {
            $output .= Runtime::hooks()->filter('the_content_more_link', [' <a href="' . PostLinks::permalink($post) . "#more-{$post->ID}\" class=\"more-link\">{$moreLinkText}</a>", $moreLinkText]);
        }
        return TagBalancer::balance((string) $output);
    }
}
