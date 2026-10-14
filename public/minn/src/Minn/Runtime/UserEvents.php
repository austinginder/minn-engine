<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Closure;
use Minn\Http\Request;
use Minn\Objects\Errors;
use Minn\Objects\RestRequest;
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
        if ($id instanceof Errors) {
            throw new RestError($id->get_error_code(), $id->get_error_message(), 400);
        }
        if (!is_int($id)) {
            return $quietly();
        }
        Runtime::hooks()->action('rest_insert_user', [Handoff::user($id), $wpRequest, true]);
        (Handoff::newUser($id))->add_role($role);
        RestMeta::writeFrom($wpRequest, 'user', $id, 'user');
        RegisteredFields::update(Handoff::user($id), 'user', $wpRequest);
        RegisteredFields::context($wpRequest, 'user');
        Runtime::hooks()->action('rest_after_insert_user', [Handoff::user($id), $wpRequest, true]);
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
        if ($result instanceof Errors) {
            throw new RestError($result->get_error_code(), $result->get_error_message(), 400);
        }
        if ($role !== null) {
            (Handoff::newUser($id))->set_role($role);
        }
        Runtime::hooks()->action('rest_insert_user', [Handoff::user($id), $wpRequest, false]);
        RestMeta::writeFrom($wpRequest, 'user', $id, 'user');
        RegisteredFields::update(Handoff::user($id), 'user', $wpRequest);
        RegisteredFields::context($wpRequest, 'user');
        Runtime::hooks()->action('rest_after_insert_user', [Handoff::user($id), $wpRequest, false]);
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
        $user = Handoff::user($id);
        \wp_delete_user($id, $reassign);
        Runtime::hooks()->action('rest_delete_user', [$user, Handoff::newRestResponse($data, 200), RuntimeRoutes::wpRequest($request)]);
    }

    /**
     * rest_pre_insert_user over the prepared user (the request's fields), as
     * the reference runs it before the save; what the filter changes is
     * saved, an error refuses.
     *
     * @param array<string, mixed> $userdata
     * @return array<string, mixed>
     */
    private static function prepared(array $userdata, RestRequest $request): array
    {
        $filtered = Runtime::hooks()->filter('rest_pre_insert_user', [(object) $userdata, $request]);
        if ($filtered instanceof Errors) {
            $status = $filtered->get_error_data();
            throw new RestError($filtered->get_error_code(), $filtered->get_error_message(), is_array($status) ? (int) ($status['status'] ?? 400) : 400);
        }
        return is_object($filtered) ? get_object_vars($filtered) : $userdata;
    }
}
