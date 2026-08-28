<?php

declare(strict_types=1);

namespace Minn\Ext\SimpleCustomCss;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;
use Minn\Support\Serialized;

/** The site's custom stylesheet from the sccss_settings option, in the head as the plugin printed it. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $minn->head(static function (Seams $s): string {
            $settings = Serialized::decode((string) ($s->site->option('sccss_settings') ?? ''));
            $css = is_array($settings) ? (string) ($settings['sccss-content'] ?? '') : '';
            if (trim($css) === '') {
                return '';
            }
            return '<style id="sccss">' . str_replace('</style', '<\/style', $css) . "\n</style>\n";
        });
    }
}
