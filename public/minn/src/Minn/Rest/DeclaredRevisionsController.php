<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Args;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\RevisionArgs;
use Minn\Http\Route;
use Minn\Http\RouteMiss;

/**
 * wp/v2 revisions and autosaves under a post type plugin code or an
 * extension registered, at its rest_base (RevisableTypes says which take
 * which). The {base} declines any other base, so the next route may take
 * it; the answers, and the parent's checks, are RevisionsController's.
 */
final readonly class DeclaredRevisionsController
{
    public function __construct(private RevisionsController $revisions)
    {
    }

    /** Every revision of the parent. */
    #[Route(Method::Get, '/wp/v2/{base:[a-z0-9_-]+}/{parent:[\d]+}/revisions', policy: new Policy(Access::Type, param: 'base'), args: [RevisionArgs::PARENT, Args::CONTEXT, RevisionArgs::COLLECTION])]
    public function revisions(Request $request, string $base, string $parent): Response
    {
        return $this->revisions->revisions($request, self::revisable($base), $parent);
    }

    /** One revision of the parent. */
    #[Route(Method::Get, '/wp/v2/{base:[a-z0-9_-]+}/{parent:[\d]+}/revisions/{id:[\d]+}', policy: new Policy(Access::Type, param: 'base'), args: [RevisionArgs::PARENT, RevisionArgs::ID, Args::CONTEXT])]
    public function revision(Request $request, string $base, string $parent, string $id): Response
    {
        return $this->revisions->revision($request, self::revisable($base), $parent, $id);
    }

    /** Deletes one revision of the parent. */
    #[Route(Method::Delete, '/wp/v2/{base:[a-z0-9_-]+}/{parent:[\d]+}/revisions/{id:[\d]+}', policy: new Policy(Access::Type, param: 'base'), args: [RevisionArgs::PARENT, RevisionArgs::ID, RevisionArgs::FORCE])]
    public function deleteRevision(Request $request, string $base, string $parent, string $id): Response
    {
        return $this->revisions->deleteRevision($request, self::revisable($base), $parent, $id);
    }

    /** The parent's autosaves. */
    #[Route(Method::Get, '/wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+}/autosaves', policy: new Policy(Access::Type, param: 'base'), args: [RevisionArgs::AUTOSAVE_PARENT, Args::CONTEXT])]
    public function autosaves(Request $request, string $base, string $id): Response
    {
        return $this->revisions->autosaves($request, self::autosaving($base), $id);
    }

    /** The parent's newest autosave. */
    #[Route(Method::Get, '/wp/v2/{base:[a-z0-9_-]+}/{parent:[\d]+}/autosaves/{id:[\d]+}', policy: new Policy(Access::Type, param: 'base'), args: [RevisionArgs::AUTOSAVE_PARENT, RevisionArgs::AUTOSAVE_ID, Args::CONTEXT])]
    public function autosave(Request $request, string $base, string $parent, string $id): Response
    {
        return $this->revisions->autosave($request, self::autosaving($base), $parent, $id);
    }

    /** Saves the caller's autosave of the parent. */
    #[Route(Method::Post, '/wp/v2/{base:[a-z0-9_-]+}/{id:[\d]+}/autosaves', policy: new Policy(Access::Type, param: 'base'))]
    public function createAutosave(Request $request, string $base, string $id): Response
    {
        return $this->revisions->createAutosave($request, self::autosaving($base), $id);
    }

    private static function revisable(string $base): string
    {
        return RevisableTypes::revisable($base) ? $base : throw new RouteMiss();
    }

    private static function autosaving(string $base): string
    {
        return RevisableTypes::autosaving($base) ? $base : throw new RouteMiss();
    }
}
