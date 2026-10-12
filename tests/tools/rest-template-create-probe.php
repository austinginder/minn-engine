<?php
/**
 * Creating templates and template parts (probe rest-template-create):
 * POST wp/v2/templates and /template-parts with a new slug, none, a slug
 * the site already holds (made unique), a slug only a theme file holds
 * (the site's copy of that file), a draft (published anyway), a mixed-case
 * slug, and signed out. The ids, dates and author are masked; the copies
 * made here, and the area terms their parts brought, are deleted on the
 * way out.
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
wp_set_current_user(1);
if (!did_action('init')) {
    do_action('init');
}
$made = [];
// A part's area is a term the part brings into being; the ones made here go too.
$areas = static fn (): array => array_map('intval', (array) get_terms(['taxonomy' => 'wp_template_part_area', 'hide_empty' => false, 'fields' => 'ids']));
$areasBefore = $areas();
register_shutdown_function(static function () use (&$made, $areas, $areasBefore): void {
    foreach ($made as $id) {
        if (is_int($id) && $id > 1) {
            wp_delete_post($id, true);
        }
    }
    foreach (array_diff($areas(), $areasBefore) as $term) {
        if (is_int($term) && $term > 1) {
            wp_delete_term($term, 'wp_template_part_area');
        }
    }
});
$do = static function (string $route, array $params) use (&$made) {
    $request = new WP_REST_Request('POST', $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);
    $data = $response->get_data();
    if (is_array($data) && isset($data['wp_id']) && is_int($data['wp_id'])) {
        $made[] = $data['wp_id'];
    }
    if (is_array($data)) {
        foreach (['wp_id', 'modified', 'date', 'author'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = '{' . $key . '}';
            }
        }
    }
    return ['status' => $response->get_status(), 'data' => $data];
};

$say('a custom template', $do('/wp/v2/templates', ['slug' => 'zz-custom', 'title' => 'ZZ custom', 'content' => '<!-- wp:paragraph --><p>x</p><!-- /wp:paragraph -->', 'description' => 'ZZ desc']));
$say('no slug', $do('/wp/v2/templates', ['title' => 'ZZ none']));
$say('the same slug again', $do('/wp/v2/templates', ['slug' => 'zz-custom', 'title' => 'ZZ again']));
$say('a third time', $do('/wp/v2/templates', ['slug' => 'zz-custom', 'title' => 'ZZ third']));
$say('a theme file\'s slug', $do('/wp/v2/templates', ['slug' => 'index', 'title' => 'ZZ index', 'content' => 'i']));
$say('a draft', $do('/wp/v2/templates', ['slug' => 'zz-draft', 'title' => 'ZZ draft', 'status' => 'draft']));
$say('a mixed-case slug', $do('/wp/v2/templates', ['slug' => 'zz-Mixed', 'title' => 'ZZ mixed']));
$say('a template part', $do('/wp/v2/template-parts', ['slug' => 'zz-part', 'title' => 'ZZ part', 'content' => 'p', 'area' => 'header']));
$say('a part with no area', $do('/wp/v2/template-parts', ['slug' => 'zz-part-plain', 'title' => 'ZZ part plain']));
wp_set_current_user(0);
$say('signed out', $do('/wp/v2/templates', ['slug' => 'zz-signed-out']));

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
