<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;

/**
 * What the app bundle carries: the changelog, the user guide, and the
 * translation offers (none: the engine has no update channel to poll).
 */
final readonly class BundleController
{
    public function __construct(
        private App $app,
        private Caller $caller,
    ) {
    }

    /** The translation offers; none on the engine. */
    #[Route(Method::Get, '/minn-admin/v1/translations')]
    public function translations(Request $request): Response
    {
        $this->caller->requireFloor();
        return Reply::answer($request, ['count' => 0, 'groups' => []]);
    }

    /** The app's bundled changelog. */
    #[Route(Method::Get, '/minn-admin/v1/changelog')]
    public function changelog(Request $request): Response
    {
        $this->caller->requireFloor();
        return Reply::answer($request, $this->bundled('changelog.md'));
    }

    /** The app's bundled user guide. */
    #[Route(Method::Get, '/minn-admin/v1/guide')]
    public function guide(Request $request): Response
    {
        $this->caller->requireFloor();
        return Reply::answer($request, $this->bundled('docs/user-guide.md'));
    }

    /** @return array{version: string, markdown: string} */
    private function bundled(string $relative): array
    {
        $file = $this->app->file($relative);
        return ['version' => $this->app->version(), 'markdown' => $file === null ? '' : (string) file_get_contents($file)];
    }
}
