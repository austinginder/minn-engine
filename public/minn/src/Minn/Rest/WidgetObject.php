<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\WidgetAreas;

/**
 * A widget as wp/v2/widgets shows it (probe rest-widgets): its id and base,
 * its sidebar, its markup as that sidebar wraps it (nothing among the
 * inactive widgets) and, in the edit context, its settings form and its
 * settings: serialized and base64 encoded, signed with wp_hash, and raw
 * when the widget shows its instance in REST. Links to itself, its type
 * and its sidebar; through rest_prepare_widget.
 */
final readonly class WidgetObject
{
    public function __construct(private RestUrl $url)
    {
    }

    /** A widget in a sidebar, its form and settings in the edit context. @return array<string, mixed> */
    public function view(string $widgetId, string $sidebarId, Context $context): array
    {
        $parsed = WidgetAreas::parse($widgetId);
        $object = \_minn_widget_factory()->get_widget_object($parsed['id_base']);
        $item = [
            'id' => $widgetId,
            'id_base' => $parsed['id_base'],
            'sidebar' => $sidebarId,
            'rendered' => $sidebarId === WidgetAreas::INACTIVE ? '' : trim(\wp_render_widget($widgetId, $sidebarId)),
        ];
        if ($context->isEdit()) {
            $item['rendered_form'] = trim((string) ($object instanceof \WP_Widget ? self::form($object, $parsed['number'] ?? -1) : \wp_render_widget_control($widgetId)));
            if ($object instanceof \WP_Widget) {
                $item['instance'] = self::instance($object, $parsed['number'] ?? -1);
            }
        }
        $item['_links'] = [
            'self' => [['href' => $this->url->to('/wp/v2/widgets/' . $widgetId), 'targetHints' => ['allow' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']]]],
            'collection' => [['href' => $this->url->to('/wp/v2/widgets')]],
            'about' => [['embeddable' => true, 'href' => $this->url->to('/wp/v2/widget-types/' . $parsed['id_base'])]],
            'wp:sidebar' => [['href' => $this->url->to('/wp/v2/sidebars/' . $sidebarId . '/')]],
            'curies' => RestUrl::curies(),
        ];
        return RuntimePrepare::item('rest_prepare_widget', $item, static fn () => $GLOBALS['wp_registered_widgets'][$widgetId] ?? []);
    }

    /** A widget's settings, encoded and signed, and raw when it shows them. @return array<string, mixed> */
    public static function instance(\WP_Widget $object, int $number): array
    {
        $all = $object->get_settings();
        $instance = is_array($all) && isset($all[$number]) ? (array) $all[$number] : [];
        $serialized = serialize($instance);
        $out = ['encoded' => base64_encode($serialized), 'hash' => \wp_hash($serialized)];
        if (!empty($object->widget_options['show_instance_in_rest'])) {
            $out['raw'] = $instance;
        }
        return $out;
    }

    private static function form(\WP_Widget $object, int $number): string
    {
        $all = $object->get_settings();
        $object->_set($number);
        ob_start();
        $object->form(is_array($all) && isset($all[$number]) ? (array) $all[$number] : []);
        return (string) ob_get_clean();
    }
}
