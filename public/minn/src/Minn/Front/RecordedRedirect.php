<?php

declare(strict_types=1);

namespace Minn\Front;

use Minn\Http\Response;
use Minn\Runtime\Runtime;

/**
 * The redirect the runtime recorded for this request (wp_redirect and its
 * safe variant record it, the reference exits after sending it), as the
 * response the engine sends: the location under its status with the
 * X-Redirect-By a caller named, or a blank page when a plugin's filter
 * emptied the location.
 */
final class RecordedRedirect
{
    /** The recorded redirect as a response, or a blank page when there is no location left to send. */
    public static function response(): Response
    {
        ['location' => $location, 'status' => $status, 'by' => $by] = (array) Runtime::current()->get('redirect', []) + ['location' => '', 'status' => 302, 'by' => null];
        if ((string) $location === '') {
            return new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], '');
        }
        $response = Response::redirect((string) $location, (int) $status);
        return is_string($by) ? $response->withHeader('X-Redirect-By', $by) : $response;
    }
}
