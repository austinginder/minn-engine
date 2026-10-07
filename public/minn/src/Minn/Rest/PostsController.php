<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Policy;
use Minn\Http\Args;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Auth\TypeCapabilities;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Runtime;

/** wp/v2 posts and pages, read side. */
final readonly class PostsController
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private PostObject $object,
        private Caller $caller,
    ) {
    }

    /** The posts or pages list. */
    #[Route(Method::Get, '/wp/v2/{base:posts}', policy: new Policy(Access::Public), params: PostCollectionParams::class)]
    #[Route(Method::Get, '/wp/v2/{base:pages}', policy: new Policy(Access::Public), params: PostCollectionParams::class)]
    public function list(Request $request, string $base): Response
    {
        return $this->serveList($request, $base === 'pages' ? 'page' : 'post');
    }

    /**
     * The list for any post type as the reference serves it (probe
     * rest-post-lists): the caller and the statuses judged, the request's
     * parameters sanitized, the query arguments through rest_{type}_query
     * and rest_query_var-*, a WP_Query (so pre_get_posts and every query
     * filter run), then each post the caller may read (or edit, in the edit
     * context) shaped (by $shape when the type has its own object, as
     * media does). The totals are the query's, counted again without the
     * page when a later page came back empty. A media search also matches
     * file names, as the reference's does.
     *
     * @param (\Closure(PostRecord, Context): array<string, mixed>)|null $shape
     */
    public function serveList(Request $request, string $type, ?\Closure $shape = null): Response
    {
        $context = Context::of($request);
        $this->visibleStatuses($request, $type, $context);
        $registered = PostCollectionParams::listed($type);
        $wp = RuntimeRoutes::sanitized($request, $registered);
        if ($wp['orderby'] === 'relevance' && empty($wp['search'])) {
            throw new RestError('rest_no_search_term_defined', 'You need to define a search term to order by relevance.', 400);
        }
        if ($wp['orderby'] === 'include' && empty($wp['include'])) {
            throw new RestError('rest_orderby_include_missing_include', 'You need to define an include parameter to order by include.', 400);
        }
        $args = (array) \apply_filters("rest_{$type}_query", PostListArgs::of($wp, $registered, $type), $wp);
        $vars = PostListArgs::queryVars($args, $wp);
        if ($type === 'attachment' && !empty($vars['s'])) {
            Runtime::hooks()->add('wp_allow_query_attachment_by_filename', '__return_true');
        }
        $query = new \WP_Query();
        $posts = $query->query($vars);
        [$total, $pages] = $this->totals($query, $vars);
        if ($request->method === Method::Head) {
            return Reply::list([], $total, $pages, null);
        }
        $objects = [];
        foreach ((array) $posts as $post) {
            if (!$post instanceof \WP_Post || !($context->isEdit() ? $this->caller->can('edit_post', $post->ID) : $this->readable($post))) {
                continue;
            }
            $record = PostRecord::fromRow(get_object_vars($post));
            $objects[] = match (true) {
                $shape !== null => $shape($record, $context),
                $context->isEdit() => $this->object->edit($record, $this->caller->id()),
                default => $this->object->view($record),
            };
        }
        return Reply::list($objects, $total, $pages, Fields::fromQuery($request->query));
    }

    /**
     * The total and the page count: the query's own, or, when a later page
     * came back with nothing counted, a count without the page. A page past
     * the last is refused.
     *
     * @param array<string, mixed> $vars
     * @return array{0: int, 1: int}
     */
    private function totals(\WP_Query $query, array $vars): array
    {
        $page = (int) ($vars['paged'] ?? 0);
        $total = (int) $query->found_posts;
        if ($total < 1 && $page > 1) {
            unset($vars['paged']);
            $count = new \WP_Query();
            $count->query($vars);
            $total = (int) $count->found_posts;
        }
        $perPage = (int) $query->query_vars['posts_per_page'];
        $pages = $perPage !== 0 ? (int) ceil($total / $perPage) : 0;
        if ($page > $pages && $total > 0) {
            throw new RestError('rest_post_invalid_page_number', 'The page number requested is larger than the number of pages available.', 400);
        }
        return [$total, $pages];
    }

    /** Whether the caller may read a post: published, readable to them, a public status, or an attachment of one they may. */
    private function readable(\WP_Post $post): bool
    {
        if ($post->post_status === 'publish' || $this->caller->can('read_post', $post->ID)) {
            return true;
        }
        $status = \get_post_status_object($post->post_status);
        if ($status && $status->public) {
            return true;
        }
        if ($post->post_status === 'inherit' && $post->post_parent > 0) {
            $parent = \get_post($post->post_parent);
            if ($parent instanceof \WP_Post) {
                return $this->readable($parent);
            }
        }
        return $post->post_status === 'inherit';
    }

    /**
     * The statuses this caller may list. Public callers see only 'publish';
     * any other status and the edit context need a caller who can edit this
     * type, and a status beyond publish is a parameter error without that cap.
     */
    private function visibleStatuses(Request $request, string $type, Context $context): void
    {
        // An attachment's public status is inherit (it shows as its parent does).
        $publicOnly = [$type === 'attachment' ? 'inherit' : 'publish'];
        $requested = $request->has('status')
            ? array_values(array_filter(array_map(trim(...), explode(',', (string) $request->query('status')))))
            : $publicOnly;
        $beyondPublic = array_diff($requested, $publicOnly) !== [];
        $editCap = TypeCapabilities::edit($type);
        if ($beyondPublic && !$this->caller->can($editCap)) {
            $inner = ['code' => 'rest_forbidden_status', 'message' => 'Status is forbidden.', 'data' => ['status' => $this->caller->id() > 0 ? 403 : 401]];
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => 'Status is forbidden.'], 'details' => ['status' => $inner]]);
        }
        // The enum is judged after the capability, and the reference always names status[0].
        $known = PostCollectionParams::ofType($type)['status']['items']['enum'] ?? [];
        if (array_diff($requested, $known) !== []) {
            $options = implode(', ', array_slice($known, 0, -1)) . ', and ' . end($known);
            $inner = ['code' => 'rest_not_in_enum', 'message' => "status[0] is not one of {$options}.", 'data' => null];
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => $inner['message']], 'details' => ['status' => $inner]]);
        }
        if (!$context->isEdit() && !$beyondPublic) {
            return;
        }
        $refusal = 'Sorry, you are not allowed to edit posts in this post type.';
        $this->caller->require('rest_forbidden_context', $refusal, 401);
        if (!$this->caller->can($editCap)) {
            throw new RestError('rest_forbidden_context', $refusal, 403);
        }
    }

    /** One post or page. */
    #[Route(Method::Get, '/wp/v2/{base:posts|pages}/{id:[\d]+}', policy: new Policy(Access::Public, subject: Subject::Post, param: 'id', edit: new Policy(Access::Own, 'edit_post', param: 'id', signIn: 'rest_forbidden_context', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_forbidden_context', message: 'Sorry, you are not allowed to edit this post.')), args: [Args::CONTEXT])]
    public function single(Request $request, string $base, string $id): Response
    {
        return $this->serveSingle($request, $base === 'pages' ? 'page' : 'post', $id);
    }

    /** One post of any type, with the reference's read rules. */
    public function serveSingle(Request $request, string $type, string $id): Response
    {
        $postId = (int) $id;
        $row = $this->db->row(
            "SELECT * FROM {$this->db->table('posts')} WHERE ID = ? AND post_type = ? LIMIT 1",
            [$postId, $type],
        );
        if ($row === null) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        $row = PostRecord::fromRow($row);
        $fields = Fields::fromQuery($request->query);
        if (Context::of($request)->isEdit()) {
            if (!$this->caller->can('edit_post', $postId)) {
                throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit this post.');
            }
            return self::withAlternate(Reply::item($this->object->edit($row, $this->caller->id()), $fields), $row);
        }
        // A non-published post is visible only to a reader who can edit it.
        if ($row->status !== 'publish' && !$this->caller->can('read_post', $postId)) {
            throw $this->caller->refuse('rest_forbidden', 'Sorry, you are not allowed to do that.');
        }
        return self::withAlternate(Reply::item($this->object->view($row), $fields), $row);
    }

    /** A viewable type's single post points at its page on the site. */
    public static function withAlternate(Response $response, PostRecord $post): Response
    {
        if (!Runtime::booted() || !\is_post_type_viewable($post->type)) {
            return $response;
        }
        return Reply::alternate($response, (string) \get_permalink($post->id));
    }
}
