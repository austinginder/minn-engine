<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Blocks\QueryVars;

/**
 * The query arguments the query and comments blocks run, as WordPress's
 * block functions build them from a block's context: a query block's post
 * query, and the comment query a post's comments block shows.
 */
final class BlockQueries
{
    /** The post formats a query block may ask for, by slug. */
    private const FORMATS = ['standard', 'aside', 'chat', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio'];

    /**
     * A query block's post query for a page (build_query_vars_from_query_block),
     * through query_loop_block_query_vars when the block has a query context.
     *
     * @return array<string, mixed>
     */
    public static function posts(mixed $block, mixed $page): mixed
    {
        $context = $block->context['query'] ?? null;
        $sticky = array_map('intval', (array) Runtime::options()->filtered('sticky_posts', []));
        $site = ['viewableType' => static fn (string $type) => Registered::viewableType($type), 'hierarchicalType' => static fn (string $type) => Registered::hierarchicalType($type), 'viewableTaxonomy' => static fn (string $taxonomy) => Registered::viewableTaxonomy($taxonomy), 'formats' => self::FORMATS];
        $query = QueryVars::fromContext(is_array($context) ? $context : null, (int) $page, $sticky, $site);
        return is_array($context) ? Runtime::hooks()->filter('query_loop_block_query_vars', [$query, $block, $page]) : $query;
    }

    /**
     * The comment query a comments block shows (build_comment_query_vars_from_block):
     * approved comments oldest first, the reader's own held ones too, the
     * post's, threaded and paged as the discussion settings say.
     *
     * @return array<string, mixed>
     */
    public static function comments(mixed $block): array
    {
        $vars = ['orderby' => 'comment_date_gmt', 'order' => 'ASC', 'status' => 'approve', 'no_found_rows' => false];
        if (Handoff::signedIn()) {
            $vars['include_unapproved'] = [(int) CurrentUser::id()];
        } else {
            ['comment_author_email' => $email] = (array) CommentChecks::currentCommenter() + ['comment_author_email' => null];
            $email = (string) $email ?: CommentChecks::unapprovedEmail();
            if ($email !== '') {
                $vars['include_unapproved'] = [$email];
            }
        }
        $contextPost = $block->context['postId'] ?? null;
        if (!empty($contextPost)) {
            $vars = array_replace($vars, ['post_id' => (int) $contextPost]);
        }
        ['post_id' => $postId] = $vars + ['post_id' => null];
        $options = Runtime::options();
        $vars['hierarchical'] = $options->filtered('thread_comments') ? 'threaded' : false;
        if ($options->filtered('page_comments') && (int) $options->filtered('comments_per_page') > 0) {
            $perPage = (int) $options->filtered('comments_per_page');
            $vars['number'] = $perPage;
            $page = (int) MainQuery::current()->get('cpage');
            if ($page > 0) {
                $vars['paged'] = $page;
            } elseif ($options->filtered('default_comments_page') === 'oldest') {
                $vars['paged'] = 1;
            } elseif (!empty($postId)) {
                $count = (int) CommentReads::query(['post_id' => $postId, 'status' => 'approve', 'count' => true]);
                $vars['paged'] = max(1, (int) ceil($count / $perPage));
            }
        }
        return $vars;
    }
}
