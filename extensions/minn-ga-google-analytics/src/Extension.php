<?php

declare(strict_types=1);

namespace Minn\Ext\GoogleAnalytics;

use Minn\Extension\Extension as MinnExtension;
use Minn\Extension\Seams;
use Minn\Support\Html;
use Minn\Support\Serialized;

/** The gtag snippet from gap_options: the tracking id, in the head or the footer, with the anonymize flag. */
final class Extension implements MinnExtension
{
    public function register(Seams $minn): void
    {
        $options = Serialized::decode((string) ($minn->site->option('gap_options') ?? ''));
        $options = is_array($options) ? $options : [];
        $id = trim((string) ($options['gap_id'] ?? ''));
        if ($id === '' || !preg_match('/^(G|UA|AW|DC)-[A-Z0-9-]+$/i', $id)) {
            return;
        }
        // The plugin stays out of the admin; the engine's admin is not this document anyway.
        $render = static function () use ($id, $options): string {
            $config = !empty($options['gap_anonymize']) ? "gtag('config', '" . $id . "', { 'anonymize_ip': true });" : "gtag('config', '" . $id . "');";
            $custom = trim((string) ($options['gap_custom_code'] ?? ''));
            return "\n\t\t<!-- GA Google Analytics @ https://m0n.co/ga -->\n"
                . "\t\t" . '<script async src="https://www.googletagmanager.com/gtag/js?id=' . Html::attr($id) . '"></script>' . "\n"
                . "\t\t<script>\n\t\t\twindow.dataLayer = window.dataLayer || [];\n\t\t\tfunction gtag(){dataLayer.push(arguments);}\n\t\t\tgtag('js', new Date());\n"
                . "\t\t\t" . $config . "\n"
                . ($custom === '' ? '' : "\t\t\t" . $custom . "\n")
                . "\t\t</script>\n\n";
        };
        if (($options['gap_location'] ?? 'header') === 'footer') {
            $minn->footer($render);
        } else {
            $minn->head($render);
        }
    }
}
