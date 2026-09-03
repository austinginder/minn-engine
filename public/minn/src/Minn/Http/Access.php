<?php

declare(strict_types=1);

namespace Minn\Http;

/**
 * Who a route is for. The six answers every route gives, so the
 * authorization surface of the engine reads as a list of these.
 */
enum Access
{
    /** Anyone, signed in or not; the caller is never resolved. */
    case Public;
    /** A signed-in caller, whoever they are. */
    case SignedIn;
    /** A signed-in caller holding the named capability (and every extra one). */
    case Cap;
    /** The Minn Admin floor: signed in, may edit posts, and holds every extra capability named; refused as rest_forbidden. */
    case Floor;
    /** A signed-in caller holding a capability on the object a pattern capture names. */
    case Own;
    /** Any caller, on a route whose {base} capture must name a declared post type; the route declines otherwise and the handler judges the rest. */
    case Type;
}
