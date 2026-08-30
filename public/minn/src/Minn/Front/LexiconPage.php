<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Support\Html;

/**
 * The interactive lexicon page. Markdown is parsed into blocks; this
 * paints them in the Minn site chrome with Speak / Hear / Mute filters.
 */
final readonly class LexiconPage
{
    public function __construct(
        private Lexicon $lexicon,
        private Permalinks $permalinks,
        private string $siteName,
        private ?string $themeDir,
        private string $themeUri,
    ) {
    }

    public function html(): string
    {
        $title = Html::esc($this->lexicon->title) . ' · ' . Html::esc($this->siteName);
        $chrome = new SiteChrome($this->themeDir);
        $css = $chrome->stylesheet($this->themeUri);
        return '<!DOCTYPE html>' . "\n" . '<html lang="en">' . "\n" . '<head>' . "\n"
            . '<meta charset="UTF-8" />' . "\n"
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />' . "\n"
            . '<title>' . $title . '</title>' . "\n"
            . $css
            . '</head>' . "\n"
            . '<body class="minn-lex-page">' . "\n"
            . '<a class="skip-link" href="#content">Skip to content</a>' . "\n"
            . $chrome->part('header', LexiconController::PATH)
            . '<main id="content" class="minn-lexicon">' . "\n"
            . '<header class="minn-lex-hero"><span class="minn-kicker">The glossary</span><h1>'
            . Html::esc($this->lexicon->title) . '</h1></header>' . "\n"
            . $this->toolbar()
            . $this->body()
            . '</main>' . "\n"
            . $chrome->part('footer', LexiconController::PATH)
            . $this->script()
            . '</body>' . "\n" . '</html>' . "\n";
    }

    private function toolbar(): string
    {
        $md = Html::attr($this->permalinks->url('/lexicon.md'));
        $n = (string) $this->lexicon->familyCount();
        return '<div class="minn-lex-bar" id="minn-lex-bar">'
            . '<div class="minn-lex-chips" role="group" aria-label="Filter by status">'
            . $this->chip('all', 'All')
            . $this->chip('speak', 'Speak')
            . $this->chip('hear', 'Hear')
            . $this->chip('mute', 'Mute')
            . '</div>'
            . '<input type="search" id="minn-lex-q" class="minn-lex-q" placeholder="Search families" autocomplete="off" aria-label="Search families" />'
            . '<span class="minn-lex-count" id="minn-lex-count">' . Html::esc($n) . ' of ' . Html::esc($n) . ' families</span>'
            . '<a class="minn-lex-src" href="' . $md . '">Source markdown</a>'
            . '</div>' . "\n"
            . '<p class="minn-lex-empty" id="minn-lex-empty" hidden>No families match.</p>' . "\n";
    }

    private function chip(string $id, string $label): string
    {
        $pressed = $id === 'all' ? 'true' : 'false';
        return '<button type="button" class="minn-lex-chip" data-lex-filter="' . Html::attr($id)
            . '" aria-pressed="' . $pressed . '">' . Html::esc($label) . '</button>';
    }

    private function body(): string
    {
        $out = '';
        $open = null;
        foreach ($this->lexicon->blocks as $block) {
            if ($block->kind === LexiconKind::Heading && $block->level === 2 && $block->status !== null) {
                if ($open !== null) {
                    $out .= "</section>\n";
                }
                $out .= '<section class="minn-lex-section" data-lex-section data-status="'
                    . Html::attr($block->status) . '">' . "\n";
                $open = $block->status;
            } elseif ($block->kind === LexiconKind::Heading && $block->level === 2 && $open !== null) {
                $out .= "</section>\n";
                $open = null;
            }
            $out .= $this->block($block);
        }
        if ($open !== null) {
            $out .= "</section>\n";
        }
        return $out;
    }

    private function block(LexiconBlock $block): string
    {
        return match ($block->kind) {
            LexiconKind::Heading => $this->heading($block),
            LexiconKind::Paragraph => '<p>' . self::inline($block->text) . "</p>\n",
            LexiconKind::List => $this->list($block),
            LexiconKind::Table => $this->table($block),
            LexiconKind::Rule => "<hr />\n",
        };
    }

    private function heading(LexiconBlock $block): string
    {
        $tag = 'h' . $block->level;
        $id = $block->id !== '' ? ' id="' . Html::attr($block->id) . '"' : '';
        return "<{$tag}{$id}>" . self::inline($block->text) . "</{$tag}>\n";
    }

    private function list(LexiconBlock $block): string
    {
        $tag = $block->ordered ? 'ol' : 'ul';
        $out = "<{$tag}>\n";
        foreach ($block->items as $item) {
            $out .= '<li>' . self::inline($item) . "</li>\n";
        }
        return $out . "</{$tag}>\n";
    }

    private function table(LexiconBlock $block): string
    {
        $class = $block->filterable ? ' class="minn-lex-table"' : '';
        $out = "<div class=\"minn-lex-table-wrap\"><table{$class}>\n<thead><tr>";
        foreach ($block->headers as $cell) {
            $out .= '<th>' . self::inline($cell) . '</th>';
        }
        $out .= "</tr></thead>\n<tbody>\n";
        foreach ($block->rows as $row) {
            $status = $block->status;
            foreach ($row as $cell) {
                $status = $status ?? Lexicon::statusFromCell($cell);
            }
            $attr = '';
            if ($block->filterable) {
                $attr = ' data-lex-row';
                if ($status !== null) {
                    $attr .= ' data-status="' . Html::attr($status) . '"';
                }
            }
            $out .= "<tr{$attr}>";
            foreach ($row as $cell) {
                $out .= '<td>' . self::inline($cell) . '</td>';
            }
            $out .= "</tr>\n";
        }
        return $out . "</tbody></table></div>\n";
    }

    public static function inline(string $text): string
    {
        $text = Html::esc($text);
        $text = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = (string) preg_replace('/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/', '<em>$1</em>', $text);
        return $text;
    }

    private function script(): string
    {
        return <<<'JS'
<script>
(function () {
    var root = document.querySelector('.minn-lexicon');
    if (!root) { return; }
    var q = document.getElementById('minn-lex-q');
    var count = document.getElementById('minn-lex-count');
    var empty = document.getElementById('minn-lex-empty');
    var chips = root.querySelectorAll('[data-lex-filter]');
    var filter = 'all';
    function apply() {
        var query = ((q && q.value) || '').trim().toLowerCase();
        var n = 0, total = 0;
        root.querySelectorAll('[data-lex-row]').forEach(function (row) {
            total++;
            var status = row.getAttribute('data-status') || '';
            var text = (row.textContent || '').toLowerCase();
            var ok = (filter === 'all' || status === filter) && (!query || text.indexOf(query) !== -1);
            row.hidden = !ok;
            if (ok) { n++; }
        });
        root.querySelectorAll('.minn-lex-table-wrap').forEach(function (wrap) {
            if (!wrap.querySelector('[data-lex-row]')) { return; }
            wrap.hidden = !wrap.querySelector('[data-lex-row]:not([hidden])');
        });
        root.querySelectorAll('[data-lex-section]').forEach(function (sec) {
            var status = sec.getAttribute('data-status');
            if (!status) { return; }
            var any = sec.querySelector('[data-lex-row]:not([hidden])');
            var statusOk = filter === 'all' || status === filter;
            sec.hidden = !statusOk || (query !== '' && !any);
        });
        if (count) { count.textContent = n + ' of ' + total + ' families'; }
        if (empty) { empty.hidden = n !== 0 || total === 0; }
        chips.forEach(function (c) {
            c.setAttribute('aria-pressed', c.getAttribute('data-lex-filter') === filter ? 'true' : 'false');
        });
    }
    function reveal(smooth) {
        if (filter === 'all') { return; }
        var target = document.getElementById(filter);
        if (!target) { return; }
        target.scrollIntoView({ behavior: smooth ? 'smooth' : 'instant', block: 'start' });
    }
    chips.forEach(function (c) {
        c.addEventListener('click', function () {
            filter = c.getAttribute('data-lex-filter') || 'all';
            if (filter !== 'all') { history.replaceState(null, '', '#' + filter); }
            else { history.replaceState(null, '', location.pathname); }
            apply();
            reveal(true);
        });
    });
    if (q) { q.addEventListener('input', apply); }
    var hash = (location.hash || '').replace('#', '').toLowerCase();
    if (hash === 'speak' || hash === 'hear' || hash === 'mute') { filter = hash; }
    apply();
    reveal(false);
    // The browser's own hash scroll runs after inline scripts and
    // would park the heading under the sticky bar. Repeat on load.
    window.addEventListener('load', function () { reveal(false); });
})();
</script>
JS;
    }
}
