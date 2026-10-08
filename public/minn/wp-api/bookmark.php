<?php

use Minn\Content\Links;
use Minn\Runtime\Runtime;

/** @internal the links table */
function _minn_links(): Links
{
    return new Links(Runtime::current()->db);
}

/** A link with its category ids, sanitized for a context, as an object or array; null when there is none. */
function get_bookmark($bookmark, $output = OBJECT, $filter = 'raw')
{
    if (is_object($bookmark)) {
        $link = $bookmark;
    } else {
        $row = _minn_links()->find((int) $bookmark);
        if ($row === null) {
            return null;
        }
        $link = (object) $row;
        $link->link_category = array_values(array_unique(array_map('intval', (array) wp_get_object_terms((int) $link->link_id, 'link_category', ['fields' => 'ids']))));
    }
    $link = sanitize_bookmark($link, $filter);
    return match ($output) {
        ARRAY_A => get_object_vars($link),
        ARRAY_N => array_values(get_object_vars($link)),
        default => $link,
    };
}

/** Deprecated since 2.1: get_bookmark. */
function get_link($bookmark_id, $output = OBJECT, $filter = 'raw')
{
    _deprecated_function(__FUNCTION__, '2.1.0', 'get_bookmark()');
    return get_bookmark($bookmark_id, $output, $filter);
}

/** One field of a link, sanitized for a context; "" when the link or the field is missing. */
function get_bookmark_field($field, $bookmark, $context = 'display')
{
    $link = get_bookmark((int) $bookmark);
    if (!is_object($link) || !isset($link->$field)) {
        return '';
    }
    return sanitize_bookmark_field($field, $link->$field, $link->link_id, $context);
}

/** The links asked for (Content\Links), through get_bookmarks; none for a category name that is not there. */
function get_bookmarks($args = '')
{
    $r = wp_parse_args($args, ['orderby' => 'name', 'order' => 'ASC', 'limit' => -1, 'category' => '', 'category_name' => '', 'hide_invisible' => 1, 'show_updated' => 0, 'include' => '', 'exclude' => '', 'search' => '']);
    if (!empty($r['category_name'])) {
        $category = get_term_by('name', $r['category_name'], 'link_category');
        if (!$category) {
            return [];
        }
        $r['category'] = $category->term_id;
    }
    $categories = empty($r['include']) ? wp_parse_id_list($r['category']) : [];
    $links = array_map(static fn (array $row) => (object) $row, _minn_links()->matching($r, array_values(array_filter($categories)), (int) get_option('links_recently_updated_time')));
    return apply_filters('get_bookmarks', $links, $r);
}

/** Every field a link (object or array) carries, sanitized for a context. */
function sanitize_bookmark($bookmark, $context = 'display')
{
    $object = is_object($bookmark);
    $fields = $object ? get_object_vars($bookmark) : (array) $bookmark;
    $id = (int) ($fields['link_id'] ?? 0);
    foreach ([...Links::FIELDS, 'link_category'] as $field) {
        if (array_key_exists($field, $fields)) {
            $fields[$field] = sanitize_bookmark_field($field, $fields[$field], $id, $context);
        }
    }
    if (!$object) {
        return $fields;
    }
    foreach ($fields as $field => $value) {
        $bookmark->$field = $value;
    }
    return $bookmark;
}

/**
 * One link field for a context: ids and ratings are integers, categories a
 * list of them, visibility only Y and N, a target only _blank or _top; then
 * edit_{field} and an escape (a textarea's for notes), pre_{field} for the
 * database, or {field} for display, escaped for an attribute or a script.
 */
function sanitize_bookmark_field($field, $value, $bookmark_id, $context)
{
    $integer = in_array($field, ['link_id', 'link_rating'], true);
    $value = match ($field) {
        'link_category' => array_map('absint', (array) $value),
        'link_visible' => preg_replace('/[^YNyn]/', '', (string) $value),
        'link_target' => in_array($value, ['_top', '_blank'], true) ? $value : '',
        default => $integer ? (int) $value : $value,
    };
    if ($field === 'link_category' || $context === 'raw') {
        return $value;
    }
    if ($context === 'edit') {
        $value = apply_filters("edit_{$field}", $value, $bookmark_id);
        $value = $field === 'link_notes' ? esc_html($value) : esc_attr($value);
    } elseif ($context === 'db') {
        $value = apply_filters("pre_{$field}", $value);
    } else {
        $value = apply_filters($field, $value, $bookmark_id, $context);
        $value = match ($context) {
            'attribute' => esc_attr($value),
            'js' => esc_js($value),
            default => $value,
        };
    }
    return $integer ? (int) $value : $value;
}

/** Nothing is kept between calls here; the link's term cache is cleared. */
function clean_bookmark_cache($bookmark_id)
{
    clean_object_term_cache($bookmark_id, 'link');
}

/**
 * A link saved: the fields sanitized for the database and unslashed, a
 * missing name taken from the address, the categories set (the default
 * category when none are given); add_link or edit_link after. 0 without
 * an address.
 */
function wp_insert_link($linkdata, $wp_error = false)
{
    $r = wp_unslash(sanitize_bookmark(wp_parse_args($linkdata, ['link_id' => 0, 'link_name' => '', 'link_url' => '', 'link_rating' => 0]), 'db'));
    if (trim((string) $r['link_url']) === '') {
        return 0;
    }
    $update = !empty($r['link_id']);
    $fields = ['link_url' => $r['link_url'], 'link_name' => trim((string) $r['link_name']) === '' ? $r['link_url'] : $r['link_name'], 'link_image' => $r['link_image'] ?? '', 'link_target' => $r['link_target'] ?? '', 'link_description' => $r['link_description'] ?? '', 'link_visible' => !empty($r['link_visible']) ? $r['link_visible'] : 'Y', 'link_owner' => !empty($r['link_owner']) ? $r['link_owner'] : get_current_user_id(), 'link_rating' => (int) $r['link_rating'], 'link_updated' => current_time('mysql'), 'link_rel' => $r['link_rel'] ?? '', 'link_notes' => $r['link_notes'] ?? '', 'link_rss' => $r['link_rss'] ?? ''];
    $id = _minn_links()->save($update ? (int) $r['link_id'] : 0, $fields);
    wp_set_link_cats($id, $r['link_category'] ?? []);
    do_action($update ? 'edit_link' : 'add_link', $id);
    clean_bookmark_cache($id);
    return $id;
}

/** A link's fields changed, the rest kept (its categories too, unless new ones are given); 0 for no link. */
function wp_update_link($linkdata)
{
    $row = _minn_links()->find((int) ($linkdata['link_id'] ?? 0));
    if ($row === null) {
        return 0;
    }
    $categories = empty($linkdata['link_category']) ? wp_get_link_cats($row['link_id']) : $linkdata['link_category'];
    return wp_insert_link(array_merge(wp_slash(sanitize_bookmark($row, 'raw')), $linkdata, ['link_category' => $categories]));
}

/** A link and its category relationships removed, between delete_link and deleted_link. */
function wp_delete_link($link_id)
{
    do_action('delete_link', $link_id);
    wp_delete_object_term_relationships($link_id, 'link_category');
    _minn_links()->delete((int) $link_id);
    do_action('deleted_link', $link_id);
    clean_bookmark_cache($link_id);
    return true;
}

/** A link's category ids. */
function wp_get_link_cats($link_id = 0)
{
    return array_values(array_map('intval', (array) wp_get_object_terms($link_id, 'link_category', ['fields' => 'ids'])));
}

/** A link's categories set to those given, or the default link category when none are. */
function wp_set_link_cats($link_id = 0, $link_categories = [])
{
    if (!is_array($link_categories) || $link_categories === []) {
        $link_categories = [get_option('default_link_category')];
    }
    wp_set_object_terms($link_id, array_values(array_unique(array_map('intval', $link_categories))), 'link_category');
    clean_bookmark_cache($link_id);
}

/**
 * The links as list items: each a link (its description as the title,
 * its relation and target), an image when it has one and images are shown,
 * else its name; its description and rating after, when asked.
 */
function _walk_bookmarks($bookmarks, $args = '')
{
    $r = wp_parse_args($args, ['show_updated' => 0, 'show_description' => 0, 'show_images' => 1, 'show_name' => 0, 'before' => '<li>', 'after' => '</li>', 'between' => "\n", 'show_rating' => 0, 'link_before' => '', 'link_after' => '']);
    $output = '';
    foreach ((array) $bookmarks as $bookmark) {
        $recent = !empty($r['show_updated']) && !empty($bookmark->recently_updated);
        $desc = esc_attr(sanitize_bookmark_field('link_description', $bookmark->link_description, $bookmark->link_id, 'display'));
        $output .= $r['before'] . ($recent ? '<em>' : '') . _minn_bookmark_anchor($bookmark, $r, $desc) . ($recent ? '</em>' : '');
        if ($r['show_description'] && $desc !== '') {
            $output .= $r['between'] . $desc;
        }
        if ($r['show_rating']) {
            $output .= $r['between'] . sanitize_bookmark_field('link_rating', $bookmark->link_rating, $bookmark->link_id, 'display');
        }
        $output .= $r['after'] . "\n";
    }
    return $output;
}

/** @internal one link's anchor: the title its description and last update, then its image or its name */
function _minn_bookmark_anchor(object $bookmark, array $r, string $desc): string
{
    $name = esc_attr(sanitize_bookmark_field('link_name', $bookmark->link_name, $bookmark->link_id, 'display'));
    $title = $desc;
    if ($r['show_updated'] && isset($bookmark->link_updated_f) && !str_starts_with((string) $bookmark->link_updated_f, '00')) {
        $title .= ' (' . sprintf(__('Last updated: %s'), gmdate(get_option('links_updated_date_format'), (int) $bookmark->link_updated_f + (int) ((float) get_option('gmt_offset') * HOUR_IN_SECONDS))) . ')';
    }
    $alt = ' alt="' . $name . ($r['show_description'] ? ' ' . $title : '') . '"';
    $title = $title !== '' ? ' title="' . $title . '"' : '';
    $rel = (string) $bookmark->link_rel !== '' ? ' rel="' . esc_attr($bookmark->link_rel) . '"' : '';
    $target = (string) $bookmark->link_target !== '' ? ' target="' . $bookmark->link_target . '"' : '';
    $inner = $name;
    if (!empty($bookmark->link_image) && $r['show_images']) {
        $src = str_starts_with((string) $bookmark->link_image, 'http') ? $bookmark->link_image : get_option('siteurl') . $bookmark->link_image;
        $inner = "<img src=\"{$src}\"{$alt}{$title} />" . ($r['show_name'] ? " {$name}" : '');
    }
    return '<a href="' . (empty($bookmark->link_url) ? '#' : esc_url($bookmark->link_url)) . '"' . $rel . $title . $target . '>' . $r['link_before'] . $inner . $r['link_after'] . '</a>';
}

/**
 * The links, grouped under each link category that has some (a heading and
 * a blogroll list per group), or one list under the title; through
 * wp_list_bookmarks, printed unless echo is off.
 */
function wp_list_bookmarks($args = '')
{
    $r = wp_parse_args($args, ['orderby' => 'name', 'order' => 'ASC', 'limit' => -1, 'category' => '', 'exclude_category' => '', 'category_name' => '', 'hide_invisible' => 1, 'show_updated' => 0, 'echo' => 1, 'categorize' => 1, 'title_li' => __('Bookmarks'), 'title_before' => '<h2>', 'title_after' => '</h2>', 'category_orderby' => 'name', 'category_order' => 'ASC', 'class' => 'linkcat', 'category_before' => '<li id="%id" class="%class">', 'category_after' => '</li>']);
    $r['class'] = trim(implode(' ', array_map('sanitize_html_class', is_array($r['class']) ? $r['class'] : explode(' ', (string) $r['class']))));
    $groups = [];
    $cats = $r['categorize'] ? get_terms(['taxonomy' => 'link_category', 'name__like' => $r['category_name'], 'include' => $r['category'], 'exclude' => $r['exclude_category'], 'orderby' => $r['category_orderby'], 'order' => $r['category_order'], 'hierarchical' => 0]) : [];
    if (is_array($cats) && $cats !== []) {
        foreach ($cats as $cat) {
            $groups[] = ["linkcat-{$cat->term_id}", apply_filters('link_category', $cat->name), get_bookmarks(array_merge($r, ['category' => $cat->term_id]))];
        }
    } else {
        $groups[] = ["linkcat-{$r['category']}", empty($r['title_li']) ? null : $r['title_li'], get_bookmarks($r)];
    }
    $output = '';
    foreach ($groups as [$id, $heading, $bookmarks]) {
        if ($bookmarks === []) {
            continue;
        }
        $output .= $heading === null ? _walk_bookmarks($bookmarks, $r)
            : str_replace(['%id', '%class'], [$id, $r['class']], $r['category_before']) . $r['title_before'] . $heading . $r['title_after'] . "\n\t<ul class='xoxo blogroll'>\n" . _walk_bookmarks($bookmarks, $r) . "\n\t</ul>\n" . $r['category_after'] . "\n";
    }
    $html = apply_filters('wp_list_bookmarks', $output);
    if (!$r['echo']) {
        return $html;
    }
    echo $html;
}
