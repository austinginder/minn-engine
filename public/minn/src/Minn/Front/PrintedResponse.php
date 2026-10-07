<?php

declare(strict_types=1);

namespace Minn\Front;

use Closure;
use Minn\Http\Response;
use Minn\Theme\MainQueryBridge;
use Minn\Theme\Printed;

/**
 * A response WordPress's handlers print themselves (a sitemap, robots.txt),
 * as the reference serves them: the main query stood with the request's own
 * variables and the front-end steps around it, then $print; what was
 * printed, under the status and headers the handlers sent. A handler that
 * ends the request early (Printed, where the reference exits) ends it here
 * too; when none did and there is nothing to print, null, and the theme
 * renders the page on the query as it stands.
 */
final class PrintedResponse
{
    /**
     * The request served this way, or null for the theme to render.
     *
     * @param array<string, mixed> $vars the request's own query variables
     */
    public static function stand(MainQueryBridge $bridge, array $vars, ?Closure $print = null, ?Resolution $resolution = null): ?Response
    {
        $level = ob_get_level();
        ob_start();
        try {
            $bridge->stand($resolution ?? Resolution::home(), $vars);
            if ($print === null) {
                self::discard($level);
                return null;
            }
            $print();
        } catch (Printed) {
            // The handler's output is the response.
        } catch (\Throwable $failure) {
            self::discard($level);
            throw $failure;
        }
        $body = '';
        while (ob_get_level() > $level) {
            $body = (string) ob_get_clean() . $body;
        }
        $code = http_response_code();
        return new Response(is_int($code) && $code > 0 ? $code : 200, [], $body);
    }

    private static function discard(int $level): void
    {
        while (ob_get_level() > $level) {
            ob_end_clean();
        }
    }
}
