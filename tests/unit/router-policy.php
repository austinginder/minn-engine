<?php

declare(strict_types=1);

use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Http\RouteMiss;
use Minn\Http\Router;

/**
 * The router and the policy on the attribute: the gate judges a policy
 * before the handler runs, a public route never reaches the gate, and a
 * handler that declines leaves the request to the next route.
 */
final class RouterPolicyProbe
{
    /** @var list<string> */
    public array $calls = [];

    #[Route(Method::Get, '/open')]
    public function open(Request $request): Response
    {
        $this->calls[] = 'open';
        return Response::html('open');
    }

    #[Route(Method::Get, '/public', policy: new Policy(Access::Public))]
    public function public(Request $request): Response
    {
        $this->calls[] = 'public';
        return Response::html('public');
    }

    #[Route(Method::Get, '/gated/{id:\d+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', refuse: 'rest_cannot_edit', message: 'no'))]
    public function gated(Request $request, string $id): Response
    {
        $this->calls[] = 'gated ' . $id;
        return Response::html('gated');
    }

    #[Route(Method::Get, '/{name}', policy: new Policy(Access::SignedIn))]
    public function declines(Request $request, string $name): Response
    {
        $this->calls[] = 'declined ' . $name;
        throw new RouteMiss();
    }

    #[Route(Method::Get, '/{name}')]
    public function fallback(Request $request, string $name): Response
    {
        $this->calls[] = 'fallback ' . $name;
        return Response::html('fallback');
    }
}

$request = static fn (string $path): Request => new Request(Method::Get, $path, [], [], [], '', false, 'unit.test', []);
$build = static function (array &$judged, bool $refuse = false): array {
    $probe = new RouterPolicyProbe();
    $router = new Router(static function (Policy $policy, Request $request, array $captures) use (&$judged, $refuse): void {
        $judged[] = [$policy->describe(), $captures];
        if ($refuse) {
            throw new \Minn\RestError($policy->refuse, $policy->message, 403);
        }
    });
    $router->register($probe);
    return [$router, $probe];
};

return [
    'a route with no policy and a public policy never reach the gate' => static function () use ($build, $request): bool|string {
        $judged = [];
        [$router, $probe] = $build($judged);
        $router->dispatch($request('/open'));
        $router->dispatch($request('/public'));
        return $judged === [] && $probe->calls === ['open', 'public'] ? true : json_encode([$judged, $probe->calls]);
    },
    'the gate sees the policy and the captures before the handler runs' => static function () use ($build, $request): bool|string {
        $judged = [];
        [$router, $probe] = $build($judged);
        $router->dispatch($request('/gated/7'));
        return $judged === [['cap edit_post on {id}', ['id' => '7']]] && $probe->calls === ['gated 7'] ? true : json_encode([$judged, $probe->calls]);
    },
    'a refusing gate keeps the handler from running' => static function () use ($build, $request): bool|string {
        $judged = [];
        [$router, $probe] = $build($judged, true);
        try {
            $router->dispatch($request('/gated/7'));
            return 'no refusal';
        } catch (\Minn\RestError $e) {
            return $e->getMessage() === 'no' && $probe->calls === [] ? true : json_encode([$e->getMessage(), $probe->calls]);
        }
    },
    'a handler that declines leaves the request to the next route' => static function () use ($build, $request): bool|string {
        $judged = [];
        [$router, $probe] = $build($judged);
        $response = $router->dispatch($request('/x'));
        return $response?->body === 'fallback' && $probe->calls === ['declined x', 'fallback x'] ? true : json_encode([$response?->body, $probe->calls]);
    },
    'the table lists every route with its policy' => static function () use ($build): bool|string {
        $judged = [];
        [$router] = $build($judged);
        $rows = array_map(static fn (array $r): string => $r['pattern'] . ' ' . ($r['policy']?->describe() ?? '-'), $router->table());
        return $rows === ['/open -', '/public public', '/gated/{id:\d+} cap edit_post on {id}', '/{name} signed in', '/{name} -'] ? true : json_encode($rows);
    },
    'a policy names its capabilities and its context' => static function (): bool|string {
        $floor = new Policy(Access::Floor, caps: ['install_plugins']);
        $cap = new Policy(Access::Cap, 'upload_files', edit: new Policy(Access::Cap, 'edit_posts'));
        return $floor->capabilities() === ['edit_posts', 'install_plugins']
            && $floor->describe() === 'floor edit_posts + install_plugins'
            && $cap->describe() === 'cap upload_files; edit context: cap edit_posts'
            && !$cap->isPublic()
            && (new Policy())->isPublic()
            && !(new Policy(Access::Public, edit: new Policy(Access::Cap, 'edit_posts')))->isPublic()
            ? true : json_encode([$floor->describe(), $cap->describe()]);
    },
];
