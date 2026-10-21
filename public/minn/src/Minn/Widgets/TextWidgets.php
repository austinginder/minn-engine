<?php

declare(strict_types=1);

namespace Minn\Widgets;

use DOMDocument;
use DOMXPath;
use Minn\Content\Autop;
use Minn\Runtime\Runtime;

/**
 * The text and custom HTML widgets' behaviour (WP_Widget_Text,
 * WP_Widget_Custom_HTML; probes widget-methods, nav-pomo-rest-doubts):
 * which text instances are legacy ones the visual editor would change, how
 * an update settles the filter and visual flags, and the two widgets'
 * output, their filters run with no global post and a gallery inside
 * belonging to no post.
 */
final class TextWidgets
{
    /** The elements the visual editor keeps as written, each with the attributes it keeps. */
    private const KEPT = ['b' => [], 'i' => [], 'u' => [], 's' => [], 'em' => [], 'strong' => [], 'code' => [], 'abbr' => [], 'ul' => [], 'ol' => [], 'li' => [], 'a' => ['href'], 'img' => ['src', 'alt'], 'hr' => []];

    /** The kept elements that are empty by nature. */
    private const VOID = ['img', 'hr'];

    /**
     * Whether a text instance is a legacy one: one with a visual flag is
     * legacy when the flag is off, one filtered as content never is, nor
     * blank text. Otherwise its text is legacy when it has a line break
     * without the paragraph filter, a comment, an element or attribute the
     * visual editor does not keep, or an element left empty.
     */
    public static function isLegacy(mixed $instance): bool
    {
        $instance = (array) $instance;
        if (isset($instance['visual'])) {
            return !$instance['visual'];
        }
        $text = (string) ($instance['text'] ?? '');
        if (($instance['filter'] ?? null) === 'content' || trim($text) === '') {
            return false;
        }
        if (empty($instance['filter']) && str_contains($text, "\n")) {
            return true;
        }
        $document = new DOMDocument();
        $errors = libxml_use_internal_errors(true);
        $document->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $text . '</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($errors);
        $xpath = new DOMXPath($document);
        if (($xpath->query('//comment()')->length ?? 0) > 0) {
            return true;
        }
        foreach ($xpath->query('//body//*') ?: [] as $element) {
            $name = strtolower($element->nodeName);
            $kept = self::KEPT[$name] ?? null;
            if ($kept === null || (!in_array($name, self::VOID, true) && trim($element->textContent) === '')) {
                return true;
            }
            foreach ($element->attributes ?? [] as $attribute) {
                if (!in_array(strtolower($attribute->name), $kept, true)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * An updated text instance's flags settled: filtered when the new one
     * asks, visual as the new one says (always for content filtering), and a
     * visual instance always filtered.
     *
     * @param array<array-key, mixed> $instance the instance with its title and text set
     * @param array<array-key, mixed> $new the settings as submitted
     * @return array<array-key, mixed>
     */
    public static function settled(array $instance, array $new): array
    {
        $instance['filter'] = !empty($new['filter']);
        if (isset($new['visual'])) {
            $instance['visual'] = !empty($new['visual']);
        }
        if (($new['filter'] ?? null) === 'content') {
            $instance['visual'] = true;
        }
        if (!empty($instance['visual'])) {
            $instance['filter'] = true;
        }
        return $instance;
    }

    /**
     * The text widget's markup: a visual instance (visual and filtered, or
     * filtered as content) through widget_text and widget_text_content; any
     * other through widget_text, then paragraphs when it asks for them, then
     * its shortcodes.
     *
     * @param array<array-key, mixed> $args
     * @param array<array-key, mixed> $instance
     */
    public static function text(object $widget, array $args, array $instance): string
    {
        // Visual: saved as content, or both visual and filtered.
        $visual = ($instance['filter'] ?? null) === 'content' || (!empty($instance['filter']) && !empty($instance['visual']));
        $heading = empty($instance['title']) ? '' : $instance['title'];
        $title = Runtime::hooks()->filter('widget_title', [$heading, $instance, $widget->id_base]);
        $text = empty($instance['text']) ? '' : $instance['text'];
        if ($visual) {
            $instance['filter'] = true;
            $instance['visual'] = true;
        }
        $text = self::withoutPost($widget, static function () use ($widget, $text, $instance, $visual) {
            $text = Runtime::hooks()->filter('widget_text', [$text, $instance, $widget]);
            if ($visual) {
                return Runtime::hooks()->filter('widget_text_content', [$text, $instance, $widget]);
            }
            $text = empty($instance['filter']) ? (string) $text : Autop::apply((string) $text);
            return Runtime::shortcodes()->apply(Runtime::shortcodes()->unautop($text), 'run');
        });
        $text = preg_replace_callback('#<(video|iframe|object|embed)\s[^>]*>#i', [$widget, 'inject_video_max_width_style'], (string) $text);
        return $args['before_widget'] . ($title ? $args['before_title'] . $title . $args['after_title'] : '')
            . "\t\t\t<div class=\"textwidget\">" . $text . "</div>\n\t\t" . $args['after_widget'];
    }

    /**
     * The custom HTML widget's markup: its content through widget_text (as a
     * text instance would carry it) and widget_custom_html_content, the text
     * widget's class first in the wrapper.
     *
     * @param array<array-key, mixed> $args
     * @param array<array-key, mixed> $instance
     */
    public static function customHtml(object $widget, array $args, array $instance): string
    {
        $instance += ['title' => '', 'content' => ''];
        $title = Runtime::hooks()->filter('widget_title', [(string) $instance['title'], $instance, $widget->id_base]);
        $content = self::withoutPost($widget, static function () use ($widget, $instance) {
            $asText = ['title' => $instance['title'], 'text' => $instance['content'], 'filter' => false, 'visual' => false];
            $content = Runtime::hooks()->filter('widget_text', [(string) $instance['content'], $asText, $widget]);
            return Runtime::hooks()->filter('widget_custom_html_content', [$content, $instance, $widget]);
        });
        return preg_replace('/(?<=\sclass=["\'])/', 'widget_text ', (string) ($args['before_widget'] ?? ''))
            . ($title !== '' ? ($args['before_title'] ?? '') . $title . ($args['after_title'] ?? '') : '')
            . '<div class="textwidget custom-html-widget">' . $content . '</div>' . ($args['after_widget'] ?? '');
    }

    /**
     * Gallery attributes inside a text or HTML widget: off a singular view,
     * a gallery naming neither an id nor included images belongs to no post (-1).
     *
     * @param array<array-key, mixed> $attrs
     * @return array<array-key, mixed>
     */
    public static function galleryAttributes(array $attrs): array
    {
        if (empty($attrs['id']) && empty($attrs['include']) && !\_minn_main_query()->is_singular()) {
            $attrs['id'] = -1;
        }
        return $attrs;
    }

    /** A widget's filters run with no global post and its gallery filter on; both put back after. */
    private static function withoutPost(object $widget, callable $run): mixed
    {
        $post = $GLOBALS['post'] ?? null;
        $GLOBALS['post'] = null;
        $gallery = [$widget, '_filter_gallery_shortcode_attrs'];
        Runtime::hooks()->add('shortcode_atts_gallery', $gallery);
        try {
            return $run();
        } finally {
            Runtime::hooks()->remove('shortcode_atts_gallery', $gallery);
            $GLOBALS['post'] = $post;
        }
    }
}
