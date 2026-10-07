<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/**
 * wp/v2/{rest_base} for the taxonomies plugin code registers to show in
 * REST (probe rest-plugin-types), answered as the core taxonomies are.
 * Registered after the declared post types; a base no such taxonomy names
 * declines, so a plugin's own route under wp/v2 is not shadowed.
 */
final readonly class DeclaredTermsController
{
    public function __construct(private TermsController $terms)
    {
    }

    /** A registered taxonomy's terms. */
    #[Route(Method::Get, '/wp/v2/{base:[a-z0-9_-]+}', policy: new Policy(Access::Taxonomy, param: 'base'), params: TermCollectionParams::class)]
    public function list(Request $request, string $base): Response
    {
        return $this->terms->list($request, $base);
    }

    /** One term of a registered taxonomy. */
    #[Route(Method::Get, '/wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+}', policy: new Policy(Access::Taxonomy, param: 'base'))]
    public function single(Request $request, string $base, string $id): Response
    {
        return $this->terms->single($request, $base, $id);
    }

    /** Creates a term in a registered taxonomy. */
    #[Route(Method::Post, '/wp/v2/{base:[a-z0-9_-]+}', policy: new Policy(Access::Taxonomy, param: 'base', verb: 'create'))]
    public function create(Request $request, string $base): Response
    {
        return $this->terms->create($request, $base);
    }

    /** Updates a term of a registered taxonomy. */
    #[Route(Method::Post, '/wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+}', policy: new Policy(Access::Taxonomy, param: 'base', verb: 'edit'))]
    #[Route(Method::Put, '/wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+}', policy: new Policy(Access::Taxonomy, param: 'base', verb: 'edit'))]
    #[Route(Method::Patch, '/wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+}', policy: new Policy(Access::Taxonomy, param: 'base', verb: 'edit'))]
    public function update(Request $request, string $base, string $id): Response
    {
        return $this->terms->update($request, $base, $id);
    }

    /** Deletes a term of a registered taxonomy. */
    #[Route(Method::Delete, '/wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+}', policy: new Policy(Access::Taxonomy, param: 'base', verb: 'delete'))]
    public function delete(Request $request, string $base, string $id): Response
    {
        return $this->terms->delete($request, $base, $id);
    }
}
