<?php

require_once __DIR__ . '/WP_HTML_Tag_Processor.php';

/**
 * The Interactivity API's tag processor (probe
 * interactivity-directives-processor): the tag processor with balanced-tag
 * walks and the content between a tag and its closer read, replaced, or
 * added to after a template. The engine's own directive pass
 * (Runtime\Interactivity) does not need it; plugins that process markup
 * the way the reference does do.
 */
class WP_Interactivity_API_Directives_Processor extends WP_HTML_Tag_Processor
{
    /** The tags whose closer the processor never stops at: their contents are text it reads whole. */
    private const MINN_UNVISITED_CLOSERS = ['IFRAME', 'NOEMBED', 'NOFRAMES', 'SCRIPT', 'STYLE', 'TEXTAREA', 'TITLE', 'XMP'];

    public function get_content_between_balanced_template_tags()
    {
        // A template's content as written, the processor left at its closer; null for anything else, or a template never closed.
        if ($this->get_tag() !== 'TEMPLATE') {
            return null;
        }
        $positions = $this->get_after_opener_tag_and_before_closer_tag_positions();
        return $positions === null ? null : $this->tags->source($positions[0], $positions[1] - $positions[0]);
    }

    public function set_content_between_balanced_tags(string $new_content): bool
    {
        // The content between the tag and its closer becomes the text given, escaped; the processor stays at the tag.
        $positions = $this->get_after_opener_tag_and_before_closer_tag_positions(true);
        if ($positions === null) {
            return false;
        }
        $this->tags->replaceAfter($positions[0], $positions[1], esc_html($new_content));
        return true;
    }

    public function append_content_after_template_tag_closer(string $new_content): bool
    {
        // At a template's closer, the markup given goes right after it (ahead of anything added there before) and is read next.
        if ($new_content === '' || $this->get_tag() !== 'TEMPLATE' || !$this->is_tag_closer()) {
            return false;
        }
        $after = $this->tags->tokenSpan()[1];
        $this->tags->replaceAfter($after, $after, $new_content);
        return true;
    }

    /** @return array{0: int, 1: int}|null where the tag's content starts and ends, its balanced closer found; back at the tag when asked */
    private function get_after_opener_tag_and_before_closer_tag_positions(bool $rewind = false)
    {
        if (!$this->has_and_visits_its_closer_tag()) {
            return null;
        }
        $this->set_bookmark('minn-opener');
        if (!$this->next_balanced_tag_closer_tag()) {
            $this->release_bookmark('minn-opener');
            return null;
        }
        $positions = [$this->tags->bookmarkSpan('minn-opener')[1], $this->tags->tokenSpan()[0]];
        if ($rewind) {
            $this->seek('minn-opener');
        }
        $this->release_bookmark('minn-opener');
        return $positions;
    }

    public function skip_to_tag_closer(): bool
    {
        // On to the closer of the tag the processor is at, counting the same tags opened in between.
        $depth = 0;
        $tag_name = $this->get_tag();
        while ($depth >= 0 && $this->next_tag(['tag_name' => $tag_name, 'tag_closers' => 'visit'])) {
            $depth += $this->is_tag_closer() ? -1 : 1;
        }
        return $depth < 0;
    }

    public function next_balanced_tag_closer_tag(): bool
    {
        // The closer that balances the current tag: tags of its name counted, those whose closers are never visited passed over.
        $tag_name = $this->get_tag();
        if (!$this->has_and_visits_its_closer_tag()) {
            return false;
        }
        for ($depth = 0; $this->next_tag(['tag_closers' => 'visit']);) {
            if (!$this->has_and_visits_its_closer_tag() || $this->get_tag() !== $tag_name) {
                continue;
            }
            if ($this->is_tag_closer() && $depth === 0) {
                return true;
            }
            $depth += $this->is_tag_closer() ? -1 : 1;
        }
        return false;
    }

    public function has_and_visits_its_closer_tag(): bool
    {
        // A tag with a closer the processor stops at: not a void element, nor one whose contents are raw text.
        $tag_name = $this->get_tag();
        return $tag_name !== null && !WP_HTML_Processor::is_void($tag_name) && !in_array($tag_name, self::MINN_UNVISITED_CLOSERS, true);
    }
}
