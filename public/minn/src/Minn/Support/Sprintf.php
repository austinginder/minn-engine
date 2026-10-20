<?php

declare(strict_types=1);

namespace Minn\Support;

use Closure;

/**
 * A pattern filled one fragment at a time, as wp_sprintf fills it: "%%" is
 * a percent sign, each other fragment (a directive and the text up to the
 * next one) takes the next argument or the one its position names, and
 * $filter may write a fragment itself (the wp_sprintf filter: the list
 * directive %l lives there); otherwise PHP's sprintf fills it, an array
 * as nothing, and a fragment it cannot fill stays as written.
 */
final class Sprintf
{
    /**
     * The pattern with its arguments filled in.
     *
     * @param list<mixed> $args
     * @param Closure(string, mixed): mixed $filter the fragment and its argument in, the fragment as written out
     */
    public static function format(string $pattern, array $args, Closure $filter): string
    {
        $at = strpos($pattern, '%');
        if ($at === false) {
            return $pattern;
        }
        $out = substr($pattern, 0, $at);
        $length = strlen($pattern);
        $next = 0;
        while ($at < $length) {
            if (substr($pattern, $at, 2) === '%%') {
                $out .= '%';
                $at += 2;
                continue;
            }
            $end = strpos($pattern, '%', $at + 1);
            $fragment = $end === false ? substr($pattern, $at) : substr($pattern, $at, $end - $at);
            $at = $end === false ? $length : $end;
            if (preg_match('/^%(\d+)\$/', $fragment, $position) === 1) {
                $value = $args[(int) $position[1] - 1] ?? '';
                $fragment = '%' . substr($fragment, strlen($position[0]));
            } else {
                $value = $args[$next++] ?? '';
            }
            $filtered = $filter($fragment, $value);
            if ($filtered !== $fragment) {
                $out .= $filtered;
                continue;
            }
            try {
                $out .= sprintf($fragment, is_array($value) ? '' : $value);
            } catch (\ValueError) {
                $out .= $fragment;
            }
        }
        return $out;
    }
}
