<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;

/** The System view: diagnostics, the scheduled-post list, autoloaded options, and the logs. */
final readonly class SystemController
{
    public function __construct(private Diagnostics $diagnostics, private Logs $logs, private Caller $caller)
    {
    }

    /** The System view's payload. */
    #[Route(Method::Get, '/minn-admin/v1/system', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function system(Request $request): Response
    {
        return Reply::answer($request, $this->diagnostics->payload($request));
    }

    /** The scheduled posts. */
    #[Route(Method::Get, '/minn-admin/v1/system/cron', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function cron(Request $request): Response
    {
        return Reply::answer($request, $this->diagnostics->cron());
    }

    /** The autoloaded options. */
    #[Route(Method::Get, '/minn-admin/v1/system/autoload', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function autoload(Request $request): Response
    {
        return Reply::answer($request, $this->diagnostics->autoload());
    }

    /** The engine never rewrites wp-config.php; the file is the site's, edited by hand. */
    #[Route(Method::Post, '/minn-admin/v1/system/config', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function config(Request $request): Response
    {
        throw new RestError('not_editable', 'Minn Engine does not rewrite wp-config.php. Change the constant in the file itself.', 400);
    }

    /** The logs list. */
    #[Route(Method::Get, '/minn-admin/v1/system/logs', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function logs(Request $request): Response
    {
        return Reply::answer($request, ['sources' => $this->logs->listPayload()]);
    }

    /** One log's tail. */
    #[Route(Method::Get, '/minn-admin/v1/system/logs/{id:[a-zA-Z0-9:_.-]+}', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function log(Request $request, string $id): Response
    {
        return Reply::answer($request, $this->logs->read($id));
    }

    /** Empties one log. */
    #[Route(Method::Delete, '/minn-admin/v1/system/logs/{id:[a-zA-Z0-9:_.-]+}', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function clearLog(Request $request, string $id): Response
    {
        $this->logs->clear($id);
        return Reply::answer($request, ['cleared' => true]);
    }

    /** The debug log's tail. */
    #[Route(Method::Get, '/minn-admin/v1/system/debug-log', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function debugLog(Request $request): Response
    {
        return Reply::answer($request, $this->logs->tail($this->logs->debugLogPath()));
    }

    /** Empties the debug log. */
    #[Route(Method::Delete, '/minn-admin/v1/system/debug-log', policy: new Policy(Access::Cap, 'manage_options', signIn: 'rest_forbidden', signInMessage: 'Sorry, you are not allowed to do that.'))]
    public function clearDebugLog(Request $request): Response
    {
        $this->logs->clear('debug');
        return Reply::answer($request, ['cleared' => true]);
    }


}
