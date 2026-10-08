<?php

declare(strict_types=1);

namespace Minn\Content;

use Minn\Html;

/**
 * The markers the block editor's notes leave in content: a mark element
 * whose class list holds "wp-note". A rendered block loses the marker and
 * keeps what it wrapped, nested markers too, an unclosed one losing only its
 * opening tag; any other mark stays whole (probe comment-helpers).
 */
final class NoteMarkers
{
    /** Markup with every note marker's tags taken out. */
    public static function strip(string $html): string
    {
        if (stripos($html, 'wp-note') === false) {
            return $html;
        }
        $out = '';
        $open = [];
        foreach (Html::split($html) as $i => $piece) {
            if ($i % 2 === 1 && preg_match('#^<mark(?=[\s/>])#i', $piece) === 1) {
                $open[] = self::isNote($piece);
                if (end($open)) {
                    continue;
                }
            } elseif ($i % 2 === 1 && preg_match('#^</mark\s*>#i', $piece) === 1 && array_pop($open) === true) {
                continue;
            }
            $out .= $piece;
        }
        return $out;
    }

    /** Whether a mark's opening tag carries the "wp-note" class. */
    private static function isNote(string $tag): bool
    {
        if (preg_match('/\sclass\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $m) !== 1) {
            return false;
        }
        $classes = preg_split('/\s+/', trim(($m[1] ?? '') . ($m[2] ?? '') . ($m[3] ?? ''))) ?: [];
        return in_array('wp-note', $classes, true);
    }
}
