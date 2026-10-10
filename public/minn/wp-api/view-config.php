<?php

/**
 * Entity view configurations (probes rest-view-config,
 * view-config-functions). An entity starts from the captured
 * configuration (data/view-configs.json): a post type (kind postType, in
 * that case) from the generic one with the type's "all items" label and
 * the default post type form; anything else from the plain default.
 * WP_View_Config_Data then hands it to the entity's filter, whose name is
 * the kind and name in lower case, so a kind asked in another case still
 * gets its type's patches. The core types patch theirs at 5:
 * pages set their own default view and layouts and add their status views
 * after the rest; patterns, templates and template parts set every key,
 * their views filled in from what is registered.
 */

/** @internal the captured configurations */
function _minn_view_configs(): array
{
    static $all = null;
    return $all ??= (array) Minn\Json::file(MINN_ENGINE_DIR . '/data/view-configs.json');
}

function wp_get_entity_view_config_hook_name($kind, $name)
{
    return 'get_entity_view_config_' . strtolower((string) $kind) . '_' . strtolower((string) $name);
}

function wp_get_entity_view_config($kind, $name)
{
    $all = _minn_view_configs();
    $config = $all['default'];
    if ($kind === 'postType') {
        $config = $all['postType'];
        $type = get_post_type_object((string) $name);
        $config['view_list'][0]['title'] = $type ? $type->labels->all_items : __('All items');
        $config['form'] = _wp_get_default_posttype_form();
    }
    return (new WP_View_Config_Data(['version' => 1] + $config))->apply_filters($kind, $name);
}

function _wp_get_default_posttype_form()
{
    return _minn_view_configs()['postType']['form'];
}

function _wp_get_entity_view_config_posttype_page($data)
{
    $page = _minn_view_configs()['page'];
    return $data->set(['default_view' => $page['default_view'], 'default_layouts' => $page['default_layouts']], 1)->merge(['view_list' => array_slice($page['view_list'], 1)], 1);
}

function _wp_get_entity_view_config_posttype_wp_block($data)
{
    $views = [];
    foreach (WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered() as $category) {
        $views[] = ['title' => $category['label'], 'slug' => $category['name']];
    }
    // Uncategorized among the categories, all by title regardless of case.
    $views[] = ['title' => __('Uncategorized'), 'slug' => 'uncategorized'];
    usort($views, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));
    return _minn_view_config_set($data, 'wp_block', $views);
}

function _wp_get_entity_view_config_posttype_wp_template($data)
{
    $author = (string) wp_get_theme()->get('Name');
    return _minn_view_config_set($data, 'wp_template', [['title' => $author, 'slug' => $author, 'view' => ['filters' => [['field' => 'author', 'operator' => 'is', 'value' => $author, 'isLocked' => true]]]]]);
}

function _wp_get_entity_view_config_posttype_wp_template_part($data)
{
    // A view per template part area, General last.
    $areas = get_allowed_block_template_part_areas();
    usort($areas, static fn ($a, $b) => ($a['area'] === 'uncategorized') <=> ($b['area'] === 'uncategorized'));
    $views = array_map(static fn ($area) => ['title' => $area['label'], 'slug' => $area['area'], 'view' => ['filters' => [['field' => 'area', 'operator' => 'is', 'value' => $area['area'], 'isLocked' => true]]]], $areas);
    return _minn_view_config_set($data, 'wp_template_part', $views);
}

/** @internal a core type's captured configuration set on the data, with views of its own after its fixed ones */
function _minn_view_config_set(WP_View_Config_Data $data, string $type, array $views): WP_View_Config_Data
{
    $config = _minn_view_configs()[$type];
    $config['view_list'] = array_merge($config['view_list'], $views);
    return $data->set(array_intersect_key($config, array_flip(['default_view', 'default_layouts', 'view_list', 'form'])), 1);
}
