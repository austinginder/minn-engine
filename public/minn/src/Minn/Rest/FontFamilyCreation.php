<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Refusal;
use Minn\Http\Request;

/**
 * A font family's create judges its settings, then its theme.json version,
 * before the caller is judged (Rest\FontSettings), so a caller who may not
 * create one hears of a bad setting first, as on the reference. With
 * plugins present, the refusal reaches them where a permission refusal
 * would, after rest_request_before_callbacks.
 */
final class FontFamilyCreation implements Refusal
{
    /** Throws the settings' or the version's refusal. */
    public static function refuse(Request $request): void
    {
        FontSettings::newFamily($request);
        FontSettings::version($request);
    }
}
