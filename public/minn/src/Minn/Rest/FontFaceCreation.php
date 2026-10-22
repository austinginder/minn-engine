<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Refusal;
use Minn\Http\Request;

/**
 * A font face's create judges its settings (its sources against the files
 * the request uploads), then its theme.json version, before the caller is
 * judged; its family is looked for only after, as on the reference.
 */
final class FontFaceCreation implements Refusal
{
    /** Throws the settings' or the version's refusal. */
    public static function refuse(Request $request): void
    {
        FontSettings::newFace($request);
        FontSettings::version($request);
    }
}
