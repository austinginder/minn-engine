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
 * What the app bundle answers for: the user guide it carries, the
 * translation offers (none: the engine polls no translation channel), and
 * the two changelogs, Minn Admin's and the engine's, read from GitHub
 * because a release carries neither.
 */
final readonly class BundleController
{
    public function __construct(
        private App $app,
        private Caller $caller,
        private Changelog $adminChangelog,
        private Changelog $engineChangelog,
    ) {
    }

    /** The translation offers; none on the engine. */
    #[Route(Method::Get, '/minn-admin/v1/translations', policy: new Policy(Access::Floor))]
    public function translations(Request $request): Response
    {
        return Reply::answer($request, ['count' => 0, 'groups' => []]);
    }

    /** Minn Admin's changelog, released sections only, beside the bundle's version. */
    #[Route(Method::Get, '/minn-admin/v1/changelog', policy: new Policy(Access::Floor))]
    public function changelog(Request $request): Response
    {
        return Reply::answer($request, ['version' => $this->app->version(), 'markdown' => $this->adminChangelog->markdown()]);
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
