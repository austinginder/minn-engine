<?php
/**
 * The icons API (probe icons): collections and icons through their
 * registries, and an icon printed as SVG. The reference's own icon library
 * is GPL art the engine does not ship, so the default collection is
 * registered without icons.
 */

function wp_register_icon($icon_name, $args)
{
    return WP_Icons_Registry::get_instance()->register($icon_name, $args);
}

function wp_unregister_icon($icon_name)
{
    return WP_Icons_Registry::get_instance()->unregister($icon_name);
}

/**
 * An icon's SVG with its sizing and accessibility attributes: a label makes
 * it an image named by that label, otherwise it is hidden from assistive
 * technology; a numeric size sets its width and height (24 by default).
 */
function wp_get_icon($name, $args = [])
{
    $icon = WP_Icons_Registry::get_instance()->get_registered_icon($name);
    $tags = new WP_HTML_Tag_Processor((string) ($icon['content'] ?? ''));
    if ($icon === null || !$tags->next_tag('svg')) {
        return '';
    }
    $args = (array) $args;
    $label = is_string($args['label'] ?? null) ? $args['label'] : '';
    $size = $args['size'] ?? 24;
    $attributes = ($label === '' ? ['aria-hidden' => 'true', 'focusable' => 'false'] : ['aria-label' => $label, 'role' => 'img']) + (empty($args['class']) ? [] : ['class' => (string) $args['class']]) + (is_numeric($size) ? ['height' => (string) $size, 'width' => (string) $size] : []);
    ksort($attributes);
    foreach ($attributes as $attribute => $value) {
        $tags->set_attribute($attribute, $value);
    }
    return $tags->get_updated_html();
}

function wp_register_icon_collection($slug, $args)
{
    return WP_Icon_Collections_Registry::get_instance()->register($slug, $args);
}

function wp_unregister_icon_collection($slug)
{
    return WP_Icon_Collections_Registry::get_instance()->unregister($slug);
}

/** The reference registers its icon library here; the engine ships none of that art. */
function _wp_register_default_icons()
{
}

function _wp_register_default_icon_collections()
{
    WP_Icon_Collections_Registry::get_instance()->register('core', ['label' => __('WordPress'), 'description' => __('Default icon collection.')]);
}
