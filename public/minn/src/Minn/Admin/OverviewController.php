<?php

declare(strict_types=1);

namespace Minn\Admin;

use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Rest\Caller;
use Minn\Rest\Reply;
use Minn\RestError;
use Minn\Support\Serialized;

/**
 * The Overview of minn-admin/v1: the payload, the drill-down behind one
 * chart bar, and the pick of cards, the person's own and the site's default.
 */
final readonly class OverviewController
{
    public function __construct(
        private Db $db,
        private Site $site,
        private Dashboard $dashboard,
        private Users $users,
        private Caller $caller,
    ) {
    }

    /** The overview payload. */
    #[Route(Method::Get, '/minn-admin/v1/overview')]
    public function overview(Request $request): Response
    {
        $userId = $this->caller->requireFloor();
        return Reply::answer($request, $this->dashboard->overview($userId, $this->days($request)));
    }

    /** The events behind one chart bar. */
    #[Route(Method::Get, '/minn-admin/v1/overview/activity')]
    public function overviewActivity(Request $request): Response
    {
        $userId = $this->caller->requireFloor();
        [$from, $to] = $this->window($request);
        return Reply::answer($request, $this->dashboard->activity($userId, $from, $to));
    }

    /** Saves the caller's pick of overview cards. */
    #[Route(Method::Post, '/minn-admin/v1/overview/metrics')]
    public function setOverviewMetrics(Request $request): Response
    {
        $userId = $this->caller->requireFloor();
        $keys = $this->metricKeysFrom($request);
        if ($keys === null || $keys === []) {
            $this->users->deleteMeta($userId, 'minn_admin_overview_metrics');
        } else {
            $this->users->setMeta($userId, 'minn_admin_overview_metrics', Serialized::encode($keys));
        }
        return Reply::answer($request, $this->storedMetricLayout($userId));
    }

    /** Saves the site's default overview cards. */
    #[Route(Method::Post, '/minn-admin/v1/overview/metric-defaults')]
    public function setOverviewMetricDefaults(Request $request): Response
    {
        $userId = $this->caller->requireFloor();
        $this->caller->requireCap('manage_options');
        $keys = $this->metricKeysFrom($request);
        if ($keys === null || $keys === []) {
            $this->site->deleteOption('minn_admin_overview_metric_defaults');
        } else {
            $this->site->setOption('minn_admin_overview_metric_defaults', Serialized::encode($keys));
        }
        return Reply::answer($request, $this->storedMetricLayout($userId));
    }

    /** The cleaned keys, null when the caller asked for a clear; a missing or non-list keys member refuses. */
    private function metricKeysFrom(Request $request): ?array
    {
        $json = $request->json();
        if (!array_key_exists('keys', $json)) {
            throw new RestError('minn_admin_bad_metrics', 'No metrics provided.', 400);
        }
        $keys = $json['keys'];
        if ($keys === null || $keys === []) {
            return null;
        }
        if (!is_array($keys)) {
            throw new RestError('minn_admin_bad_metrics', 'No metrics provided.', 400);
        }
        return Dashboard::cleanMetricKeys($keys);
    }

    /** The stored layout, unfiltered by the catalog, so a save answers without a second Overview round trip. */
    private function storedMetricLayout(int $userId): array
    {
        $site = Dashboard::cleanMetricKeys(Serialized::decode((string) ($this->db->option('minn_admin_overview_metric_defaults') ?? '')));
        $raw = $this->users->meta($userId, 'minn_admin_overview_metrics');
        $saved = $raw === null ? null : Serialized::decode($raw);
        $custom = is_array($saved);
        return [
            'keys' => $custom ? Dashboard::cleanMetricKeys($saved) : $site,
            'defaults' => $site,
            'custom' => $custom,
            'canSetMetricDefaults' => $this->caller->can('manage_options'),
        ];
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
