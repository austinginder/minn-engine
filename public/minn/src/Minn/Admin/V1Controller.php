<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Ops\CoreStatus;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Access;
use Minn\Http\Policy;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;

/**
 * The boot burst of minn-admin/v1: the bell feed and its read marker, the
 * core status, and the one-round-trip boot-status the app starts from.
 * Every route sits behind the same capability floor (edit_posts) the
 * plugin declares.
 */
final readonly class V1Controller
{
    public function __construct(
        private Db $db,
        private Notifications $notifications,
        private CoreStatus $core,
        private AdminTypes $types,
        private Caller $caller,
    ) {
    }

    /** The bell feed. */
    #[Route(Method::Get, '/minn-admin/v1/notifications', policy: new Policy(Access::Floor))]
    public function notifications(Request $request): Response
    {
        return Reply::answer($request, $this->notifications->items($this->caller->id()));
    }

    /** Body {id} marks one read; {} marks all read. */
    #[Route(Method::Post, '/minn-admin/v1/notifications/read', policy: new Policy(Access::Floor))]
    public function notificationsRead(Request $request): Response
    {
        $userId = $this->caller->requireFloor();
        $id = trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) ($request->json()['id'] ?? ''))));
        $this->notifications->markRead($userId, $id);
        return Reply::answer($request, ['ok' => true]);
    }

    /** Minn's version and any newer release; a day-old check of the update service runs once the answer is sent. */
    #[Route(Method::Get, '/minn-admin/v1/core', policy: new Policy(Access::Floor))]
    public function core(Request $request): Response
    {
        $this->caller->requireCap('update_core');
        return $this->checkingReleases(Reply::answer($request, $this->core->data()));
    }

    /** Replaces the running engine with the release on offer. */
    #[Route(Method::Post, '/minn-admin/v1/core/update', policy: new Policy(Access::Floor))]
    public function coreUpdate(Request $request): Response
    {
        $this->caller->requireCap('update_core');
        return Reply::answer($request, ['version' => $this->core->update()]);
    }

    /**
     * The app's one-round-trip boot burst. Absent sections are the
     * contract's own fallback: the client loads a missing section
     * standalone. The engine serves what it can honestly answer and omits
     * the plugin-inventory sections it has no installation for.
     */
    #[Route(Method::Get, '/minn-admin/v1/boot-status', policy: new Policy(Access::Floor))]
    public function bootStatus(Request $request): Response
    {
        $userId = $this->caller->requireFloor();
        $out = ['notifications' => $this->notifications->items($userId)];
        $checks = $this->caller->can('update_core');
        if ($checks) {
            $out['core'] = $this->core->data();
        }
        $out['types'] = $this->types->section($userId);
        if ($this->types->commentsEnabled()) {
            $out['pendingComments'] = (int) $this->db->value(
                "SELECT COUNT(*) FROM {$this->db->table('comments')} WHERE comment_approved = '0' AND comment_type IN ( '', 'comment' )",
            );
        }
        $response = Reply::answer($request, $out);
        return $checks ? $this->checkingReleases($response) : $response;
    }

    /** The answer, with the update service asked about Minn's releases after it is sent when the last check is a day old. */
    private function checkingReleases(Response $response): Response
    {
        if (!$this->core->due()) {
            return $response;
        }
        $core = $this->core;
        return $response->afterSend(static function () use ($core): void {
            $core->refresh();
        });
    }
}
