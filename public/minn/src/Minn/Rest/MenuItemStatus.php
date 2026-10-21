<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Auth\Can;
use Minn\Auth\TypeCapabilities;
use Minn\Http\Refusal;
use Minn\Http\Request;
use Minn\RestError;
use Minn\Runtime\Handoff;

/**
 * Menu items listed with a status beyond publish by a caller who may not
 * edit them: refused as the parameter it is ("Status is forbidden."),
 * before the caller is judged for the list or its edit context, as the
 * reference refuses it, signed out too (register 18300).
 */
final class MenuItemStatus implements Refusal
{
    /** Throws the status refusal, 401 inside for a signed-out caller and 403 for one signed in. */
    public static function refuse(Request $request): void
    {
        $asked = PostsController::requestedStatuses($request) ?? [];
        if (array_diff($asked, ['publish']) === [] || Can::current(TypeCapabilities::edit('nav_menu_item'))) {
            return;
        }
        $inner = ['code' => 'rest_forbidden_status', 'message' => 'Status is forbidden.', 'data' => ['status' => (int) (Handoff::currentUser()->ID ?? 0) > 0 ? 403 : 401]];
        throw new RestError('rest_invalid_param', 'Invalid parameter(s): status', 400, ['params' => ['status' => $inner['message']], 'details' => ['status' => $inner]]);
    }
}
