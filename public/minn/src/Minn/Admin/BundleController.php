<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Ops\Changelog;
use Minn\Rest\Caller;
use Minn\Rest\Reply;

/**
 * What the app bundle carries: the changelog, the user guide, and the
 * translation offers (none: the engine polls no translation channel);
 * and beside them the engine's own changelog, read from GitHub.
 */
final readonly class BundleController
{
    public function __construct(
        private App $app,
        private Caller $caller,
        private Changelog $engineChangelog,
    ) {
    }

    /** The translation offers; none on the engine. */
    #[Route(Method::Get, '/minn-admin/v1/translations', policy: new Policy(Access::Floor))]
    public function translations(Request $request): Response
    {
        return Reply::answer($request, ['count' => 0, 'groups' => []]);
    }

    /** The app's bundled changelog. */
    #[Route(Method::Get, '/minn-admin/v1/changelog', policy: new Policy(Access::Floor))]
    public function changelog(Request $request): Response
    {
        return Reply::answer($request, $this->bundled('changelog.md'));
    }

    /** The engine's own changelog, kept in its repository and not in a release, shown beside the app's on Minn. */
    #[Route(Method::Get, '/minn-admin/v1/engine-changelog', policy: new Policy(Access::Floor))]
    public function engineChangelog(Request $request): Response
    {
        return Reply::answer($request, ['version' => MINN_ENGINE_VERSION, 'markdown' => $this->engineChangelog->markdown()]);
    }

    /** The app's bundled user guide. */
    #[Route(Method::Get, '/minn-admin/v1/guide', policy: new Policy(Access::Floor))]
    public function guide(Request $request): Response
    {
        return Reply::answer($request, $this->bundled('docs/user-guide.md'));
    }

    /** @return array{version: string, markdown: string} */
    private function bundled(string $relative): array
    {
        $file = $this->app->file($relative);
        return ['version' => $this->app->version(), 'markdown' => $file === null ? '' : (string) file_get_contents($file)];
    }
}
