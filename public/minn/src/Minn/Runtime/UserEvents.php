<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Http\Request;
use Minn\Rest\RuntimeRoutes;

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
        $id = \wp_insert_user(['role' => false] + $userdata);
        if (!is_int($id)) {
            return $quietly();
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        \do_action('rest_insert_user', \get_userdata($id), $wpRequest, true);
        (new \WP_User($id))->add_role($role);
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
        \wp_update_user(['ID' => $id] + $userdata);
        if ($role !== null) {
            (new \WP_User($id))->set_role($role);
        }
        $wpRequest = RuntimeRoutes::wpRequest($request);
        \do_action('rest_insert_user', \get_userdata($id), $wpRequest, false);
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
}
