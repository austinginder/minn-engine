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

/**
 * wp-site-health/v1 (probe: the site health routes on the reference): the
 * namespace, its six asynchronous tests and the directory sizes, each for
 * someone who may view site health checks and refused to anyone else as
 * rest_forbidden. What the tests report is the reference's own Site Health
 * screen (its wording, its loopback and wordpress.org requests, the sizes
 * of a WordPress install), which the engine does not have
 * (WP_Site_Health's tests are placeholders), so for someone allowed the
 * route declines and the answer stays rest_no_route.
 */
final readonly class SiteHealthController
{
    private const REFUSAL = 'Sorry, you are not allowed to do that.';

    /** One of the asynchronous tests, or the directory sizes. */
    #[Route(Method::Get, '/wp-site-health/v1/tests/background-updates', policy: new Policy(Access::Cap, 'view_site_health_checks', signIn: 'rest_forbidden', signInMessage: self::REFUSAL))]
    #[Route(Method::Get, '/wp-site-health/v1/tests/loopback-requests', policy: new Policy(Access::Cap, 'view_site_health_checks', signIn: 'rest_forbidden', signInMessage: self::REFUSAL))]
    #[Route(Method::Get, '/wp-site-health/v1/tests/https-status', policy: new Policy(Access::Cap, 'view_site_health_checks', signIn: 'rest_forbidden', signInMessage: self::REFUSAL))]
    #[Route(Method::Get, '/wp-site-health/v1/tests/dotorg-communication', policy: new Policy(Access::Cap, 'view_site_health_checks', signIn: 'rest_forbidden', signInMessage: self::REFUSAL))]
    #[Route(Method::Get, '/wp-site-health/v1/tests/authorization-header', policy: new Policy(Access::Cap, 'view_site_health_checks', signIn: 'rest_forbidden', signInMessage: self::REFUSAL))]
    #[Route(Method::Get, '/wp-site-health/v1/directory-sizes', policy: new Policy(Access::Cap, 'view_site_health_checks', signIn: 'rest_forbidden', signInMessage: self::REFUSAL))]
    #[Route(Method::Get, '/wp-site-health/v1/tests/page-cache', policy: new Policy(Access::Cap, 'view_site_health_checks', signIn: 'rest_forbidden', signInMessage: self::REFUSAL))]
    public function test(Request $request): Response
    {
        throw new RouteMiss('Site Health is not answered by the engine.');
    }
}
