<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/** wp/v2 taxonomies: the registry, whole or per type, in view or edit context. */
final readonly class TaxonomiesController
{
    public function __construct(private Taxonomies $taxonomies, private Caller $caller)
    {
    }

    /** A whole-payload reply like types: _fields filters the map, not its members. */
    #[Route(Method::Get, '/wp/v2/taxonomies')]
    public function list(Request $request): Response
    {
        $edit = $this->context($request);
        $type = $request->query('type');
        $out = [];
        foreach ($type === null ? $this->taxonomies->all() : $this->taxonomies->forType($type) as $slug => $taxonomy) {
            if ($edit && !$this->caller->can($taxonomy['capabilities']['manage_terms'])) {
                continue;
            }
            $out[$slug] = $edit ? $taxonomy : Taxonomies::view($taxonomy);
        }
        return Reply::item($out === [] ? new \stdClass() : $out, Fields::fromQuery($request->query));
    }

    /** One taxonomy. */
    #[Route(Method::Get, '/wp/v2/taxonomies/{slug:[\w-]+}')]
    public function single(Request $request, string $slug): Response
    {
        $taxonomy = $this->taxonomies->find($slug);
        if ($taxonomy === null) {
            throw new RestError('rest_taxonomy_invalid', 'Invalid taxonomy.', 404);
        }
        $edit = $this->context($request);
        if ($edit && !$this->caller->can($taxonomy['capabilities']['manage_terms'])) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to manage terms in this taxonomy.');
        }
        return Reply::item($edit ? $taxonomy : Taxonomies::view($taxonomy), Fields::fromQuery($request->query));
    }

    /** Edit context needs a signed-in caller; the per-taxonomy capability is checked per item. */
    private function context(Request $request): bool
    {
        if ($request->query('context') !== 'edit') {
            return false;
        }
        $this->caller->require('rest_forbidden_context', 'Sorry, you are not allowed to edit this resource.', 401);
        return true;
    }
}
