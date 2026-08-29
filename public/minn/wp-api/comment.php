<?php
/** Comments: reads, counts, the writers. Behaviour from contracts/fixtures/api/media.json. */

use Minn\Content\Comments;
use Minn\Runtime\Runtime;

/** @internal */
function _minn_comments(): Comments
{
    return new Comments(Runtime::current()->db);
}

function get_comment($comment = null, $output = OBJECT)
{
    if ($comment === null) {
        $comment = $GLOBALS['comment'] ?? null;
    }
    if ($comment instanceof WP_Comment) {
        $object = $comment;
    } elseif (is_object($comment) && isset($comment->comment_ID)) {
        $object = new WP_Comment($comment);
    } else {
        $row = (int) $comment > 0 ? _minn_comments()->find((int) $comment) : null;
        if ($row === null) {
            return null;
        }
        $object = new WP_Comment((object) $row);
    }
    $object = apply_filters('get_comment', $object);
    if ($output === ARRAY_A) {
        return $object->to_array();
    }
    if ($output === ARRAY_N) {
        return array_values($object->to_array());
    }
    return $object;
}

function get_comments($args = '')
{
    $args = wp_parse_args($args, ['post_id' => 0, 'post__in' => [], 'status' => 'all', 'number' => '', 'offset' => 0, 'orderby' => 'comment_date_gmt', 'order' => 'DESC', 'fields' => '', 'count' => false, 'parent' => '', 'type' => '', 'author_email' => '', 'user_id' => '', 'search' => '', 'include_unapproved' => [], 'comment__in' => [], 'comment__not_in' => [], 'post_status' => '', 'post_type' => '', 'author__in' => [], 'date_query' => null, 'hierarchical' => false]);
    $db = Runtime::current()->db;
    $where = [];
    $params = [];
    if (!empty($args['post_id'])) {
        $where[] = 'c.comment_post_ID = ?';
        $params[] = (int) $args['post_id'];
    }
    if (!empty($args['post__in'])) {
        $ids = array_map('intval', (array) $args['post__in']);
        $where[] = 'c.comment_post_ID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    $status = $args['status'];
    $statuses = [];
    foreach ((array) $status as $one) {
        $statuses[] = match ((string) $one) {
            'hold', '0' => '0',
            'approve', '1' => '1',
            'all', '' => 'all',
            default => (string) $one,
        };
    }
    if (!in_array('all', $statuses, true) && !in_array('any', $statuses, true)) {
        $where[] = 'c.comment_approved IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        array_push($params, ...$statuses);
    } elseif (in_array('all', $statuses, true)) {
        $where[] = "c.comment_approved IN ('0', '1')";
    }
    if ($args['parent'] !== '' && $args['parent'] !== null) {
        $where[] = 'c.comment_parent = ?';
        $params[] = (int) $args['parent'];
    }
    if ($args['type'] !== '') {
        $types = array_map(static fn ($t) => $t === 'comment' ? 'comment' : (string) $t, (array) $args['type']);
        $where[] = 'c.comment_type IN (' . implode(',', array_fill(0, count($types), '?')) . ')';
        array_push($params, ...$types);
    }
    if ($args['author_email'] !== '') {
        $where[] = 'c.comment_author_email = ?';
        $params[] = (string) $args['author_email'];
    }
    if ($args['user_id'] !== '' && $args['user_id'] !== null) {
        $where[] = 'c.user_id = ?';
        $params[] = (int) $args['user_id'];
    }
    if (!empty($args['comment__in'])) {
        $ids = array_map('intval', (array) $args['comment__in']);
        $where[] = 'c.comment_ID IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if (!empty($args['comment__not_in'])) {
        $ids = array_map('intval', (array) $args['comment__not_in']);
        $where[] = 'c.comment_ID NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
        array_push($params, ...$ids);
    }
    if ($args['search'] !== '') {
        $needle = '%' . addcslashes((string) $args['search'], '%_\\') . '%';
        $where[] = '(c.comment_author LIKE ? OR c.comment_author_email LIKE ? OR c.comment_author_url LIKE ? OR c.comment_author_IP LIKE ? OR c.comment_content LIKE ?)';
        array_push($params, $needle, $needle, $needle, $needle, $needle);
    }
    $clause = $where === [] ? '1=1' : implode(' AND ', $where);
    if ($args['count']) {
        return (int) $db->value("SELECT COUNT(*) FROM {$db->table('comments')} c WHERE {$clause}", $params);
    }
    $column = match ((string) $args['orderby']) {
        'comment_date' => 'c.comment_date',
        'comment_ID', 'ID' => 'c.comment_ID',
        'comment_post_ID' => 'c.comment_post_ID',
        'comment_author' => 'c.comment_author',
        'none' => '',
        default => 'c.comment_date_gmt',
    };
    $order = strtoupper((string) $args['order']) === 'ASC' ? 'ASC' : 'DESC';
    $orderClause = $column === '' ? '' : " ORDER BY {$column} {$order}, c.comment_ID {$order}";
    $limit = '';
    $limitParams = [];
    if ($args['number'] !== '' && (int) $args['number'] > 0) {
        $limit = ' LIMIT ? OFFSET ?';
        $limitParams = [(int) $args['number'], (int) $args['offset']];
    }
    $rows = $db->rows("SELECT c.* FROM {$db->table('comments')} c WHERE {$clause}{$orderClause}{$limit}", [...$params, ...$limitParams]);
    if ($args['fields'] === 'ids') {
        return array_map(static fn (array $r) => (int) $r['comment_ID'], $rows);
    }
    $comments = array_map(static fn (array $r) => new WP_Comment((object) $r), $rows);
    return apply_filters('the_comments', $comments, null);
}

function get_comments_number($post = 0)
{
    $post = get_post($post);
    if ($post === null) {
        $count = 0;
        $post_id = 0;
    } else {
        $count = $post->comment_count;
        $post_id = $post->ID;
    }
    return apply_filters('get_comments_number', $count, $post_id);
}

function comments_number($zero = false, $one = false, $more = false, $post = 0)
{
    echo get_comments_number_text($zero, $one, $more, $post);
}

function get_comments_number_text($zero = false, $one = false, $more = false, $post = 0)
{
    $number = (int) get_comments_number($post);
    if ($number > 1) {
        $output = $more === false ? sprintf('%s Comments', number_format_i18n($number)) : str_replace('%', number_format_i18n($number), (string) $more);
    } elseif ($number === 0) {
        $output = $zero === false ? 'No Comments' : $zero;
    } else {
        $output = $one === false ? '1 Comment' : $one;
    }
    return apply_filters('comments_number', $output, $number);
}

function wp_count_comments($post_id = 0)
{
    $post_id = (int) $post_id;
    $filtered = apply_filters('wp_count_comments', [], $post_id);
    if (!empty($filtered)) {
        return (object) $filtered;
    }
    $db = Runtime::current()->db;
    $sql = "SELECT comment_approved, COUNT(*) AS total FROM {$db->table('comments')}";
    $params = [];
    if ($post_id > 0) {
        $sql .= ' WHERE comment_post_ID = ?';
        $params[] = $post_id;
    }
    $rows = $db->rows($sql . ' GROUP BY comment_approved', $params);
    $counts = ['approved' => 0, 'spam' => 0, 'trash' => 0, 'post-trashed' => 0, 'all' => 0, 'total_comments' => 0, 'moderated' => 0];
    foreach ($rows as $row) {
        $n = (int) $row['total'];
        switch ((string) $row['comment_approved']) {
            case '1':
                $counts['approved'] += $n;
                break;
            case '0':
                $counts['moderated'] += $n;
                break;
            case 'spam':
                $counts['spam'] += $n;
                break;
            case 'trash':
                $counts['trash'] += $n;
                break;
            case 'post-trashed':
                $counts['post-trashed'] += $n;
                break;
        }
    }
    $counts['all'] = $counts['approved'] + $counts['moderated'];
    $counts['total_comments'] = $counts['all'] + $counts['spam'];
    return (object) $counts;
}

function wp_insert_comment($commentdata)
{
    $data = wp_unslash((array) $commentdata);
    $now = current_time('mysql');
    $columns = [
        'comment_post_ID' => (int) ($data['comment_post_ID'] ?? 0),
        'comment_author' => (string) ($data['comment_author'] ?? ''),
        'comment_author_email' => (string) ($data['comment_author_email'] ?? ''),
        'comment_author_url' => (string) ($data['comment_author_url'] ?? ''),
        'comment_author_IP' => (string) ($data['comment_author_IP'] ?? ''),
        'comment_date' => (string) ($data['comment_date'] ?? $now),
        'comment_date_gmt' => (string) ($data['comment_date_gmt'] ?? get_gmt_from_date((string) ($data['comment_date'] ?? $now))),
        'comment_content' => (string) ($data['comment_content'] ?? ''),
        'comment_karma' => (int) ($data['comment_karma'] ?? 0),
        'comment_approved' => (string) ($data['comment_approved'] ?? '1'),
        'comment_agent' => (string) ($data['comment_agent'] ?? ''),
        'comment_type' => (string) (($data['comment_type'] ?? '') === '' ? 'comment' : $data['comment_type']),
        'comment_parent' => (int) ($data['comment_parent'] ?? 0),
        'user_id' => (int) ($data['user_id'] ?? 0),
    ];
    $id = _minn_comments()->insert($columns);
    if ($columns['comment_approved'] === '1') {
        wp_update_comment_count($columns['comment_post_ID']);
    }
    wp_cache_delete($columns['comment_post_ID'], 'posts');
    $comment = get_comment($id);
    do_action('wp_insert_comment', $id, $comment);
    return $id;
}

function wp_update_comment_count($post_id, $do_deferred = false)
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }
    $post = get_post($post_id);
    if ($post === null) {
        return false;
    }
    $old = (int) $post->comment_count;
    _minn_comments()->recount($post_id);
    wp_cache_delete($post_id, 'posts');
    $new = (int) get_post($post_id)->comment_count;
    do_action('wp_update_comment_count', $post_id, $new, $old);
    do_action("edit_post_{$post->post_type}", $post_id, get_post($post_id));
    do_action('edit_post', $post_id, get_post($post_id));
    return true;
}

function wp_update_comment($commentarr, $wp_error = false)
{
    $data = wp_unslash((array) $commentarr);
    $comment = get_comment((int) ($data['comment_ID'] ?? 0));
    if ($comment === null) {
        return $wp_error ? new WP_Error('invalid_comment_id', 'Invalid comment ID.') : false;
    }
    $columns = [];
    foreach (['comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_date', 'comment_date_gmt', 'comment_content', 'comment_karma', 'comment_approved', 'comment_agent', 'comment_type', 'comment_parent', 'user_id'] as $key) {
        if (array_key_exists($key, $data) && (string) $data[$key] !== (string) $comment->{$key}) {
            $columns[$key] = $data[$key];
        }
    }
    if (isset($columns['comment_approved'])) {
        $columns['comment_approved'] = match ((string) $columns['comment_approved']) {
            'hold' => '0',
            'approve' => '1',
            default => (string) $columns['comment_approved'],
        };
    }
    $columns = apply_filters('wp_update_comment_data', $columns, $comment->to_array(), $data);
    if (is_wp_error($columns)) {
        return $wp_error ? $columns : 0;
    }
    if ($columns === []) {
        return 0;
    }
    _minn_comments()->update($comment->comment_ID, $columns);
    wp_update_comment_count((int) $comment->comment_post_ID);
    do_action('edit_comment', $comment->comment_ID, get_comment($comment->comment_ID)->to_array());
    return 1;
}

function wp_set_comment_status($comment_id, $comment_status, $wp_error = false)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    $status = match ((string) $comment_status) {
        'hold', '0' => '0',
        'approve', '1' => '1',
        'spam' => 'spam',
        'trash' => 'trash',
        default => null,
    };
    if ($status === null) {
        return false;
    }
    _minn_comments()->update($comment->comment_ID, ['comment_approved' => $status]);
    wp_update_comment_count((int) $comment->comment_post_ID);
    do_action('wp_set_comment_status', $comment->comment_ID, $comment_status);
    return true;
}

function wp_delete_comment($comment_id, $force_delete = false)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    if (!$force_delete && EMPTY_TRASH_DAYS && !in_array($comment->comment_approved, ['trash', 'spam'], true)) {
        return wp_trash_comment($comment->comment_ID);
    }
    do_action('delete_comment', $comment->comment_ID, $comment);
    $db = Runtime::current()->db;
    $db->execute("UPDATE {$db->table('comments')} SET comment_parent = ? WHERE comment_parent = ?", [(int) $comment->comment_parent, $comment->comment_ID]);
    $db->execute("DELETE FROM {$db->table('commentmeta')} WHERE comment_id = ?", [$comment->comment_ID]);
    _minn_comments()->delete((int) $comment->comment_ID);
    do_action('deleted_comment', $comment->comment_ID, $comment);
    wp_update_comment_count((int) $comment->comment_post_ID);
    return true;
}

function wp_trash_comment($comment_id)
{
    if (!EMPTY_TRASH_DAYS) {
        return wp_delete_comment($comment_id, true);
    }
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    do_action('trash_comment', $comment->comment_ID, $comment);
    if (wp_set_comment_status($comment->comment_ID, 'trash')) {
        delete_comment_meta($comment->comment_ID, '_wp_trash_meta_status');
        delete_comment_meta($comment->comment_ID, '_wp_trash_meta_time');
        add_comment_meta($comment->comment_ID, '_wp_trash_meta_status', $comment->comment_approved);
        add_comment_meta($comment->comment_ID, '_wp_trash_meta_time', time());
        do_action('trashed_comment', $comment->comment_ID, $comment);
        return true;
    }
    return false;
}

function wp_untrash_comment($comment_id)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return false;
    }
    $status = (string) (get_comment_meta($comment->comment_ID, '_wp_trash_meta_status', true) ?: '0');
    if (wp_set_comment_status($comment->comment_ID, $status)) {
        delete_comment_meta($comment->comment_ID, '_wp_trash_meta_status');
        delete_comment_meta($comment->comment_ID, '_wp_trash_meta_time');
        return true;
    }
    return false;
}

function wp_spam_comment($comment_id)
{
    return wp_set_comment_status($comment_id, 'spam');
}

function wp_count_terms($args = [], $deprecated = '')
{
    if (!is_array($args) || (is_array($args) && !isset($args['taxonomy']) && !empty($deprecated))) {
        $legacy = is_array($deprecated) ? $deprecated : [];
        $legacy['taxonomy'] = $args;
        $args = $legacy;
    }
    $args = wp_parse_args($args, ['hide_empty' => false]);
    $args['fields'] = 'count';
    return get_terms($args);
}

function get_comment_author($comment_id = 0)
{
    $comment = get_comment($comment_id);
    $author = $comment === null || $comment->comment_author === '' ? 'Anonymous' : $comment->comment_author;
    return apply_filters('get_comment_author', $author, $comment?->comment_ID, $comment);
}

function get_comment_text($comment_id = 0, $args = [])
{
    $comment = get_comment($comment_id);
    return apply_filters('get_comment_text', $comment === null ? '' : $comment->comment_content, $comment, $args);
}

function get_comment_date($format = '', $comment_id = 0)
{
    $comment = get_comment($comment_id);
    if ($comment === null) {
        return '';
    }
    return apply_filters('get_comment_date', mysql2date($format === '' ? get_option('date_format') : $format, $comment->comment_date), $format, $comment);
}

function get_comment_link($comment = null, $args = [])
{
    $comment = get_comment($comment);
    if ($comment === null) {
        return '';
    }
    return apply_filters('get_comment_link', get_permalink((int) $comment->comment_post_ID) . '#comment-' . $comment->comment_ID, $comment, $args, get_post((int) $comment->comment_post_ID));
}

function comments_open($post = null)
{
    $post = get_post($post);
    return (bool) apply_filters('comments_open', $post !== null && $post->comment_status === 'open', $post?->ID);
}

function pings_open($post = null)
{
    $post = get_post($post);
    return (bool) apply_filters('pings_open', $post !== null && $post->ping_status === 'open', $post?->ID);
}

function wp_comment_reply($position = 1, $checkbox = false, $mode = 'single', $table_row = true)
{
    return '';
}

function wp_defer_comment_counting($defer = null)
{
    return false;
}

function get_comment_count($post_id = 0)
{
    $counts = wp_count_comments($post_id);
    return ['approved' => $counts->approved, 'awaiting_moderation' => $counts->moderated, 'spam' => $counts->spam, 'trash' => $counts->trash, 'post-trashed' => $counts->{'post-trashed'}, 'total_comments' => $counts->total_comments, 'all' => $counts->all];
}
