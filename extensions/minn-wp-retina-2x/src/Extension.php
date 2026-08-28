<?php

declare(strict_types=1);

namespace Minn\Ext\Retina;

use Minn\Blocks\Block;
use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;

/**
 * Where a "@2x" file sits beside an image's full-size file, the srcset gains
 * that file at twice the width, as the plugin's "Picturefill" method
 * produced it on the reference. Sized variants are left alone.
 */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $uploadsDir = ABSPATH . 'wp-content/uploads';
        $filter = static function (string $html) use ($uploadsDir): string {
            return (string) preg_replace_callback('/srcset="([^"]+)"/', static function (array $m) use ($uploadsDir): string {
                $candidates = array_map('trim', explode(',', $m[1]));
                foreach ($candidates as $candidate) {
                    [$url, $descriptor] = array_pad(preg_split('/\s+/', $candidate), 2, '');
                    $path = (string) parse_url($url, PHP_URL_PATH);
                    if (preg_match('/-\d+x\d+\.[a-z]+$/i', $path) || !preg_match('/^(.*)\.([a-z0-9]+)$/i', $path, $parts) || !str_contains($path, '/wp-content/uploads/')) {
                        continue;
                    }
                    $relative = substr($path, strpos($path, '/wp-content/uploads/') + strlen('/wp-content/uploads/'));
                    $retinaRelative = preg_replace('/\.([a-z0-9]+)$/i', '@2x.$1', $relative);
                    if (!is_file($uploadsDir . '/' . $retinaRelative) || str_contains($m[1], '@2x.')) {
                        continue;
                    }
                    $width = (int) rtrim($descriptor, 'w');
                    $retinaUrl = preg_replace('/\.([a-z0-9]+)$/i', '@2x.$1', $url);
                    return 'srcset="' . $m[1] . ', ' . $retinaUrl . ' ' . ($width * 2) . 'w"';
                }
                return $m[0];
            }, $html);
        };
        $minn->filterBlocks(static fn (Block $block, string $html): string => str_contains($html, 'srcset=') ? $filter($html) : $html);
    }
}
