<?php

declare(strict_types=1);

namespace Minn\Theme;

use Minn\Http\Response;
use Minn\Runtime\Runtime;

/**
 * The reference includes a front-end template inside the template-enhancement
 * buffer: wp_before_include_template fires with the template's file, its
 * default callback opens the buffer when a plugin filters
 * wp_template_enhancement_output_buffer, and that filter is handed the whole
 * page. The engine builds the page as a string, so the string is passed
 * through the same buffer: every buffer the action opened is closed in turn,
 * its handler writing into the capture the page is read from.
 */
final class TemplateBuffer
{
    /**
     * The page a render gives, as the buffers opened before it leave it; null
     * when the render gives none. A render that fails leaves nothing behind.
     *
     * @param callable(): ?string $render
     */
    public static function around(string $template, callable $render): ?string
    {
        $level = ob_get_level();
        ob_start();
        try {
            Runtime::hooks()->action('wp_before_include_template', [$template]);
            $page = $render();
            if ($page !== null) {
                Response::write($page);
            }
            while (ob_get_level() > $level + 1) {
                ob_end_flush();
            }
        } catch (\Throwable $failure) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            throw $failure;
        }
        $out = (string) ob_get_clean();
        return $page === null ? null : $out;
    }
}
