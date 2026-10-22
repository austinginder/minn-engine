<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Refusal;
use Minn\Http\Request;

/**
 * A font family's update judges its settings, then its theme.json version,
 * before the family is looked for and the caller judged (the policy's
 * subject), as on the reference: a bad setting is answered even for a
 * family that does not exist.
 */
final class FontFamilyChange implements Refusal
{
    /** Throws the settings' or the version's refusal. */
    public static function refuse(Request $request): void
    {
        FontSettings::familyChanges($request);
        FontSettings::version($request);
    }
}
