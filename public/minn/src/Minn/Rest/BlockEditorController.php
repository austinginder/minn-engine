<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Http\RouteMiss;
use Minn\Runtime\Handoff;

/**
 * Two of wp-block-editor/v1's routes, for someone who may edit theme
 * options (probe: the block editor routes on the reference).
 * navigation-fallback answers the navigation menu a navigation block
 * falls back to (the newest published one, made when there is none) by
 * its id, linked so it embeds. export is refused to anyone else as the
 * reference refuses it; the theme it packs (the theme's files with its
 * templates and theme.json as the site has them, as a zip) the engine
 * does not build, so for someone allowed that route declines.
 */
final readonly class BlockEditorController
{
    public function __construct(private RestUrl $url, private Caller $caller)
    {
    }

    /** The active theme packed with the site's templates and styles. */
    #[Route(Method::Get, '/wp-block-editor/v1/export', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_export_templates', signInMessage: 'Sorry, you are not allowed to export templates and template parts.', refuse: 'rest_cannot_export_templates', message: 'Sorry, you are not allowed to export templates and template parts.'))]
    public function export(Request $request): Response
    {
        throw new RouteMiss('The theme export is not built by the engine.');
    }

    /** The navigation menu a navigation block falls back to. */
    #[Route(Method::Get, '/wp-block-editor/v1/navigation-fallback', policy: new Policy(Access::Cap, 'edit_theme_options', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create Navigation Menus as this user.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to create Navigation Menus as this user.'))]
    public function navigationFallback(Request $request): Response
    {
        $menu = Handoff::navigationFallback();
        if ($menu === null) {
            throw new RouteMiss('No navigation menu to fall back to.');
        }
        $id = (int) $menu['ID'];
        $allow = $this->caller->can('edit_post', $id) ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET'];
        return Reply::answer($request, [
            'id' => $id,
            '_links' => ['self' => [Links::item('self', $this->url->to("/wp/v2/navigation/{$id}"), ['embeddable' => true, 'targetHints' => ['allow' => $allow]])]],
        ]);
    }
}
