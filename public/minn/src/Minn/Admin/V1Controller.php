<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Db;
use Minn\Front\Permalinks;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Fields;
use Minn\Rest\Reply;
use Minn\RestError;

/**
 * The minn-admin/v1 namespace: the dashboard burst, the editor helpers,
 * and the small Settings-view routes. Every route sits behind the same
 * capability floor (edit_posts) the plugin declares.
 */
final readonly class V1Controller
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Posts $posts,
        private PostWriter $writer,
        private Permalinks $permalinks,
        private Dashboard $dashboard,
        private Notifications $notifications,
        private CoreStatus $core,
        private AdminTypes $types,
        private Caller $caller,
    ) {
    }

    #[Route(Method::Get, '/minn-admin/v1/overview')]
    public function overview(Request $request): Response
    {
        $userId = $this->requireFloor();
        return $this->reply($request, $this->dashboard->overview($userId, $this->days($request)));
    }

    #[Route(Method::Get, '/minn-admin/v1/overview/activity')]
    public function overviewActivity(Request $request): Response
    {
        $userId = $this->requireFloor();
        [$from, $to] = $this->window($request);
        return $this->reply($request, $this->dashboard->activity($userId, $from, $to));
    }

    #[Route(Method::Get, '/minn-admin/v1/notifications')]
    public function notifications(Request $request): Response
    {
        return $this->reply($request, $this->notifications->items($this->requireFloor()));
    }

    /** Body {id} marks one read; {} marks all read. */
    #[Route(Method::Post, '/minn-admin/v1/notifications/read')]
    public function notificationsRead(Request $request): Response
    {
        $userId = $this->requireFloor();
        $id = trim(preg_replace('/[\r\n\t ]+/', ' ', strip_tags((string) ($request->json()['id'] ?? ''))));
        $this->notifications->markRead($userId, $id);
        return $this->reply($request, ['ok' => true]);
    }

    #[Route(Method::Get, '/minn-admin/v1/core')]
    public function core(Request $request): Response
    {
        $this->requireFloor();
        $this->requireCap('update_core');
        return $this->reply($request, $this->core->data());
    }

    /**
     * The app's one-round-trip boot burst. Absent sections are the
     * contract's own fallback: the client loads a missing section
     * standalone. The engine serves what it can honestly answer and omits
     * the plugin-inventory sections it has no installation for.
     */
    #[Route(Method::Get, '/minn-admin/v1/boot-status')]
    public function bootStatus(Request $request): Response
    {
        $userId = $this->requireFloor();
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
        return $this->reply($request, $out);
    }

    #[Route(Method::Post, '/minn-admin/v1/posts/{id:\d+}/lock')]
    public function lock(Request $request, string $id): Response
    {
        $userId = $this->requireFloor();
        if (!$this->caller->can('edit_post', (int) $id)) {
            throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403);
        }
        $this->writer->setMeta((int) $id, '_edit_lock', time() . ':' . $userId);
        return $this->reply($request, ['acquired' => true]);
    }

    #[Route(Method::Post, '/minn-admin/v1/posts/{id:\d+}/unlock')]
    public function unlock(Request $request, string $id): Response
    {
        $this->requireFloor();
        if (!$this->caller->can('edit_post', (int) $id)) {
            throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403);
        }
        $this->writer->deleteMeta((int) $id, '_edit_lock');
        return $this->reply($request, ['unlocked' => true]);
    }

    /** No theme, no page templates: an honest empty set. */
    #[Route(Method::Get, '/minn-admin/v1/templates')]
    public function templates(Request $request): Response
    {
        $this->requireFloor();
        return $this->reply($request, ['templates' => []]);
    }

    /** The reference serves core's block CSS from wp-includes; the engine has none to serve. */
    #[Route(Method::Get, '/minn-admin/v1/editor-styles')]
    public function editorStyles(Request $request): Response
    {
        $this->requireFloor();
        return $this->reply($request, ['urls' => []]);
    }

    /** Theme patterns are GPL theme content the engine does not carry. */
    #[Route(Method::Get, '/minn-admin/v1/patterns')]
    public function patterns(Request $request): Response
    {
        $this->requireFloor();
        return $this->reply($request, ['patterns' => []]);
    }

    #[Route(Method::Get, '/minn-admin/v1/site-logo')]
    public function siteLogo(Request $request): Response
    {
        $this->requireFloor();
        return $this->reply($request, ['supported' => false, 'id' => 0, 'url' => '']);
    }

    #[Route(Method::Get, '/minn-admin/v1/permalinks')]
    public function permalinks(Request $request): Response
    {
        $this->requireFloor();
        $this->requireCap('manage_options');
        $structure = $this->permalinks->structure;
        return $this->reply($request, [
            'structure' => $structure,
            'category_base' => (string) ($this->site->option('category_base') ?? ''),
            'tag_base' => (string) ($this->site->option('tag_base') ?? ''),
            'pretty' => $structure !== '',
            'app_url' => $this->permalinks->url($structure !== '' ? '/minn-admin/' : '/?minn_admin=1'),
        ]);
    }

    #[Route(Method::Get, '/minn-admin/v1/spam')]
    public function spam(Request $request): Response
    {
        $this->requireFloor();
        $this->requireCap('moderate_comments');
        $counts = ['spam' => 0, 'pending' => 0];
        $rows = $this->db->rows(
            "SELECT comment_approved, COUNT(*) AS c FROM {$this->db->table('comments')}
             WHERE comment_approved IN ('spam', '0') GROUP BY comment_approved",
        );
        foreach ($rows as $row) {
            $counts[$row['comment_approved'] === 'spam' ? 'spam' : 'pending'] = (int) $row['c'];
        }
        return $this->reply($request, [
            'providers' => [],
            'queue' => $counts,
            'disallowed_keys' => (string) ($this->site->option('disallowed_keys') ?? ''),
        ]);
    }

    /** The available list is captured registry data; installed reflects this site. */
    #[Route(Method::Get, '/minn-admin/v1/languages')]
    public function languages(Request $request): Response
    {
        $this->requireFloor();
        $this->requireCap('manage_options');
        $data = (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/languages.json'), true);
        $data['current'] = (string) ($this->site->option('WPLANG') ?? '');
        return $this->reply($request, $data);
    }

    #[Route(Method::Get, '/minn-admin/v1/media/months')]
    public function mediaMonths(Request $request): Response
    {
        $this->requireFloor();
        $rows = $this->db->rows(
            "SELECT DATE_FORMAT(post_date, '%Y-%m') AS ym, COUNT(*) AS c FROM {$this->db->table('posts')}
             WHERE post_type = 'attachment' AND post_status = 'inherit' GROUP BY ym ORDER BY ym DESC",
        );
        return $this->reply($request, array_map(static fn (array $row) => ['value' => $row['ym'], 'count' => (int) $row['c']], $rows));
    }

    private function reply(Request $request, mixed $data): Response
    {
        return Reply::item($data, Fields::fromQuery($request->query));
    }

    /** Auth plus the edit_posts floor every dashboard route shares. */
    private function requireFloor(): int
    {
        $userId = $this->caller->require('rest_forbidden', 'Sorry, you are not allowed to do that.')->id();
        $this->requireCap('edit_posts');
        return $userId;
    }

    private function requireCap(string $capability): void
    {
        if (!$this->caller->can($capability)) {
            throw new RestError('rest_forbidden', 'Sorry, you are not allowed to do that.', 403);
        }
    }

    /** ?days must be an in-range integer; the reference's parameter errors otherwise. */
    private function days(Request $request): int
    {
        $raw = $request->query('days');
        if ($raw === null || $raw === '') {
            return 30;
        }
        if (!is_numeric($raw) || (float) $raw !== (float) (int) $raw) {
            throw self::parameterError('days', 'rest_invalid_type', 'days is not of type integer.', ['param' => 'days']);
        }
        $days = (int) $raw;
        if ($days < 7 || $days > 90) {
            throw self::parameterError('days', 'rest_out_of_bounds', 'days must be between 7 (inclusive) and 90 (inclusive)', null);
        }
        return $days;
    }

    /** Both bounds required, "Y-m-d H:i:s". @return array{0: string, 1: string} */
    private function window(Request $request): array
    {
        $missing = array_values(array_filter(['from', 'to'], static fn (string $name) => !$request->has($name)));
        if ($missing !== []) {
            throw RestError::missingParams($missing);
        }
        $pattern = '^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$';
        $values = [];
        $bad = [];
        foreach (['from', 'to'] as $name) {
            $value = (string) $request->query($name);
            if (!preg_match('/' . $pattern . '/', $value)) {
                $bad[$name] = $name . ' does not match pattern ' . $pattern . '.';
            }
            $values[] = $value;
        }
        if ($bad !== []) {
            $details = [];
            foreach ($bad as $name => $message) {
                $details[$name] = ['code' => 'rest_invalid_pattern', 'message' => $message, 'data' => null];
            }
            throw new RestError('rest_invalid_param', 'Invalid parameter(s): ' . implode(', ', array_keys($bad)), 400, ['params' => $bad, 'details' => $details]);
        }
        return $values;
    }

    private static function parameterError(string $param, string $code, string $message, mixed $data): RestError
    {
        return new RestError('rest_invalid_param', 'Invalid parameter(s): ' . $param, 400, [
            'params' => [$param => $message],
            'details' => [$param => ['code' => $code, 'message' => $message, 'data' => $data]],
        ]);
    }
}
