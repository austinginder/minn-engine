<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Menus;
use Minn\Content\PostRecord;
use Minn\Content\TermRecord;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Objects\Post;
use Minn\Objects\Term;
use Minn\RestError;
use Minn\Runtime\Records;
use Minn\Runtime\Registered;
use Minn\Runtime\Runtime;
use Minn\Runtime\TermLookup;

/**
 * The menus and menu items lists, served as the reference serves them: the
 * menus as the nav_menu taxonomy's term list (its parameters through
 * rest_nav_menu_collection_params, its query through rest_nav_menu_query,
 * counted without the page), the items as the nav_menu_item type's post
 * list with its own parameters (Rest\MenuItemCollectionParams), each in
 * its own shape.
 */
final readonly class MenuLists
{
    private const TAXONOMY = 'nav_menu';
    private const ITEM_TYPE = 'nav_menu_item';

    public function __construct(
        private PostsController $posts,
        public readonly Menus $menus,
        public readonly MenuObject $menuObject,
        public readonly MenuItemObject $itemObject,
        public readonly Caller $caller,
    ) {
    }

    /**
     * The menus: edit context for one who may edit menus, a post's menus
     * only for a menu item the caller may read (an unknown post is a bad
     * parameter), then get_terms, or the post's own terms, as asked.
     */
    public function menus(Request $request): Response
    {
        $params = MenuCollectionParams::for([]);
        $wp = RuntimeRoutes::sanitized($request, $params);
        $object = Registered::taxonomy(self::TAXONOMY);
        if (Context::of($request)->isEdit() && !$this->caller->can((string) ($object->cap->edit_terms ?? 'edit_theme_options'))) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit terms in this taxonomy.');
        }
        // A post of 0 names none, as the reference reads it.
        if (!empty($wp['post'])) {
            $target = Records::post((int) $wp['post']);
            if ($target === null) {
                throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 400);
            }
            if (!$this->termsReadable($target, array_map('strval', (array) ($object->object_type ?? [self::ITEM_TYPE])))) {
                throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to view terms for this post.');
            }
        }
        $registered = (array) Runtime::hooks()->filter('rest_' . self::TAXONOMY . '_collection_params', [$params, $object]);
        $args = (array) Runtime::hooks()->filter('rest_' . self::TAXONOMY . '_query', [TermListArgs::of($wp, $registered, self::TAXONOMY, $request->method->value), $wp]);
        $found = empty($args['post']) ? TermLookup::terms($args) : TermLookup::objectTerms($args['post'], self::TAXONOMY, $args);
        $count = empty($args['post']) ? $args : ['object_ids' => $args['post']] + $args;
        unset($count['number'], $count['offset']);
        $total = (int) TermLookup::count($count);
        $perPage = (int) ($args['number'] ?? 0);
        $pages = $perPage > 0 ? (int) ceil($total / $perPage) : 0;
        if ($request->method === Method::Head) {
            return Reply::list([], $total, $pages, null);
        }
        $objects = [];
        foreach (is_array($found) ? $found : [] as $term) {
            if ($term instanceof Term) {
                $objects[] = $this->menuObject->view(TermRecord::fromRow(get_object_vars($term)));
            }
        }
        return Reply::list($objects, $total, $pages, Fields::fromQuery($request->query));
    }

    /** The menu items, as the post list of their type: every list parameter honoured, each item in its own shape. */
    public function items(Request $request): Response
    {
        $shape = function (PostRecord $record, Context $context): ?array {
            $item = $this->menus->findItem($record->id);
            return $item === null ? null : $this->itemObject->view($item, $context);
        };
        return $this->posts->serveList($request, self::ITEM_TYPE, $shape, registered: MenuItemCollectionParams::listed());
    }

    /**
     * Whether the caller may see a post's menus: the post one of the
     * taxonomy's types, and publicly viewable or one the caller may read.
     *
     * @param list<string> $objectTypes the taxonomy's post types
     */
    private function termsReadable(Post $post, array $objectTypes): bool
    {
        if (!in_array($post->post_type, $objectTypes, true)) {
            return false;
        }
        $status = Runtime::registry()->status((string) $post->post_status);
        $type = Runtime::registry()->postType((string) $post->post_type);
        $viewable = !empty($type['publicly_queryable']) || (!empty($type['_builtin']) && !empty($type['public']));
        return (!empty($status['public']) && $viewable) || $this->caller->can('read_post', (int) $post->ID);
    }
}
