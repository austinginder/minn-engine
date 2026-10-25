<?php

declare(strict_types=1);

namespace Minn\Ext\TestRecords;

use Minn\Extension\Seams;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\RestError;

/** REST routes over the extension's own table: one open to anyone, two for a signed-in caller, one for a capability. */
final readonly class Notes
{
    public function __construct(private Seams $minn)
    {
    }

    /** Who the REST gate says is calling. */
    #[Route(Method::Get, '/minn-test/v1/whoami', policy: new Policy(Access::Public))]
    public function whoami(Request $request): Response
    {
        $user = $this->minn->user();
        return Response::json(['id' => $user?->id ?? 0, 'login' => $user?->login ?? '']);
    }

    /** The caller's own notes. */
    #[Route(Method::Get, '/minn-test/v1/notes', policy: new Policy(Access::SignedIn))]
    public function list(Request $request): Response
    {
        $table = $this->minn->db->table('minn_test_records_notes');
        $rows = $this->minn->db->rows("SELECT id, body FROM {$table} WHERE user_id = ? ORDER BY id", [$this->minn->reader->userId]);
        return Response::json(array_map(static fn (array $row): array => ['id' => (int) $row['id'], 'body' => (string) $row['body']], $rows));
    }

    /** A note written for the caller. */
    #[Route(Method::Post, '/minn-test/v1/notes', policy: new Policy(Access::SignedIn))]
    public function create(Request $request): Response
    {
        $body = $request->json()['body'] ?? null;
        if (!is_string($body) || $body === '') {
            throw new RestError('minn_test_body', 'A note needs a body.', 400);
        }
        $table = $this->minn->db->table('minn_test_records_notes');
        $this->minn->db->execute("INSERT INTO {$table} (user_id, body) VALUES (?, ?)", [$this->minn->reader->userId, $body]);
        return Response::json(['id' => $this->minn->db->insertId(), 'body' => $body], 201);
    }

    /** Only for someone who manages the site. */
    #[Route(Method::Get, '/minn-test/v1/admins', policy: new Policy(Access::Cap, 'manage_options'))]
    public function admins(Request $request): Response
    {
        return Response::json(['ok' => true]);
    }
}
