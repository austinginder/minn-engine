<?php

declare(strict_types=1);

namespace Minn\Admin;

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
    #[Route(Method::Get, '/minn-admin/v1/notifications')]
    public function notifications(Request $request): Response
    {
        return Reply::answer($request, $this->notifications->items($this->caller->requireFloor()));
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

    /** The core status. */
    #[Route(Method::Get, '/minn-admin/v1/core', policy: new Policy(Access::Floor))]
    public function core(Request $request): Response
    {
        $this->caller->requireCap('update_core');
        return Reply::answer($request, $this->core->data());
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
        if ($this->caller->can('update_core')) {
            $out['core'] = $this->core->data();
        }
        $out['types'] = $this->types->section($userId);
        if ($this->types->commentsEnabled()) {
            $out['pendingComments'] = (int) $this->db->value(
                "SELECT COUNT(*) FROM {$this->db->table('comments')} WHERE comment_approved = '0' AND comment_type IN ( '', 'comment' )",
            );
        }
        return Reply::answer($request, $out);
    }
}
