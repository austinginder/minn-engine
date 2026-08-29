<?php
/**
 * Behaviour probe for the query classes, the hook object, block templates
 * and hooked blocks, and the classic comment templating. Runs unchanged on
 * the reference (wp eval-file) and on the engine (tests/api.test.php).
 */

$log = [];
$say = static function (string $label, $value) use (&$log): void {
    $log[] = [$label, $value];
};
$out = static function (callable $fn): string { ob_start(); $fn(); return (string) ob_get_clean(); };
$err = static fn ($v) => is_wp_error($v) ? ['error' => $v->get_error_code()] : $v;
$norm = static fn (string $x): string => preg_replace('/\{[0-9a-f]{32,64}\}/', '%', preg_replace('/\s+/', ' ', str_replace($GLOBALS['wpdb']->prefix, 'wp_', $x)));
$sql = static fn ($s) => is_array($s) ? array_map(static fn ($x) => $norm((string) $x), $s) : $norm((string) $s);
foreach (['home_url', 'site_url', 'option_home', 'option_siteurl'] as $devHook) {
    remove_all_filters($devHook);
}
$wpdb = $GLOBALS['wpdb'];

// WP_Hook and $wp_filter.
$say('wp_filter init class', get_class($GLOBALS['wp_filter']['init']));
add_filter('minn_probe_hook', 'strtoupper', 12, 1);
add_filter('minn_probe_hook', 'trim');
$hook = $GLOBALS['wp_filter']['minn_probe_hook'];
$say('wp_filter entry', [get_class($hook), array_keys($hook->callbacks), array_keys($hook->callbacks[10]), $hook->callbacks[12]['strtoupper']]);
$say('WP_Hook has_filter', [$hook->has_filter(), $hook->has_filter('minn_probe_hook', 'strtoupper'), $hook->has_filter('minn_probe_hook', 'nope'), $hook->has_filters()]);
$say('WP_Hook apply_filters', $hook->apply_filters(' abc ', [' abc ']));
$say('WP_Hook current_priority idle', $hook->current_priority());
add_filter('minn_probe_hook', static function ($v) use ($hook, $say) { $say('WP_Hook current_priority running', $hook->current_priority()); return $v; }, 11);
apply_filters('minn_probe_hook', 'x');
$say('WP_Hook remove_filter', [$hook->remove_filter('minn_probe_hook', 'strtoupper', 12), $hook->remove_filter('minn_probe_hook', 'strtoupper', 12), array_keys($hook->callbacks)]);
$say('WP_Hook array access', [isset($hook[10]), isset($hook[99]), array_keys($hook[10])]);
$say('WP_Hook remove_all', [$hook->remove_all_filters(), $hook->callbacks, has_filter('minn_probe_hook')]);
$say('WP_Hook standalone', (static function () { $h = new WP_Hook(); $h->add_filter('h', 'strrev', 10, 1); $h->add_filter('h', 'strtoupper', 5, 1); return [array_keys($h->callbacks), $h->apply_filters('ab', ['ab']), $h->has_filter('h', 'strrev')]; })());
$say('WP_Hook build_preinitialized', (static function () { $built = WP_Hook::build_preinitialized_hooks(['pre' => [10 => [['function' => 'strrev', 'accepted_args' => 1]]]]); return [array_keys($built), get_class($built['pre']), $built['pre']->apply_filters('ab', ['ab'])]; })());
$say('WP_Hook do_action', (static function () { $h = new WP_Hook(); $seen = []; $h->add_filter('a', static function ($x) use (&$seen) { $seen[] = $x; }, 10, 1); $h->do_action(['v']); return $seen; })());
remove_all_filters('minn_probe_hook');

// WP_Meta_Query.
$mq = new WP_Meta_Query([['key' => 'a', 'value' => '1', 'compare' => '>=', 'type' => 'NUMERIC'], ['key' => 'b', 'compare' => 'NOT EXISTS'], 'relation' => 'OR']);
$say('WP_Meta_Query get_sql', $sql($mq->get_sql('post', $wpdb->posts, 'ID')));
$say('WP_Meta_Query state', [$mq->relation, $mq->has_or_relation(), array_keys($mq->get_clauses()), array_keys($mq->queries)]);
$mq2 = new WP_Meta_Query([['key' => 'color', 'value' => ['red', 'blue'], 'compare' => 'IN'], ['key' => 'size', 'value' => 'L'], ['key' => 'x', 'value' => 'a%', 'compare' => 'LIKE'], ['key' => 'y', 'value' => [1, 5], 'compare' => 'BETWEEN', 'type' => 'NUMERIC']]);
$say('WP_Meta_Query get_sql 2', $sql($mq2->get_sql('post', $wpdb->posts, 'ID')));
$say('WP_Meta_Query nested', $sql((new WP_Meta_Query(['relation' => 'AND', ['key' => 'a', 'value' => '1'], ['relation' => 'OR', ['key' => 'b', 'value' => '2'], ['key' => 'c', 'value' => '3']]]))->get_sql('post', $wpdb->posts, 'ID')));
$say('WP_Meta_Query legacy', $sql((static function () { $q = new WP_Meta_Query(); $q->parse_query_vars(['meta_key' => 'k', 'meta_value' => 'v', 'meta_compare' => '!=']); return $q->get_sql('post', $GLOBALS['wpdb']->posts, 'ID'); })()));
$say('WP_Meta_Query empty', [(new WP_Meta_Query([]))->get_sql('post', $wpdb->posts, 'ID'), (new WP_Meta_Query())->queries]);
$say('WP_Meta_Query shapes', $sql((new WP_Meta_Query([['key' => 'k'], ['value' => 'v'], ['key' => 'e', 'compare' => 'EXISTS'], ['key' => 'r', 'value' => 'a.b', 'compare' => 'REGEXP'], ['key' => 'nl', 'value' => 'z', 'compare' => 'NOT LIKE'], ['key' => 'nb', 'value' => [1, 2], 'compare' => 'NOT BETWEEN', 'type' => 'DECIMAL(10,2)'], ['key' => 'ni', 'value' => 'a,b', 'compare' => 'NOT IN'], ['key' => 'd', 'value' => '2024-01-01', 'compare' => '<', 'type' => 'DATE'], ['key' => 'u', 'value' => 3, 'type' => 'UNSIGNED'], ['key' => 'bin', 'value' => 'B', 'type' => 'BINARY'], ['key' => 'ne', 'value' => ['x'], 'compare' => '!=']]))->get_sql('post', $wpdb->posts, 'ID')));
$say('WP_Meta_Query same key or', $sql((new WP_Meta_Query(['relation' => 'OR', ['key' => 'k', 'value' => '1'], ['key' => 'k', 'value' => '2']]))->get_sql('post', $wpdb->posts, 'ID')));
$say('WP_Meta_Query same key and', $sql((new WP_Meta_Query(['relation' => 'AND', ['key' => 'k', 'value' => '1'], ['key' => 'k', 'value' => '2']]))->get_sql('post', $wpdb->posts, 'ID')));
$say('WP_Meta_Query named clause', (static function () use ($sql) { $q = new WP_Meta_Query(['price' => ['key' => '_price', 'value' => 5, 'type' => 'NUMERIC'], 'other' => ['key' => 'o', 'value' => 'x']]); $r = $sql($q->get_sql('post', $GLOBALS['wpdb']->posts, 'ID')); return [$r, array_keys($q->get_clauses()), $q->get_clauses()['price']['alias'], $q->get_clauses()['price']['cast']]; })());
$say('WP_Meta_Query comment', $sql((new WP_Meta_Query([['key' => 'rating', 'value' => 4, 'type' => 'NUMERIC']]))->get_sql('comment', $wpdb->comments, 'comment_ID')));
$say('WP_Meta_Query orderby', $sql((static function () { $q = new WP_Meta_Query([['key' => 'k', 'value' => 'v'], ['key' => 'k2', 'compare' => 'EXISTS']]); $r = $q->get_sql('post', $GLOBALS['wpdb']->posts, 'ID'); return [$r['where'], $q->get_clauses()['wp_postmeta'] ?? array_keys($q->get_clauses())]; })()));
$say('WP_Meta_Query term', $sql((new WP_Meta_Query([['key' => 'order', 'value' => '2', 'type' => 'NUMERIC']]))->get_sql('term', 't', 'term_id')));
$say('WP_Meta_Query user', $sql((new WP_Meta_Query([['key' => 'nickname', 'value' => 'x']]))->get_sql('user', $wpdb->users, 'ID')));

// WP_Tax_Query.
$tq = new WP_Tax_Query([['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [1]], ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['engine'], 'operator' => 'NOT IN'], 'relation' => 'AND']);
$say('WP_Tax_Query get_sql', $sql($tq->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query state', [$tq->relation, array_keys($tq->queries), $tq->queries[0]['terms'], $tq->queries[0]['field'], $tq->queries[0]['include_children']]);
$say('WP_Tax_Query and', $sql((new WP_Tax_Query([['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['engine', 'parity'], 'operator' => 'AND']]))->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query exists', $sql((new WP_Tax_Query([['taxonomy' => 'category', 'operator' => 'EXISTS'], ['taxonomy' => 'post_tag', 'operator' => 'NOT EXISTS']]))->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query missing term', $sql((new WP_Tax_Query([['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['no-such-tag-xyz']]]))->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query two in', $sql((new WP_Tax_Query([['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [1]], ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['engine']]]))->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query or', $sql((new WP_Tax_Query(['relation' => 'OR', ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [1]], ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['engine']]]))->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query ttid field', $sql((new WP_Tax_Query([['taxonomy' => 'category', 'field' => 'term_taxonomy_id', 'terms' => [1, 7]], ['taxonomy' => 'category', 'field' => 'term_id', 'terms' => [1], 'include_children' => false]]))->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query nested', $sql((new WP_Tax_Query(['relation' => 'AND', ['taxonomy' => 'category', 'terms' => [1]], ['relation' => 'OR', ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['engine']], ['taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => ['parity']]]]))->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query empty', (new WP_Tax_Query([]))->get_sql($wpdb->posts, 'ID'));
$say('WP_Tax_Query name field', $sql((new WP_Tax_Query([['taxonomy' => 'category', 'field' => 'name', 'terms' => ['Uncategorized']]]))->get_sql($wpdb->posts, 'ID')));
$say('WP_Tax_Query transform', (static function () { $q = new WP_Tax_Query([['taxonomy' => 'category', 'field' => 'slug', 'terms' => ['uncategorized']]]); $q->get_sql($GLOBALS['wpdb']->posts, 'ID'); return [$q->queries[0]['field'], $q->queries[0]['terms'], $q->queried_terms]; })());

// WP_Date_Query.
$say('WP_Date_Query get_sql', $sql((new WP_Date_Query([['after' => '2024-01-01', 'before' => ['year' => 2025, 'month' => 6], 'inclusive' => true], ['year' => 2023, 'compare' => '>='], 'relation' => 'OR'], 'post_date'))->get_sql()));
$say('WP_Date_Query parts', $sql((new WP_Date_Query([['year' => 2024, 'monthnum' => 3, 'day' => 5, 'hour' => 10, 'dayofweek' => [1, 2], 'compare' => 'IN']]))->get_sql()));
$say('WP_Date_Query between', $sql((new WP_Date_Query([['column' => 'post_modified_gmt', 'year' => [2020, 2022], 'compare' => 'BETWEEN']]))->get_sql()));
$say('WP_Date_Query state', (static function () { $q = new WP_Date_Query([['year' => 2024]]); return [$q->column, $q->compare, $q->relation, array_keys($q->queries), $q->validate_column('post_date'), $q->validate_column('nope'), $q->validate_column('comment_date')]; })());
$say('WP_Date_Query build_mysql_datetime', [(new WP_Date_Query([]))->build_mysql_datetime(['year' => 2024, 'month' => 2], false), (new WP_Date_Query([]))->build_mysql_datetime('2024-05-06 07:08:09', true), (new WP_Date_Query([]))->build_mysql_datetime(['year' => 2024], true)]);

// Block templates and hooked blocks.
$content = '<!-- wp:group --><div class="wp-block-group"><!-- wp:post-content /--><!-- wp:paragraph --><p>x</p><!-- /wp:paragraph --></div><!-- /wp:group -->';
$hooked = ['core/post-content' => ['before' => ['core/spacer'], 'after' => ['core/separator']], 'core/group' => ['first_child' => ['core/heading'], 'last_child' => ['core/buttons']]];
$blocks = parse_blocks($content);
$say('traverse_and_serialize_blocks plain', traverse_and_serialize_blocks($blocks));
$say('traverse_and_serialize_blocks hooked', traverse_and_serialize_blocks($blocks, make_before_block_visitor($hooked, get_post(1)), make_after_block_visitor($hooked, get_post(1))));
$say('traverse_and_serialize_blocks callbacks', traverse_and_serialize_blocks(parse_blocks('<!-- wp:paragraph --><p>a</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>b</p><!-- /wp:paragraph -->'), static fn (&$block, $parent, $prev) => '[b:' . ($prev['blockName'] ?? '-') . ']', static fn (&$block, $parent, $next) => '[a:' . ($next['blockName'] ?? '-') . ']'));
add_filter('hooked_block_types', static function ($types, $position, $anchor) { if ($anchor === 'core/post-content' && $position === 'after') { $types[] = 'core/spacer'; } return $types; }, 10, 3);
$say('apply_block_hooks_to_content', apply_block_hooks_to_content($content, get_post(1)));
$say('apply_block_hooks_to_content ignored', apply_block_hooks_to_content('<!-- wp:post-content {"metadata":{"ignoredHookedBlocks":["core/spacer"]}} /-->', get_post(1)));
remove_all_filters('hooked_block_types');
$say('apply_block_hooks_to_content none', apply_block_hooks_to_content($content, get_post(1)));
$registered = register_block_template('minn-probe//probe-template', ['title' => 'Probe Template', 'description' => 'A probe', 'content' => '<!-- wp:paragraph --><p>hi</p><!-- /wp:paragraph -->', 'post_types' => ['post']]);
$say('register_block_template', is_wp_error($registered) ? ['error' => $registered->get_error_code()] : [get_class($registered), get_object_vars($registered)]);
$say('register_block_template again', $err(register_block_template('minn-probe//probe-template', ['title' => 'Again'])));
$say('register_block_template bad name', [$err(register_block_template('nodelimiter', ['title' => 'x'])), $err(register_block_template('UPPER//x', ['title' => 'x']))]);
$say('get_block_template plugin', (static function () { $t = get_block_template('minn-probe//probe-template'); return $t ? [$t->id, $t->slug, $t->theme, $t->source, $t->origin, $t->content, $t->is_custom, $t->has_theme_file, $t->plugin] : null; })());
$say('get_block_templates plugin', array_map(static fn ($t) => $t->id, get_block_templates(['slug__in' => ['probe-template']])));
$say('get_block_templates post_type', in_array('minn-probe//probe-template', array_map(static fn ($t) => $t->id, get_block_templates(['post_type' => 'post'])), true));
$say('get_block_templates post_type slug', array_map(static fn ($t) => $t->id, get_block_templates(['post_type' => 'post', 'slug__in' => ['probe-template']])));
register_block_template('minn-probe//probe-open', ['title' => 'Open', 'content' => '<!-- wp:paragraph --><p>o</p><!-- /wp:paragraph -->']);
$say('get_block_templates open post_type', in_array(get_stylesheet() . '//probe-open', array_map(static fn ($t) => $t->id, get_block_templates(['post_type' => 'post'])), true));
$say('get_block_templates all has open', in_array(get_stylesheet() . '//probe-open', array_map(static fn ($t) => $t->id, get_block_templates()), true));
$say('get_block_template theme wins', (static function () { $t = get_block_template(get_stylesheet() . '//index'); return $t ? [$t->source, $t->has_theme_file, $t->is_custom, $t->slug, $t->theme, $t->status, $t->type, is_string($t->content) && $t->content !== '', $t->wp_id, $t->author, $t->area] : null; })());
$say('get_block_template part', (static function () { $t = get_block_template(get_stylesheet() . '//header', 'wp_template_part'); return $t ? [$t->source, $t->area, $t->type, $t->title] : null; })());
unregister_block_template('minn-probe//probe-open');
$say('unregister_block_template', [get_class(unregister_block_template('minn-probe//probe-template')), $err(unregister_block_template('minn-probe//probe-template')), get_block_template('minn-probe//probe-template')]);
$say('render_block_core_template_part missing', render_block_core_template_part(['slug' => 'no-such-part-xyz']));
$say('render_block_core_template_part header', md5(trim((string) preg_replace(['/\s?wp-container-core-[a-z-]+-is-layout-[0-9a-f]+/', '/\s+/'], ['', ' '], (string) render_block_core_template_part(['slug' => 'header', 'theme' => get_stylesheet()])))));
$say('render_block_core_template_part header text', preg_replace('/\s+/', ' ', strip_tags((string) render_block_core_template_part(['slug' => 'header', 'theme' => get_stylesheet()]))));

// Query vars from blocks.
$qblock = static fn (array $query, int $id = 1) => new WP_Block(parse_blocks('<!-- wp:post-template /-->')[0], ['queryId' => $id, 'query' => $query]);
$qb = $qblock(['perPage' => 3, 'pages' => 0, 'offset' => 0, 'postType' => 'post', 'order' => 'desc', 'orderBy' => 'date', 'author' => '', 'search' => '', 'exclude' => [], 'sticky' => '', 'inherit' => false]);
$say('build_query_vars_from_query_block', build_query_vars_from_query_block($qb, 1));
$say('build_query_vars_from_query_block page 2', build_query_vars_from_query_block($qb, 2));
$qb2 = $qblock(['perPage' => 5, 'offset' => 2, 'postType' => 'page', 'order' => 'asc', 'orderBy' => 'title', 'author' => '1,2', 'search' => 'hello', 'exclude' => [3], 'sticky' => 'exclude', 'inherit' => false, 'taxQuery' => ['category' => [1]], 'parents' => [2], 'format' => ['aside']]);
$say('build_query_vars_from_query_block full', build_query_vars_from_query_block($qb2, 1));
$say('build_query_vars_from_query_block inherit', build_query_vars_from_query_block($qblock(['inherit' => true, 'perPage' => 4]), 1));
$say('build_query_vars_from_query_block no context', build_query_vars_from_query_block(new WP_Block(parse_blocks('<!-- wp:post-template /-->')[0]), 1));
$say('build_query_vars_from_query_block pages', build_query_vars_from_query_block($qblock(['perPage' => 3, 'pages' => 2, 'offset' => 1, 'postType' => 'post', 'inherit' => false]), 3));
$say('build_query_vars_from_query_block pages beyond', build_query_vars_from_query_block($qblock(['perPage' => 3, 'pages' => 2, 'offset' => 1, 'postType' => 'post', 'inherit' => false]), 5));
$say('build_query_vars_from_query_block sticky only', build_query_vars_from_query_block($qblock(['sticky' => 'only', 'perPage' => 2, 'postType' => 'post', 'inherit' => false]), 1));
$say('build_comment_query_vars_from_block', build_comment_query_vars_from_block(new WP_Block(parse_blocks('<!-- wp:comment-template /-->')[0], ['postId' => 1])));
$say('build_comment_query_vars_from_block paged', build_comment_query_vars_from_block(new WP_Block(parse_blocks('<!-- wp:comment-template /-->')[0], ['postId' => 1, 'comments/inherit' => false, 'comments/perPage' => 2, 'comments/order' => 'desc', 'comments/paged' => 2])));

// WP_Comment_Query.
$cq = new WP_Comment_Query(['post_id' => 1, 'status' => 'approve']);
$say('WP_Comment_Query', [get_class($cq->comments[0]), count($cq->comments), $cq->found_comments, $cq->max_num_pages, $cq->query_vars['post_id'], $cq->query_vars['status']]);
$say('WP_Comment_Query count', (new WP_Comment_Query(['post_id' => 1, 'count' => true]))->comments);
$say('WP_Comment_Query query()', [(new WP_Comment_Query())->query(['post_id' => 1, 'fields' => 'ids']), (new WP_Comment_Query())->query(['post_id' => 1, 'count' => true])]);
$say('WP_Comment_Query ids', (new WP_Comment_Query(['post_id' => 1, 'fields' => 'ids']))->comments);
$say('WP_Comment_Query none', [(new WP_Comment_Query(['post_id' => 999999]))->comments, (new WP_Comment_Query(['post_id' => 999999]))->found_comments]);
$say('WP_Comment_Query paged', (static function () { $q = new WP_Comment_Query(['post_id' => 1, 'number' => 1, 'no_found_rows' => false]); return [$q->found_comments, $q->max_num_pages, count($q->comments)]; })());
$say('WP_Comment_Query defaults', array_keys((new WP_Comment_Query(['post_id' => 1]))->query_vars));

// Comment templating.
$GLOBALS['post'] = get_post(1);
setup_postdata($GLOBALS['post']);
$GLOBALS['comment'] = get_comment(1);
$comments = get_comments(['post_id' => 1, 'status' => 'approve', 'order' => 'ASC']);
$say('get_comment_pages_count', [get_comment_pages_count($comments, 1, false), get_comment_pages_count($comments, 10, false), get_comment_pages_count([], 1), get_comment_pages_count($comments, 1, true), get_comment_pages_count($comments, 0)]);
$say('wp_list_comments', wp_list_comments(['echo' => false], $comments));
$say('wp_list_comments args', wp_list_comments(['echo' => false, 'style' => 'ol', 'avatar_size' => 32, 'short_ping' => true, 'type' => 'comment', 'reverse_top_level' => false, 'max_depth' => 3], $comments));
$say('wp_list_comments callback', wp_list_comments(['echo' => false, 'callback' => static function ($comment, $args, $depth) { echo '<li id="c' . $comment->comment_ID . '" depth="' . $depth . '">'; }, 'end-callback' => static function () { echo '</li>'; }], $comments));
$say('wp_list_comments none', wp_list_comments(['echo' => false], []));
$say('comment_form', $out(static fn () => comment_form()));
$say('comment_form args', $out(static fn () => comment_form(['title_reply' => 'Say it', 'label_submit' => 'Go', 'comment_notes_before' => '', 'class_form' => 'probe-form', 'id_form' => 'probeform', 'fields' => ['author' => '<p class="a"><input name="author" /></p>'], 'comment_field' => '<p class="c"><textarea name="comment"></textarea></p>', 'submit_button' => '<button name="%1$s" type="submit" id="%2$s" class="%3$s">%4$s</button>', 'cancel_reply_before' => '', 'cancel_reply_after' => ''], 1)));
$say('comment_form closed', $out(static fn () => comment_form([], 2)));
$say('paginate_links', paginate_links(['base' => 'http://x/%_%', 'format' => '?paged=%#%', 'total' => 5, 'current' => 2]));
$say('paginate_links first', paginate_links(['base' => 'http://x/page/%#%/', 'format' => 'page/%#%/', 'total' => 12, 'current' => 1, 'end_size' => 1, 'mid_size' => 1]));
$say('paginate_links last', paginate_links(['base' => 'http://x/%_%', 'format' => '?paged=%#%', 'total' => 12, 'current' => 12, 'prev_text' => 'Prev', 'next_text' => 'Next', 'add_args' => ['a' => 'b'], 'add_fragment' => '#top']));
$say('paginate_links array', paginate_links(['base' => 'http://x/%_%', 'format' => '?paged=%#%', 'total' => 3, 'current' => 2, 'type' => 'array', 'show_all' => true, 'prev_next' => false]));
$say('paginate_links list', paginate_links(['base' => 'http://x/%_%', 'format' => '?paged=%#%', 'total' => 3, 'current' => 3, 'type' => 'list', 'aria_current' => 'true', 'before_page_number' => '[', 'after_page_number' => ']']));
$say('paginate_links one', paginate_links(['base' => 'http://x/%_%', 'format' => '?paged=%#%', 'total' => 1, 'current' => 1]));
$say('get_comments_pagenum_link', [get_comments_pagenum_link(2), get_comments_pagenum_link(1), get_comments_pagenum_link(3, 5)]);
$say('get_comment_reply_link', [get_comment_reply_link(['depth' => 1, 'max_depth' => 5], 1, 1), get_comment_reply_link(['depth' => 5, 'max_depth' => 5], 1, 1), get_comment_reply_link(['depth' => 1, 'max_depth' => 5, 'reply_text' => 'Answer', 'add_below' => 'x', 'respond_id' => 'r', 'before' => '<b>', 'after' => '</b>'], 1, 1), get_comment_reply_link(['depth' => 1, 'max_depth' => 5], 1, 2)]);
$say('get_cancel_comment_reply_link', [get_cancel_comment_reply_link(), get_cancel_comment_reply_link('Stop')]);
$say('get_comment_id_fields', [get_comment_id_fields(1), get_comment_id_fields(2)]);
$say('get_comments_link', [get_comments_link(1), get_comments_link(2)]);
$say('get_comment_author_link', [get_comment_author_link(1), get_comment_author_link(999999)]);
$say('get_comment_ID', $GLOBALS['comment'] ? get_comment_ID() : null);
$say('comment_text', $out(static fn () => comment_text(1)));
$say('comment_form_title', [$out(static fn () => comment_form_title()), $out(static fn () => comment_form_title('No', 'Reply to %s')), $out(static fn () => comment_form_title(false, false, true, 1))]);
$say('get_next_comments_link', [get_next_comments_link(), get_next_comments_link('More', 5)]);
$say('get_previous_comments_link', [get_previous_comments_link(), get_previous_comments_link('Back')]);
$say('paginate_comments_links', paginate_comments_links(['echo' => false]));
$GLOBALS['wp_query'] = new WP_Query(['p' => 1]);
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['wp_query']->the_post();
$say('comments_template', $out(static fn () => comments_template()));
$say('comments_template separate', $out(static fn () => comments_template('/comments.php', true)));
wp_reset_postdata();
wp_reset_query();

echo json_encode($log, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
