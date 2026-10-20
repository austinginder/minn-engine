<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\Autop;
use Minn\Content\PostRecord;
use Minn\Content\PasswordGate;
use Minn\Content\Texturize;
use Minn\Extension\Extensions;
use Minn\Front\Permalinks;
use Minn\Runtime\MainQuery;
use Minn\Runtime\Runtime;
use Minn\Html;

/**
 * What a classic theme's the_content() prints: the content as written (the
 * more-tag teaser in listings, the password gate's form in its place) through
 * the_content, the engine's block renderer in do_blocks's place for block
 * content and core's own defaults for classic content, so plugin code is
 * handed what the reference hands it, in the same hook order.
 */
final class ClassicContent
{
    /** A post's content as the classic loop prints it, with the more link. */
    public static function render(PostRecord $post, ?string $moreLinkText, Teaser $teaser = Teaser::Kept): string
    {
        $permalinks = Runtime::current()->get('permalinks');
        if (!$permalinks instanceof Permalinks) {
            return '';
        }
        $raw = (string) ($post->content);
        $link = static fn (): string => $permalinks->forPost($post);
        if (PasswordGate::is($post)) {
            // The form takes the content's place and runs the content filters, paragraphs first, as the reference's the_content does.
            return Runtime::contentFilter(Autop::apply(PasswordGate::formForReader($post, $permalinks->url(''), $link())));
        }
        // Nothing written still goes through the_content, as on the reference: a page builder answers an
        // empty post (Elementor's editor preview, Brizy's editor frame under a classic theme).
        $more = strpos($raw, '<!--more-->');
        // The teaser stops at the more tag unless the whole text is wanted: a single post or page, or a feed ($more, as setup_postdata leaves it).
        if (!MainQuery::current()->is_singular() && empty($GLOBALS['more']) && $more !== false) {
            $label = $moreLinkText ?? \Minn\I18n\Gettext::text('(more&hellip;)');
            $content = substr($raw, 0, $more) . ' <a href="' . Html::attr($link() . '#more-' . $post->id) . '" class="more-link"><span aria-label="' . Html::attr(sprintf(\Minn\I18n\Gettext::text('Continue reading %s'), Texturize::text($post->title))) . '">' . $label . '</span></a>';
        } elseif ($teaser === Teaser::Stripped && $more !== false) {
            // The whole text without its teaser (the_content's $strip_teaser): what follows the more tag.
            $content = '<span id="more-' . $post->id . '"></span>' . substr($raw, $more + 11);
        } else {
            $content = str_replace('<!--more-->', '<span id="more-' . $post->id . '"></span>', $raw);
        }
        // Embeds are the content filter's own (WP_Embed at 8), cached by this post.
        $embed = $GLOBALS['wp_embed'] ?? null;
        if (is_object($embed) && property_exists($embed, 'post_ID')) {
            $embed->post_ID = $post->id;
        }
        // Through the_content as written, as the reference hands it: classic content with core's defaults in their
        // places, block content rendered in do_blocks's place.
        $seams = Extensions::runner();
        $seamed = $seams === null ? null : static fn (string $html): string => $seams->filterContent($html, $post);
        if (str_contains($raw, '<!-- wp:')) {
            return Runtime::blockContentFilter($content, $seamed);
        }
        $content = Runtime::classicContentFilter($content);
        return $seamed === null ? $content : $seamed($content);
    }
}
