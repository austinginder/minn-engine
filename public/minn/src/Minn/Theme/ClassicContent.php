<?php

declare(strict_types=1);

namespace Minn\Theme;

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
    public static function render(array $post, ?string $moreLinkText): string
    {
        $permalinks = Runtime::current()->get('permalinks');
        if (!$permalinks instanceof Permalinks) {
            return '';
        }
        $raw = (string) ($post['post_content'] ?? '');
        if (PasswordGate::is($post)) {
            $raw = PasswordGate::form($post, $permalinks->url(''), $permalinks->forPost($post));
        }
        if (trim($raw) === '') {
            return '';
        }
        $more = strpos($raw, '<!--more-->');
        if (!\is_singular() && $more !== false) {
            $label = $moreLinkText ?? '(more&hellip;)';
            $suffix = "\n" . ' <a href="' . Html::attr($permalinks->forPost($post) . '#more-' . (int) $post['ID']) . '" class="more-link"><span aria-label="Continue reading ' . Html::attr(Texturize::text((string) $post['post_title'])) . '">' . $label . '</span></a>';
            $content = rtrim(Blocks::render(substr($raw, 0, $more))) . $suffix;
        } else {
            $content = Blocks::render(str_replace('<!--more-->', '<span id="more-' . (int) $post['ID'] . '"></span>', $raw));
        }
        $embed = $GLOBALS['wp_embed'] ?? null;
        if (is_object($embed) && method_exists($embed, 'autoembed')) {
            $embed->post_ID = (int) $post['ID'];
            $content = (string) $embed->autoembed($content);
        }
        $seams = Extensions::runner();
        if ($seams !== null) {
            $content = $seams->filterContent($content, $post);
        }
        $content = Runtime::shortcodes()->apply($content);
        return (string) Runtime::hooks()->filter('the_content', [$content]);
    }
}
