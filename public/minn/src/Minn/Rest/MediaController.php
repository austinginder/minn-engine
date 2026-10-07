<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;
use Minn\Runtime\PostEvents;
use Minn\Http\Policy;
use Minn\Http\Args;
use Minn\Http\Subject;
use Minn\Http\Access;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
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
        private Posts $posts,
        private Writer $library,
        private MediaObject $object,
        private Caller $caller,
        private PostsController $reads,
    ) {
    }

    /** The media list: the post lists' own path for attachments (probe rest-media-lists), each item in the media shape. */
    #[Route(Method::Get, '/wp/v2/{base:media}', policy: new Policy(Access::Public), params: PostCollectionParams::class)]
    public function list(Request $request, string $base): Response
    {
        return $this->reads->serveList($request, 'attachment', fn (PostRecord $p, Context $c) => $this->object->build($p, $c));
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
        return PostsController::withAlternate(Reply::item($this->object->build($attachment, $context), Fields::fromQuery($request->query)), $attachment);
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
        $refused = static fn (string $message = 'Sorry, you are not allowed to upload this file type.'): RestError => new RestError($upload->movedFrom === null ? 'rest_upload_sideload_error' : 'rest_upload_unknown_error', $message, 500);
        if (Runtime::booted()) {
            [$relative, $type] = $this->storeWithPlugins($upload, $request, $refused);
            return $this->insertedWithPlugins($relative, $type, $upload, $request);
        }
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
        return $this->inserted($this->library->prepare($upload, $userId), $request);
    }

    /**
     * With plugins loaded, the file goes through the runtime's
     * wp_handle_sideload (a raw body) or wp_handle_upload (a form field),
     * as the reference's REST upload sends it: their prefilters, the allowed
     * types (upload_mimes), the content check, the name, the move and
     * wp_handle_upload, where SVG and security plugins work. A refusal is the
     * engine's REST error with the runtime's words.
     *
     * @param \Closure(string): RestError $refused
     * @return array{0: string, 1: string} the path relative to the uploads folder, and the type
     */
    private function storeWithPlugins(Upload $upload, Request $request, \Closure $refused): array
    {
        $tmp = $upload->movedFrom;
        if ($tmp === null) {
            $tmp = (string) tempnam(sys_get_temp_dir(), 'minn-upload');
            file_put_contents($tmp, (string) $upload->raw);
        }
        $file = ['name' => $upload->filename, 'type' => (string) ($request->header('content-type') ?? ''), 'tmp_name' => $tmp, 'error' => 0, 'size' => (int) filesize($tmp)];
        $result = $upload->movedFrom === null ? \wp_handle_sideload($file, ['test_form' => false]) : \wp_handle_upload($file, ['test_form' => false]);
        if (is_file($tmp) && $upload->movedFrom === null) {
            @unlink($tmp);
        }
        $relative = isset($result['file']) ? $this->library->relativeOf((string) $result['file']) : null;
        if (!empty($result['error']) || $relative === null) {
            throw $refused((string) ($result['error'] ?? 'Sorry, you are not allowed to upload this file type.'));
        }
        return [$relative, (string) ($result['type'] ?? '')];
    }

    /**
     * With plugins loaded, the attachment saved as the reference's controller
     * saves it (probe rest-media-save): the photo's own title and caption
     * (wp_read_image_metadata) unless the request names them, the prepared
     * attachment through rest_pre_insert_attachment, the file's name as the
     * title when none is left, then wp_insert_attachment, the REST actions
     * (the alt text between them), wp_after_insert_post, and the sizes.
     */
    private function insertedWithPlugins(string $relative, string $type, Upload $upload, Request $request): Response
    {
        $params = self::params($request);
        $file = $this->library->pathOf($relative);
        $attachment = new \stdClass();
        if (isset($params['title'])) {
            $attachment->post_title = PostsWriteController::field($params['title']);
        }
        $meta = \wp_read_image_metadata($file);
        if (is_array($meta) && !isset($params['title']) && trim((string) $meta['title']) !== '' && !is_numeric(\sanitize_title((string) $meta['title']))) {
            $attachment->post_title = (string) $meta['title'];
        }
        if (is_array($meta) && !isset($params['caption']) && trim((string) $meta['caption']) !== '') {
            $attachment->post_excerpt = (string) $meta['caption'];
        }
        $attachment->post_type = 'attachment';
        $attachment->page_template = null;
        $args = $this->preparedAttachment($attachment, $params, $request) + ['post_mime_type' => $type, 'guid' => $this->library->urlOf($relative), 'post_parent' => $upload->parent];
        // No title left after the filter: the file's name, without its extension.
        $args = array_filter($args, static fn ($value, $key) => $key !== 'post_title' || trim((string) $value) !== '', ARRAY_FILTER_USE_BOTH) + ['post_title' => (string) preg_replace('/\.[^.]+$/', '', basename($relative))];
        $id = \wp_insert_attachment(\wp_slash($args), $file, 0, true, false);
        if ($id instanceof \WP_Error) {
            throw new RestError($id->get_error_code(), $id->get_error_message(), 500);
        }
        (new PostEvents())->restInserted((int) $id, $request, null);
        $this->finishedWithPlugins((int) $id, $params, $request, null);
        (new PostEvents())->attachmentGenerated((int) $id, $file, $type);
        return Reply::item($this->object->build($this->posts->find((int) $id), Context::Edit), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->object->url()->to('/wp/v2/media/' . $id));
    }

    /**
     * rest_pre_insert_attachment over the prepared attachment, then the
     * caption and description the request sends.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function preparedAttachment(\stdClass $attachment, array $params, Request $request): array
    {
        $attachment = \apply_filters('rest_pre_insert_attachment', $attachment, RuntimeRoutes::wpRequest($request));
        if ($attachment instanceof \WP_Error) {
            $data = $attachment->get_error_data();
            throw new RestError($attachment->get_error_code(), $attachment->get_error_message(), is_array($data) ? (int) ($data['status'] ?? 500) : 500);
        }
        $args = (array) $attachment;
        foreach (['caption' => 'post_excerpt', 'description' => 'post_content'] as $field => $column) {
            if (isset($params[$field])) {
                $args[$column] = PostsWriteController::field($params[$field]);
            }
        }
        return $args;
    }

    /**
     * After rest_insert_attachment: the alt text, rest_after_insert_attachment
     * and wp_after_insert_post, in the reference's order.
     *
     * @param array<string, mixed> $params
     */
    private function finishedWithPlugins(int $id, array $params, Request $request, ?PostRecord $before): void
    {
        $events = new PostEvents();
        if (isset($params['alt_text'])) {
            \update_post_meta($id, '_wp_attachment_image_alt', \wp_slash(\sanitize_text_field((string) $params['alt_text'])));
        }
        $events->restAfterInsert($id, $request, $before);
        $events->afterInsert($id, $before);
    }

    /**
     * The fields a media request sends, wherever it sends them: the query, a
     * form, or a JSON body.
     *
     * @return array<string, mixed>
     */
    private static function params(Request $request): array
    {
        return $request->query + $request->form + (str_starts_with(trim($request->body), '{') ? $request->json() : []);
    }

    /** The attachment's row written and the reference's actions told, for a file already stored. */
    private function inserted(\Minn\Media\PreparedUpload $prepared, Request $request): Response
    {
        $events = new PostEvents();
        $events->beforeSave($prepared->columns, null);
        $id = $this->library->insert($prepared);
        $events->attachedFile($this->library, $id, $prepared->relative);
        $events->attachmentAdded($id);
        $events->restInserted($id, $request, null);
        $events->restAfterInsert($id, $request, null);
        $events->afterInsert($id, null);
        // The reference cuts the sizes after the insert, storing the metadata as it goes; without plugins the engine cut them first.
        if ($events->live()) {
            $events->attachmentGenerated($id, $this->library->pathOf($prepared->relative), (string) $prepared->columns['post_mime_type']);
        } elseif ($prepared->metadata !== null) {
            $this->library->setMetadata($id, $prepared->metadata);
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
        // With plugins loaded, the reference's update: rest_pre_insert_attachment over the
        // prepared attachment, wp_update_post, then the REST actions with the alt text between.
        $attachment = new \stdClass();
        $attachment->ID = $attachmentId;
        if (isset($body['title'])) {
            $attachment->post_title = PostsWriteController::field($body['title']);
        }
        $attachment->post_type = 'attachment';
        $attachment->page_template = null;
        $args = $this->preparedAttachment($attachment, $body, $request) + array_intersect_key($columns, ['post_parent' => true]);
        $saved = \wp_update_post(\wp_slash($args), true, false);
        if ($saved instanceof \WP_Error) {
            throw new RestError($saved->get_error_code(), $saved->get_error_message(), 500);
        }
        // The reference's posts controller prepares a response here, before the alt text, and sets it aside.
        (new PostEvents())->restInserted($attachmentId, $request, $before);
        $this->object->build($this->posts->find($attachmentId), Context::Edit);
        $this->finishedWithPlugins($attachmentId, $body, $request, $before);
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
        $data = ['deleted' => true, 'previous' => array_diff_key($this->object->build($attachment, Context::Edit), ['_links' => true])];
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
