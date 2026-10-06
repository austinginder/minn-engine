<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;
use Minn\Runtime\Runtime;

/**
 * wp/v2/block-types as the reference answers it (probe rest-block-types):
 * every registered block type, core's and a plugin's, in registration
 * order, or a namespace's share, for anyone who can edit a type shown in
 * REST. Each carries its registration (attributes, supports, selectors,
 * contexts, asset handles and the first of each as the old single field)
 * with its styles (its own, then those registered for it) and its
 * variations as built when asked; a dynamic block links to its renderer.
 * Served with plugins loaded, since a plugin's blocks come from its code.
 */
final readonly class BlockTypesController
{
    private const NAMESPACE = ['namespace' => ['type' => 'string', 'description' => 'Block namespace.', 'required' => false]];

    public function __construct(private RestUrl $url, private Caller $caller)
    {
    }

    /** Every block type, or those of one namespace (?namespace=). */
    #[Route(Method::Get, '/wp/v2/block-types', policy: new Policy(Access::Public), args: [Args::CONTEXT, self::NAMESPACE])]
    public function list(Request $request): Response
    {
        return $this->items($request, (string) ($request->query['namespace'] ?? ''));
    }

    /** One namespace's block types. */
    #[Route(Method::Get, '/wp/v2/block-types/{namespace:[a-zA-Z0-9_-]+}', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function inNamespace(Request $request, string $namespace): Response
    {
        return $this->items($request, $namespace);
    }

    /** One block type. */
    #[Route(Method::Get, '/wp/v2/block-types/{namespace:[a-zA-Z0-9_-]+}/{name:[a-zA-Z0-9_-]+}', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function single(Request $request, string $namespace, string $name): Response
    {
        $this->requireViewer();
        $type = \WP_Block_Type_Registry::get_instance()->get_registered("{$namespace}/{$name}");
        if (!$type instanceof \WP_Block_Type) {
            throw new RestError('rest_block_type_invalid', 'Invalid block type.', 404);
        }
        return Reply::item($this->item($type), Fields::fromQuery($request->query));
    }

    private function items(Request $request, string $namespace): Response
    {
        $this->requireViewer();
        $items = [];
        foreach (\WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $type) {
            if ($namespace === '' || str_starts_with((string) $name, $namespace . '/')) {
                $items[] = $this->item($type);
            }
        }
        $fields = Fields::fromQuery($request->query);
        return Reply::item($fields === null ? $items : array_map(static fn (array $item) => $fields->apply($item), $items), null);
    }

    /** @return array<string, mixed> */
    private function item(\WP_Block_Type $type): array
    {
        $first = static fn (array $handles) => $handles[0] ?? null;
        $item = [
            'attributes' => $type->get_attributes(),
            'is_dynamic' => $type->is_dynamic(),
            'api_version' => $type->api_version,
            'name' => $type->name,
            'title' => $type->title,
            'description' => $type->description,
            'icon' => $type->icon,
            'category' => $type->category,
            'keywords' => $type->keywords,
            'parent' => $type->parent,
            'ancestor' => $type->ancestor,
            'allowed_blocks' => $type->allowed_blocks,
            'provides_context' => (array) $type->provides_context,
            'uses_context' => $type->get_uses_context(),
            'selectors' => $type->selectors,
            'supports' => $type->supports,
            'styles' => array_merge((array) $type->styles, array_values(\WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block($type->name))),
            'textdomain' => $type->textdomain,
            'example' => $type->example,
        ];
        foreach (['editor_script', 'script', 'view_script', 'view_script_module_ids', 'editor_style', 'style', 'view_style'] as $kind) {
            $key = $kind === 'view_script_module_ids' ? $kind : $kind . '_handles';
            $item[$key] = (array) $type->{$key};
        }
        // The variations' descriptions are strings in the schema: a true one reads "1".
        $item['variations'] = array_map(static function ($variation) {
            if (is_array($variation) && is_bool($variation['description'] ?? null)) {
                $variation['description'] = (string) $variation['description'];
            }
            return $variation;
        }, (array) $type->get_variations());
        $item['block_hooks'] = $type->block_hooks;
        foreach (['editor_script', 'script', 'view_script', 'editor_style', 'style'] as $kind) {
            $item[$kind] = $first((array) $type->{$kind . '_handles'});
        }
        $item['_links'] = $this->links($type);
        return RuntimePrepare::item('rest_prepare_block_type', $item, static fn () => $type);
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function links(\WP_Block_Type $type): array
    {
        $links = [
            'collection' => [['href' => $this->url->to('/wp/v2/block-types')]],
            'self' => [['href' => $this->url->to('/wp/v2/block-types/' . $type->name), 'targetHints' => ['allow' => ['GET']]]],
            'up' => [['href' => $this->url->to('/wp/v2/block-types/' . strtok($type->name, '/'))]],
        ];
        if ($type->is_dynamic()) {
            $links['wp:render-block'] = [['href' => $this->url->to('/wp/v2/block-renderer/' . $type->name, ['context' => 'edit'])]];
            $links['curies'] = RestUrl::curies();
        }
        return $links;
    }

    /** Only someone who edits a type shown in REST may read block types; the runtime holds them. */
    private function requireViewer(): void
    {
        if (!Runtime::booted()) {
            throw RestError::noRoute();
        }
        if (!$this->caller->editsAnyRestType()) {
            throw $this->caller->refuse('rest_block_type_cannot_view', 'Sorry, you are not allowed to manage block types.');
        }
    }
}
