<?php

declare(strict_types=1);

namespace Minn\Runtime;

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
     * id, authordata, currentday, currentmonth, page, pages, multipage,
     * more, numpages.
     *
     * @return array<string, mixed>
     */
    public static function generate(\WP_Post $post): array
    {
        $content = str_replace("\n<!--nextpage-->\n", '<!--nextpage-->', (string) $post->post_content);
        $content = str_replace(["\n<!--nextpage-->", "<!--nextpage-->\n"], '<!--nextpage-->', $content);
        if (str_starts_with($content, '<!--nextpage-->')) {
            $content = substr($content, strlen('<!--nextpage-->'));
        }
        $pages = (array) \apply_filters('content_pagination', explode('<!--nextpage-->', $content), $post);
        return [
            'id' => (int) $post->ID,
            'authordata' => \get_userdata((int) $post->post_author),
            'currentday' => \mysql2date('d.m.y', $post->post_date, false),
            'currentmonth' => \mysql2date('m', $post->post_date, false),
            'page' => max(1, (int) \get_query_var('page')),
            'pages' => $pages,
            'multipage' => count($pages) > 1 ? 1 : 0,
            'more' => \is_single() || \is_page() || \is_feed() ? 1 : 0,
            'numpages' => count($pages),
        ];
    }

    /**
     * The content get_the_content gives: the asked page, cut at the more tag
     * unless "more" is set.
     *
     * @param array<string, mixed> $elements as generate() gives them, with strip_teaser when the teaser should go
     */
    public static function content(?string $moreLinkText, \WP_Post $post, array $elements): string
    {
        $stripTeaser = !empty($elements['strip_teaser']);
        $moreLinkText ??= sprintf('<span aria-label="%1$s">%2$s</span>', sprintf('Continue reading %s', \the_title_attribute(['echo' => false, 'post' => $post])), '(more&hellip;)');
        $pages = array_values((array) $elements['pages']);
        $content = (string) ($pages[min(max(1, (int) $elements['page']), max(1, count($pages))) - 1] ?? '');
        $parts = [$content];
        if (preg_match('/<!--more(.*?)?-->/', $content, $matches)) {
            $content = (string) preg_replace('/<!-- \/?wp:more(.*?) -->/', '', $content);
            $parts = explode($matches[0], $content, 2);
            if (!empty($matches[1]) && $moreLinkText !== '') {
                $moreLinkText = strip_tags(\wp_kses_no_null(trim($matches[1])));
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
            $output .= \apply_filters('the_content_more_link', ' <a href="' . \get_permalink($post) . "#more-{$post->ID}\" class=\"more-link\">{$moreLinkText}</a>", $moreLinkText);
        }
        return \force_balance_tags($output);
    }
}
