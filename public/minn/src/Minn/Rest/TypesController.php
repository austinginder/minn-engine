<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\RestError;

/**
 * wp/v2 types. In the edit context (probe rest-types-edit) a type adds its
 * capabilities, visibility, viewability, labels and supports, for a caller
 * who may edit its posts: the list leaves out the types the caller may
 * not, and refuses a caller who may edit none; one type refuses outright.
 */
final readonly class TypesController
{
    private const EDIT_REFUSAL = 'Sorry, you are not allowed to edit posts in this post type.';

    public function __construct(private Types $types)
    {
    }

    /** Deliberately a whole-payload reply: _fields strips every type key, yielding []. */
    #[Route(Method::Get, '/wp/v2/types', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function list(Request $request): Response
    {
        $types = $this->types->all();
        if (Context::of($request)->isEdit()) {
            $types = array_filter(array_map(fn (array $type): ?array => self::mayEdit((string) $type['slug']) ? self::withEditFields((string) $type['slug'], $type) : null, $types));
            if ($types === []) {
                throw new RestError('rest_cannot_view', self::EDIT_REFUSAL, \is_user_logged_in() ? 403 : 401);
            }
        }
        return Reply::item(Context::of($request) === Context::Embed ? array_map(self::embedded(...), $types) : $types, Fields::fromQuery($request->query));
    }

    /** One post type. */
    #[Route(Method::Get, '/wp/v2/types/{type:[\w-]+}', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function single(Request $request, string $type): Response
    {
        $found = $this->types->find($type);
        if ($found === null) {
            throw new RestError('rest_type_invalid', 'Invalid post type.', 404);
        }
        if (Context::of($request)->isEdit()) {
            if (!self::mayEdit($type)) {
                throw new RestError('rest_forbidden_context', self::EDIT_REFUSAL, \is_user_logged_in() ? 403 : 401);
            }
            $found = self::withEditFields($type, $found);
        }
        return Reply::item(Context::of($request) === Context::Embed ? self::embedded($found) : $found, Fields::fromQuery($request->query));
    }

    /** A type as an embed shows it: its name, slug, icon, REST base and namespace, template. @param array<string, mixed> $type @return array<string, mixed> */
    private static function embedded(array $type): array
    {
        return array_intersect_key($type, array_flip(['name', 'slug', 'icon', 'rest_base', 'rest_namespace', 'template', 'template_lock', '_links']));
    }

    private static function mayEdit(string $type): bool
    {
        $object = \get_post_type_object($type);
        return $object !== null && \current_user_can($object->cap->edit_posts);
    }

    /** @param array<string, mixed> $type @return array<string, mixed> */
    private static function withEditFields(string $slug, array $type): array
    {
        $object = \get_post_type_object($slug);
        return [
            'capabilities' => (array) $object->cap,
            'description' => $type['description'],
            'hierarchical' => $type['hierarchical'],
            'has_archive' => $type['has_archive'],
            'visibility' => ['show_in_nav_menus' => (bool) $object->show_in_nav_menus, 'show_ui' => (bool) $object->show_ui],
            'viewable' => \is_post_type_viewable($object),
            'labels' => (array) $object->labels,
            'name' => $type['name'],
            'slug' => $type['slug'],
            'icon' => $type['icon'],
            'supports' => \get_all_post_type_supports($slug),
        ] + $type;
    }
}
