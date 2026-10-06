<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\PostEvents;
use Minn\Http\Policy;
use Minn\Http\Args;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Media\Upload;
use Minn\Media\Writer;
use Minn\RestError;
use Minn\Support\Kses;

/**
 * wp/v2/media: list, single, upload on both transports (multipart field
 * "file", or a raw body with Content-Disposition), field edits, and force
 * delete with the files.
 */
final readonly class MediaController
{
    public function __construct(
        private Db $db,
        private Posts $posts,
        private Writer $library,
        private MediaObject $object,
        private Caller $caller,
    ) {
    }

    /** The media library list. */
    #[Route(Method::Get, '/wp/v2/media', policy: new Policy(Access::Public), args: [Args::CONTEXT, Args::MEDIA])]
    public function list(Request $request): Response
    {
        $context = Context::of($request);
        $edit = $context->isEdit();
        if ($edit && !$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit posts in this post type.');
        }
        $query = ListQuery::fromRequest($request);
        [$narrowing, $params] = $query->clauses();
        [$library, $libraryParams] = self::libraryClauses($request);
        $where = "post_type = 'attachment' AND post_status = 'inherit'" . $narrowing . $library;
        $params = [...$params, ...$libraryParams];
        $table = $this->db->table('posts');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
        $rows = $this->db->rows(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY post_date {$query->order}, ID {$query->order} LIMIT ? OFFSET ?",
            [...$params, $query->perPage, $query->offset()],
        );
        return Reply::list(
            array_map(fn (PostRecord $p) => $this->object->build($p, $context), PostRecord::fromRows($rows)),
            $total,
            $query->totalPages($total),
            Fields::fromQuery($request->query),
        );
    }

    /**
     * The library's own narrowing, captured from the oracle: media_type as a
     * mime prefix from a fixed set, mime_type exact, after/before exclusive
     * on site-local post_date.
     *
     * @return array{string, list<mixed>}
     */
    private static function libraryClauses(Request $request): array
    {
        $where = '';
        $params = [];
        $mediaType = (string) $request->query('media_type', '');
        if ($mediaType !== '') {
            $where .= ' AND post_mime_type LIKE ?';
            $params[] = $mediaType . '/%';
        }
        $mime = (string) $request->query('mime_type', '');
        if ($mime !== '') {
            $where .= ' AND post_mime_type = ?';
            $params[] = $mime;
        }
        foreach (['after' => '>', 'before' => '<'] as $param => $operator) {
            $value = (string) $request->query($param, '');
            if ($value !== '') {
                $where .= " AND post_date {$operator} ?";
                $params[] = self::restDate($value, $param);
            }
        }
        return [$where, $params];
    }

    private static function restDate(string $value, string $param): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:[T ]\d{2}:\d{2}(?::\d{2})?)?$/', $value) !== 1) {
            throw new RestError('rest_invalid_param', "Invalid parameter(s): {$param}", 400, [
                'params' => [$param => 'Invalid date.'],
                'details' => [$param => ['code' => 'rest_invalid_date', 'message' => 'Invalid date.', 'data' => null]],
            ]);
        }
        return str_replace('T', ' ', $value);
    }

    /** One attachment. */
    #[Route(Method::Get, '/wp/v2/media/{id:[\d]+}', policy: new Policy(Access::Public, subject: Subject::Attachment, param: 'id', edit: new Policy(Access::Own, 'edit_post', param: 'id', signIn: 'rest_forbidden_context', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_forbidden_context', message: 'Sorry, you are not allowed to edit this post.')), args: [Args::CONTEXT])]
    public function single(Request $request, string $id): Response
    {
        $attachment = $this->attachment((int) $id);
        $context = Context::of($request);
        $edit = $context->isEdit();
        if ($edit && !$this->caller->can('edit_post', (int) $id)) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit this post.');
        }
        return Reply::item($this->object->build($attachment, $context), Fields::fromQuery($request->query));
    }

    /** Uploads a file and creates its attachment. */
    #[Route(Method::Post, '/wp/v2/media', policy: new Policy(Access::Cap, 'upload_files', signIn: 'rest_cannot_create', signInMessage: 'Sorry, you are not allowed to create posts as this user.', refuse: 'rest_cannot_create', message: 'Sorry, you are not allowed to upload media on this site.'))]
    public function create(Request $request): Response
    {
        $userId = $this->caller->require('rest_cannot_create', 'Sorry, you are not allowed to create posts as this user.')->id();
        if (!$this->caller->can('upload_files')) {
            throw new RestError('rest_cannot_create', 'Sorry, you are not allowed to upload media on this site.', 403);
        }
        $parent = Upload::parentOf($request);
        if ($parent > 0 && !$this->caller->can('edit_post', $parent)) {
            throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to upload media to this post.', 403);
        }
        $upload = Upload::fromRequest($request);
        if ($upload === null) {
            throw new RestError('rest_upload_no_data', 'No data supplied.', 400);
        }
        // The refusal code names the transport: a multipart field is an "unknown
        // error", a raw body a "sideload error"; the message and status are one.
        $refused = static fn (): RestError => new RestError($upload->movedFrom === null ? 'rest_upload_sideload_error' : 'rest_upload_unknown_error', 'Sorry, you are not allowed to upload this file type.', 500);
        if ($upload->mime() === null) {
            throw $refused();
        }
        if ($upload->isImage()) {
            // The bytes decide: non-image bytes under an image name are refused, and a
            // GIF named .png is stored as .gif, both as the reference answers.
            $sniffed = $upload->sniffedMime();
            if ($sniffed === null) {
                throw $refused();
            }
            if ($sniffed !== $upload->mime()) {
                $upload = $upload->renamedFor($sniffed);
            }
        }
        $events = new PostEvents();
        $prepared = $this->library->prepare($upload, $userId);
        $events->beforeSave($prepared->columns, null);
        $id = $this->library->insert($prepared);
        $events->attachedFile($this->library, $id, $prepared->relative);
        $events->attachmentAdded($id);
        $events->restInserted($id, $request, null);
        $events->restAfterInsert($id, $request, null);
        $events->afterInsert($id, null);
        // The reference cuts the sizes after the insert and stores the metadata last; the engine cut them first.
        if ($prepared->metadata !== null) {
            $events->attachmentMetadata($this->library, $id, $prepared->metadata);
        }
        return Reply::item($this->object->build($this->posts->find($id), Context::Edit), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->object->url()->to('/wp/v2/media/' . $id));
    }

    /** The editable fields the app uses. */
    #[Route(Method::Post, '/wp/v2/media/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Attachment, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    #[Route(Method::Put, '/wp/v2/media/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Attachment, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    #[Route(Method::Patch, '/wp/v2/media/{id:[\d]+}', policy: new Policy(Access::Own, 'edit_post', param: 'id', subject: Subject::Attachment, signIn: 'rest_cannot_edit', signInMessage: 'Sorry, you are not allowed to edit this post.', refuse: 'rest_cannot_edit', message: 'Sorry, you are not allowed to edit this post.'))]
    public function update(Request $request, string $id): Response
    {
        $attachmentId = (int) $id;
        $before = $this->attachment($attachmentId);
        if (!$this->caller->can('edit_post', $attachmentId)) {
            throw $this->caller->refuse('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.');
        }
        $body = $request->json();
        $columns = [];
        foreach (['title' => 'post_title', 'caption' => 'post_excerpt', 'description' => 'post_content'] as $field => $column) {
            if (isset($body[$field])) {
                $markup = PostsWriteController::field($body[$field]);
                $columns[$column] = $this->caller->can('unfiltered_html') ? $markup : Kses::post($markup);
            }
        }
        if (array_key_exists('post', $body)) {
            $parent = (int) $body['post'];
            if ($parent > 0 && !$this->caller->can('edit_post', $parent)) {
                throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403);
            }
            $columns['post_parent'] = $parent;
        }
        $events = new PostEvents();
        if (!$events->live()) {
            if (isset($body['alt_text'])) {
                $this->library->setAlt($attachmentId, (string) $body['alt_text']);
            }
            $this->library->edit($attachmentId, $columns);
            return Reply::answer($request, $this->object->build($this->posts->find($attachmentId), Context::Edit));
        }
        // With plugins loaded, the reference's update: the row (always stamped), the
        // attachment actions, then the alt text between the two REST actions.
        $events->beforeSave($columns, $before);
        $this->library->stamp($attachmentId, $columns);
        $events->attachmentEdited($attachmentId, $before);
        $events->restInserted($attachmentId, $request, $before);
        if (isset($body['alt_text'])) {
            $events->altText($this->library, $attachmentId, (string) $body['alt_text']);
        }
        $events->restAfterInsert($attachmentId, $request, $before);
        $events->afterInsert($attachmentId, $before);
        return Reply::answer($request, $this->object->build($this->posts->find($attachmentId), Context::Edit));
    }

    /** Attachments cannot be trashed; force removes the row, its meta, and its files. */
    #[Route(Method::Delete, '/wp/v2/media/{id:[\d]+}', policy: new Policy(Access::Own, 'delete_post', param: 'id', subject: Subject::Attachment, signIn: 'rest_cannot_delete', signInMessage: 'Sorry, you are not allowed to delete this post.', refuse: 'rest_cannot_delete', message: 'Sorry, you are not allowed to delete this post.'))]
    public function delete(Request $request, string $id): Response
    {
        $attachmentId = (int) $id;
        $attachment = $this->attachment($attachmentId);
        if (!$this->caller->can('delete_post', $attachmentId)) {
            throw $this->caller->refuse('rest_cannot_delete', 'Sorry, you are not allowed to delete this post.');
        }
        if (!$request->flag('force')) {
            throw new RestError('rest_trash_not_supported', "The post does not support trashing. Set 'force=true' to delete.", 501);
        }
        $data = ['deleted' => true, 'previous' => $this->object->build($attachment, Context::Edit)];
        $events = new PostEvents();
        $events->live() ? \wp_delete_attachment($attachmentId, true) : $this->library->remove($attachment);
        $events->restDeleted($attachment, $data, $request);
        return Reply::answer($request, $data);
    }

    private function attachment(int $id): PostRecord
    {
        $post = $this->posts->find($id);
        if ($post === null || !$post->isAttachment()) {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        return $post;
    }
}
