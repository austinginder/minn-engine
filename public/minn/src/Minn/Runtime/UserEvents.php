<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Http\Request;
use Minn\RestError;
use Minn\Rest\RegisteredFields;
use Minn\Rest\RuntimeRoutes;
use Minn\Rest\RestMeta;

/**
 * What the reference's REST users controller tells plugins, for the
 * engine's own: with plugins loaded an account is written through the
 * runtime's wp_insert_user, wp_update_user and wp_delete_user
 * (wp_set_password, each profile meta, the role, clean_user_cache,
 * user_register and the count, profile_update, delete_user and
 * deleted_user), and the REST actions follow. Without a booted runtime each
 * write is the engine's own, given as a closure.
 */
final readonly class UserEvents
{
    /** Whether plugins are loaded to be told anything. */
    public function live(): bool
    {
        return Runtime::booted();
    }

    /**
     * Creates an account the way the REST controller does: wp_insert_user
     * with no role, rest_insert_user, then the role added, then
     * rest_after_insert_user. Returns the new id.
     *
     * @param array<string, mixed> $userdata wp_insert_user's fields
     * @param Closure(): int $quietly the engine's own write
     */
    public function create(array $userdata, string $role, Request $request, Closure $quietly): int
    {
        if (!$this->live()) {
            return $quietly();
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        $id = \wp_insert_user(['role' => false] + self::prepared($userdata, $wpRequest));
        if ($id instanceof \WP_Error) {
            throw new RestError($id->get_error_code(), $id->get_error_message(), 400);
        }
        if (!is_int($id)) {
            return $quietly();
        }
        \do_action('rest_insert_user', \get_userdata($id), $wpRequest, true);
        (new \WP_User($id))->add_role($role);
        RestMeta::writeFrom($wpRequest, 'user', $id, 'user');
        RegisteredFields::update(\get_userdata($id), 'user', $wpRequest);
        RegisteredFields::context($wpRequest, 'user');
        \do_action('rest_after_insert_user', \get_userdata($id), $wpRequest, true);
        return $id;
    }

    /**
     * Updates an account: wp_update_user with the changed fields, the role
     * when one is given, then the two REST actions.
     *
     * @param array<string, mixed> $userdata the fields the request changes
     * @param Closure(): void $quietly the engine's own write
     */
    public function update(int $id, array $userdata, ?string $role, Request $request, Closure $quietly): void
    {
        if (!$this->live()) {
            $quietly();
            return;
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        $result = \wp_update_user(self::prepared(['ID' => $id] + $userdata, $wpRequest));
        if ($result instanceof \WP_Error) {
            throw new RestError($result->get_error_code(), $result->get_error_message(), 400);
        }
        if ($role !== null) {
            (new \WP_User($id))->set_role($role);
        }
        \do_action('rest_insert_user', \get_userdata($id), $wpRequest, false);
        RestMeta::writeFrom($wpRequest, 'user', $id, 'user');
        RegisteredFields::update(\get_userdata($id), 'user', $wpRequest);
        RegisteredFields::context($wpRequest, 'user');
        \do_action('rest_after_insert_user', \get_userdata($id), $wpRequest, false);
    }

    /**
     * Deletes an account, its posts going to $reassign (or with it when
     * none), then rest_delete_user with the account as it was.
     *
     * @param array<string, mixed> $data the response
     * @param Closure(): void $quietly the engine's own delete
     */
    public function delete(int $id, ?int $reassign, array $data, Request $request, Closure $quietly): void
    {
        if (!$this->live()) {
            $quietly();
            return;
        }
        $user = \get_userdata($id);
        \wp_delete_user($id, $reassign);
        \do_action('rest_delete_user', $user, new \WP_REST_Response($data, 200), RuntimeRoutes::wpRequest($request));
    }

    /**
     * rest_pre_insert_user over the prepared user (the request's fields), as
     * the reference runs it before the save; what the filter changes is
     * saved, an error refuses.
     *
     * @param array<string, mixed> $userdata
     * @return array<string, mixed>
     */
    private static function prepared(array $userdata, \WP_REST_Request $request): array
    {
        $filtered = \apply_filters('rest_pre_insert_user', (object) $userdata, $request);
        if ($filtered instanceof \WP_Error) {
            $status = $filtered->get_error_data();
            throw new RestError($filtered->get_error_code(), $filtered->get_error_message(), is_array($status) ? (int) ($status['status'] ?? 400) : 400);
        }
        return is_object($filtered) ? get_object_vars($filtered) : $userdata;
    }
}
