<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\RestError;

/** wp/v2 types. */
final readonly class TypesController
{
    public function __construct(private Types $types)
    {
    }

    /** Deliberately a whole-payload reply: _fields strips every type key, yielding []. */
    #[Route(Method::Get, '/wp/v2/types', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function list(Request $request): Response
    {
        return Reply::item($this->types->all(), Fields::fromQuery($request->query));
    }

    /** One post type. */
    #[Route(Method::Get, '/wp/v2/types/{slug:[\w-]+}', policy: new Policy(Access::Public), args: [Args::CONTEXT])]
    public function single(Request $request, string $slug): Response
    {
        $type = $this->types->find($slug);
        if ($type === null) {
            throw new RestError('rest_type_invalid', 'Invalid post type.', 404);
        }
        return Reply::item($type, Fields::fromQuery($request->query));
    }
}
