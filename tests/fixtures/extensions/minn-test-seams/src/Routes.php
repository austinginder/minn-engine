<?php

declare(strict_types=1);

namespace Minn\Ext\TestSeams;

use Minn\Extension\Seams;
use Minn\Html;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;

/** Pages the route seam answers, so the suite can see one served and one the engine keeps. */
final class Routes
{
    public function __construct(private readonly Seams $minn)
    {
    }

    /** A page of the extension's own, with a capture and the reader. */
    #[Route(Method::Get, '/seam-probe/{name:[a-z-]+}/', policy: new Policy(Access::Public))]
    public function probe(Request $request, string $name): Response
    {
        return Response::html($this->minn->frontBar('<!doctype html><html><head><title>seam route</title></head><body><p class="seam-route" data-name="' . Html::attr($name) . '">reader-' . $this->minn->reader->userId . '</p></body></html>'));
    }

    /** A path the engine already answers: the engine's route must win. */
    #[Route(Method::Get, '/minn-admin/login', policy: new Policy(Access::Public))]
    public function shadow(Request $request): Response
    {
        return Response::html('<p class="seam-shadow">taken over</p>');
    }
}
