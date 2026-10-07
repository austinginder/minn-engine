<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Content\PostRecord;
use Minn\Content\Blocks;
use Minn\Content\PasswordGate;
use Minn\Content\Texturize;
use Minn\Extension\Extensions;
use Minn\Front\Permalinks;
use Minn\Runtime\Runtime;
use Minn\Support\Html;

/**
 * What a classic theme's the_content() prints: the engine's block pipeline
 * (render, password gate, the more-tag teaser in listings), the cached
 * embeds, then the runtime's shortcodes and the_content filters so plugin
 * code sees the same hook order the reference runs.
 */
final class ClassicContent
{
    /** A post's content as the classic loop prints it, with the more link. */
    public static function render(PostRecord $post, ?string $moreLinkText): string
    {
        $permalinks = Runtime::current()->get('permalinks');
        if (!$permalinks instanceof Permalinks) {
            return '';
        }
        $raw = (string) ($post->content);
        if (PasswordGate::is($post)) {
            // The form takes the content's place and runs the content filters, paragraphs first, as the reference's the_content does.
            return Runtime::contentFilter(\wpautop(PasswordGate::form($post, $permalinks->url(''), $permalinks->forPost($post))));
        }
        if (trim($raw) === '') {
            return '';
        }
        $more = strpos($raw, '<!--more-->');
        // The teaser stops at the more tag unless the whole text is wanted: a single post or page, or a feed ($more, as setup_postdata leaves it).
        if (!\is_singular() && empty($GLOBALS['more']) && $more !== false) {
            $label = $moreLinkText ?? '(more&hellip;)';
            $suffix = "\n" . ' <a href="' . Html::attr($permalinks->forPost($post) . '#more-' . $post->id) . '" class="more-link"><span aria-label="Continue reading ' . Html::attr(Texturize::text($post->title)) . '">' . $label . '</span></a>';
            $content = rtrim(Blocks::render(substr($raw, 0, $more))) . $suffix;
        } else {
            $content = Blocks::render(str_replace('<!--more-->', '<span id="more-' . $post->id . '"></span>', $raw));
        }
        $embed = $GLOBALS['wp_embed'] ?? null;
        if (is_object($embed) && method_exists($embed, 'autoembed')) {
            $embed->post_ID = $post->id;
            $content = (string) $embed->autoembed($content);
        }
        $seams = Extensions::runner();
        if ($seams !== null) {
            $content = $seams->filterContent($content, $post);
        }
        $content = Runtime::shortcodes()->apply($content);
        return Runtime::contentFilter($content);
    }
}
