<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * A block widget's legacy class name. Every widget the block editor saves
 * is one block of markup, and a classic theme styles it by the widget
 * class the equivalent legacy widget used, so the wrapper carries a
 * second class named after the FIRST block in the content. A block with
 * no legacy equivalent adds nothing.
 */
final class BlockWidget
{
    public const BASE_CLASS = 'widget_block';

    /** First block name => the legacy widget class a theme styles. */
    private const LEGACY_CLASSES = [
        'core/paragraph' => 'widget_text',
        'core/search' => 'widget_search',
        'core/html' => 'widget_custom_html',
        'core/archives' => 'widget_archive',
        'core/latest-posts' => 'widget_recent_entries',
        'core/latest-comments' => 'widget_recent_comments',
        'core/tag-cloud' => 'widget_tag_cloud',
        'core/categories' => 'widget_categories',
        'core/calendar' => 'widget_calendar',
        'core/rss' => 'widget_rss',
    ];

    /**
     * The legacy widget class a block widget maps to.
     *
     * @param list<array<string, mixed>> $blocks the parsed content
     */
    public static function classNameFor(array $blocks): string
    {
        $first = (string) ($blocks[0]['blockName'] ?? '');
        $legacy = self::LEGACY_CLASSES[$first] ?? '';
        return $legacy === '' ? self::BASE_CLASS : self::BASE_CLASS . ' ' . $legacy;
    }
}
