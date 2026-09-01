<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Request;

/**
 * The view a REST caller asked for. View is the public shape, edit adds the
 * raw halves and the caller's own action links, embed is the reduced shape
 * a linked resource carries when it rides inside another response.
 */
enum Context: string
{
    case View = 'view';
    case Edit = 'edit';
    case Embed = 'embed';

    /** The context a request names, view when it names none or names one the route does not serve. */
    public static function of(Request $request): self
    {
        return self::tryFrom((string) ($request->query('context') ?? '')) ?? self::View;
    }

    public function isEdit(): bool
    {
        return $this === self::Edit;
    }
}
