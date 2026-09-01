<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\UserRecord;
use Minn\Auth\ApplicationPasswords;
use Minn\Auth\Authenticator;
use Minn\Content\Site;
use Minn\Content\Users;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/**
 * wp/v2/users/{id}/application-passwords: list, create, rename, delete,
 * and introspect the password that authenticated the call. Refusals and
 * shapes follow the reference: 501 when passwords are unavailable (no
 * HTTPS), 401 anonymous, 404 for an unknown user or password, arguments
 * checked before permissions.
 */
final readonly class ApplicationPasswordsController
{
    private const NAME_SCHEMA = ['type' => 'string', 'minLength' => 1, 'pattern' => '.*\S.*'];
    private const APP_ID_SCHEMA = ['type' => 'string', 'oneOf' => [['type' => 'string', 'format' => 'uuid'], ['type' => 'string', 'enum' => ['']]]];

    public function __construct(
        private Users $users,
        private Site $site,
        private ApplicationPasswords $passwords,
        private RestUrl $url,
        private Caller $caller,
        private Schema $schema,
    ) {
    }

    /** The user's application passwords. */
    #[Route(Method::Get, '/wp/v2/users/{id:\d+|me}/application-passwords')]
    public function list(Request $request, string $id): Response
    {
        $user = $this->subject($request, $id, 'list');
        $items = array_map(fn (array $r) => $this->item($user, $r), $this->passwords->all($user->id));
        return Reply::item($items, Fields::fromQuery($request->query));
    }

    /** Mints one; the plain password is in this answer only. */
    #[Route(Method::Post, '/wp/v2/users/{id:\d+|me}/application-passwords')]
    public function create(Request $request, string $id): Response
    {
        $body = $request->json() + $request->form;
        if (!array_key_exists('name', $body)) {
            throw RestError::missingParams(['name']);
        }
        $this->validate('name', $body['name'], self::NAME_SCHEMA);
        $appId = (string) ($body['app_id'] ?? '');
        if (array_key_exists('app_id', $body)) {
            $this->validate('app_id', $body['app_id'], self::APP_ID_SCHEMA);
        }
        $user = $this->subject($request, $id, 'create');
        [$record, $plain] = $this->passwords->create($user->id, (string) $body['name'], $appId);
        $item = $this->item($user, $record);
        $item = ['uuid' => $item['uuid'], 'app_id' => $item['app_id'], 'name' => $item['name'], 'created' => $item['created'], 'last_used' => $item['last_used'], 'last_ip' => $item['last_ip'], 'password' => $plain, '_links' => $item['_links']];
        return Reply::item($item, Fields::fromQuery($request->query), 201);
    }

    /** Removes every one. */
    #[Route(Method::Delete, '/wp/v2/users/{id:\d+|me}/application-passwords')]
    public function deleteAll(Request $request, string $id): Response
    {
        $user = $this->subject($request, $id, 'delete');
        return Reply::item(['deleted' => true, 'count' => $this->passwords->deleteAll($user->id)], null);
    }

    /** The password the current Basic auth session used. */
    #[Route(Method::Get, '/wp/v2/users/{id:\d+|me}/application-passwords/introspect')]
    public function introspect(Request $request, string $id): Response
    {
        $user = $this->subject($request, $id, 'read');
        $record = $this->caller->session()?->applicationPassword;
        if ($record === null) {
            throw new RestError('rest_no_authenticated_app_password', 'Cannot introspect application password.', 404);
        }
        $fresh = $this->passwords->find($user->id, (string) $record['uuid']) ?? $record;
        return Reply::item($this->item($user, $fresh), Fields::fromQuery($request->query));
    }

    /** One password by uuid. */
    #[Route(Method::Get, '/wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}')]
    public function single(Request $request, string $id, string $uuid): Response
    {
        $user = $this->subject($request, $id, 'read');
        return Reply::item($this->item($user, $this->existing($user, $uuid)), Fields::fromQuery($request->query));
    }

    /** Renames one. */
    #[Route(Method::Post, '/wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}')]
    #[Route(Method::Put, '/wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}')]
    #[Route(Method::Patch, '/wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}')]
    public function update(Request $request, string $id, string $uuid): Response
    {
        $body = $request->json() + $request->form;
        if (array_key_exists('name', $body)) {
            $this->validate('name', $body['name'], self::NAME_SCHEMA);
        }
        $user = $this->subject($request, $id, 'edit');
        $record = $this->existing($user, $uuid);
        if (array_key_exists('name', $body)) {
            $record = $this->passwords->rename($user->id, $uuid, (string) $body['name']) ?? $record;
        }
        return Reply::item($this->item($user, $record), Fields::fromQuery($request->query));
    }

    /** Removes one. */
    #[Route(Method::Delete, '/wp/v2/users/{id:\d+|me}/application-passwords/{uuid:[0-9a-fA-F-]+}')]
    public function delete(Request $request, string $id, string $uuid): Response
    {
        $user = $this->subject($request, $id, 'delete');
        $this->existing($user, $uuid);
        $previous = $this->passwords->delete($user->id, $uuid) ?? [];
        $item = $this->item($user, $previous);
        unset($item['_links']);
        return Reply::item(['deleted' => true, 'previous' => $item], null);
    }

    /** The user the route names, after the availability, identity, and permission checks the reference makes. @return array<string, mixed> */
    private function subject(Request $request, string $id, string $action): UserRecord
    {
        if (!Authenticator::applicationPasswordsAvailable($request)) {
            throw new RestError('application_passwords_disabled', 'Application passwords are not available.', 501);
        }
        $caller = $this->caller->require();
        if ($id === 'me') {
            $user = $caller->user;
        } else {
            $user = $this->users->find((int) $id);
            if ($user === null) {
                throw new RestError('rest_user_invalid_id', 'Invalid user ID.', 404);
            }
        }
        if ($user->id !== $caller->id() && !$this->caller->can('edit_user', $user->id)) {
            $noun = $action === 'read' ? 'application_password' : 'application_passwords';
            throw new RestError("rest_cannot_{$action}_{$noun}", 'Sorry, you are not allowed to ' . $action . ' application passwords for this user.', 403);
        }
        if (!Authenticator::applicationPasswordsAvailableFor($user)) {
            throw new RestError('application_passwords_disabled_for_user', 'The provided password is an invalid application password.', 501);
        }
        return $user;
    }

    /** @param array<string, mixed> $user @return array<string, mixed> */
    private function existing(UserRecord $user, string $uuid): array
    {
        $record = $this->passwords->find($user->id, strtolower($uuid));
        if ($record === null) {
            throw new RestError('rest_application_password_not_found', 'Application password not found.', 404);
        }
        return $record;
    }

    private function validate(string $name, mixed $value, array $schema): void
    {
        $result = $this->schema->validate($value, $schema, $name);
        if ($result === true) {
            return;
        }
        throw new RestError('rest_invalid_param', "Invalid parameter(s): {$name}", 400, ['params' => [$name => $result->message], 'details' => [$name => ['code' => $result->code, 'message' => $result->message, 'data' => $result->data]]]);
    }

    /** @param array<string, mixed> $user @param array<string, mixed> $record @return array<string, mixed> */
    private function item(UserRecord $user, array $record): array
    {
        return [
            'uuid' => (string) ($record['uuid'] ?? ''),
            'app_id' => (string) ($record['app_id'] ?? ''),
            'name' => (string) ($record['name'] ?? ''),
            'created' => $this->when($record['created'] ?? null),
            'last_used' => $this->when($record['last_used'] ?? null),
            'last_ip' => isset($record['last_ip']) ? (string) $record['last_ip'] : null,
            '_links' => ['self' => [['href' => $this->url->to('/wp/v2/users/' . $user->id . '/application-passwords/' . (string) ($record['uuid'] ?? '')), 'targetHints' => ['allow' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']]]]],
        ];
    }

    /** A stored unix time as the site-local ISO stamp the reference prints, without an offset. */
    private function when(mixed $unix): ?string
    {
        if ($unix === null || $unix === '') {
            return null;
        }
        $offset = (float) ($this->site->option('gmt_offset') ?? 0);
        return gmdate('Y-m-d\TH:i:s', (int) $unix + (int) round($offset * 3600));
    }
}
