<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Content\Slug;
use Minn\Db;
use Minn\Http\Method;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Media\Images;
use Minn\Media\Metadata;
use Minn\Media\Uploads;
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
        private PostWriter $writer,
        private Site $site,
        private Uploads $uploads,
        private Images $images,
        private MediaObject $object,
        private Caller $caller,
    ) {
    }

    #[Route(Method::Get, '/wp/v2/media')]
    public function list(Request $request): Response
    {
        $edit = $request->query('context') === 'edit';
        if ($edit && !$this->caller->can('edit_posts')) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit posts in this post type.');
        }
        $perPage = max(1, min(100, (int) $request->query('per_page', '10')));
        $page = max(1, (int) $request->query('page', '1'));
        $order = strtoupper((string) $request->query('order', 'desc')) === 'ASC' ? 'ASC' : 'DESC';
        $where = "post_type = 'attachment' AND post_status = 'inherit'";
        $params = [];
        $this->applyListFilters($request, $where, $params);
        $table = $this->db->table('posts');
        $total = (int) $this->db->value("SELECT COUNT(*) FROM {$table} WHERE {$where}", $params);
        $rows = $this->db->rows(
            "SELECT * FROM {$table} WHERE {$where} ORDER BY post_date {$order}, ID {$order} LIMIT ? OFFSET ?",
            [...$params, $perPage, ($page - 1) * $perPage],
        );
        return Reply::list(
            array_map(fn (array $p) => $this->object->build($p, $edit), $rows),
            $total,
            (int) ceil($total / $perPage),
            Fields::fromQuery($request->query),
        );
    }

    /**
     * The library's query args, captured from the oracle: author/parent as
     * id lists (parent keeps 0 for unattached), media_type as a mime
     * prefix, after/before exclusive on site-local post_date, search as
     * every word in title/excerpt/content.
     *
     * @param array<int, mixed> $params
     */
    private function applyListFilters(Request $request, string &$where, array &$params): void
    {
        $include = self::intList((string) $request->query('include', ''));
        if ($include !== []) {
            $where .= ' AND ID IN (' . implode(',', array_fill(0, count($include), '?')) . ')';
            $params = [...$params, ...$include];
        }
        $author = $request->query('author');
        if ($author !== null && $author !== '') {
            $ids = self::intList($author);
            if ($ids !== []) {
                $where .= ' AND post_author IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = [...$params, ...$ids];
            }
        }
        $authorExclude = $request->query('author_exclude');
        if ($authorExclude !== null && $authorExclude !== '') {
            $ids = self::intList($authorExclude);
            if ($ids !== []) {
                $where .= ' AND post_author NOT IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = [...$params, ...$ids];
            }
        }
        $parent = $request->query('parent');
        if ($parent !== null && $parent !== '') {
            $ids = self::intList($parent, true);
            if ($ids !== []) {
                $where .= ' AND post_parent IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
                $params = [...$params, ...$ids];
            }
        }
        $mediaType = $request->query('media_type');
        if ($mediaType !== null && $mediaType !== '') {
            $allowed = ['image', 'video', 'text', 'application', 'audio'];
            if (!in_array($mediaType, $allowed, true)) {
                $message = 'media_type[0] is not one of image, video, text, application, and audio.';
                throw new RestError('rest_invalid_param', 'Invalid parameter(s): media_type', 400, [
                    'params' => ['media_type' => $message],
                    'details' => ['media_type' => ['code' => 'rest_not_in_enum', 'message' => $message, 'data' => null]],
                ]);
            }
            $where .= ' AND post_mime_type LIKE ?';
            $params[] = $mediaType . '/%';
        }
        $mime = $request->query('mime_type');
        if ($mime !== null && $mime !== '') {
            $where .= ' AND post_mime_type = ?';
            $params[] = $mime;
        }
        foreach (preg_split('/\s+/', trim((string) $request->query('search', ''))) ?: [] as $word) {
            if ($word === '') {
                continue;
            }
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $where .= ' AND (post_title LIKE ? OR post_excerpt LIKE ? OR post_content LIKE ?)';
            $params = [...$params, $like, $like, $like];
        }
        $after = $request->query('after');
        if ($after !== null && $after !== '') {
            $where .= ' AND post_date > ?';
            $params[] = self::restDate($after, 'after');
        }
        $before = $request->query('before');
        if ($before !== null && $before !== '') {
            $where .= ' AND post_date < ?';
            $params[] = self::restDate($before, 'before');
        }
    }

    /** @return list<int> */
    private static function intList(string $csv, bool $keepZero = false): array
    {
        $out = [];
        foreach (explode(',', $csv) as $part) {
            $part = trim($part);
            if ($part === '' || !ctype_digit($part)) {
                continue;
            }
            $n = (int) $part;
            if ($n !== 0 || $keepZero) {
                $out[] = $n;
            }
        }
        return array_values(array_unique($out));
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

    #[Route(Method::Get, '/wp/v2/media/{id:\d+}')]
    public function single(Request $request, string $id): Response
    {
        $attachment = $this->attachment((int) $id);
        $edit = $request->query('context') === 'edit';
        if ($edit && !$this->caller->can('edit_post', (int) $id)) {
            throw $this->caller->refuse('rest_forbidden_context', 'Sorry, you are not allowed to edit this post.');
        }
        return Reply::item($this->object->build($attachment, $edit), Fields::fromQuery($request->query));
    }

    #[Route(Method::Post, '/wp/v2/media')]
    public function create(Request $request): Response
    {
        $userId = $this->caller->require('rest_cannot_create', 'Sorry, you are not allowed to create posts as this user.')->id();
        if (!$this->caller->can('upload_files')) {
            throw new RestError('rest_cannot_create', 'Sorry, you are not allowed to upload media on this site.', 403);
        }

        $parent = (int) ($request->form['post'] ?? $request->query('post') ?? (trim($request->body) !== '' && str_starts_with(trim($request->body), '{') ? ($request->json()['post'] ?? 0) : 0));
        if ($parent > 0 && !$this->caller->can('edit_post', $parent)) {
            throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to upload media to this post.', 403);
        }
        $filename = '';
        $movedFrom = null;
        $raw = null;
        if (!empty($request->files['file']['tmp_name'])) {
            $filename = (string) $request->files['file']['name'];
            $movedFrom = (string) $request->files['file']['tmp_name'];
        } else {
            if (preg_match('/filename\*?="?([^";]+)"?/', (string) ($request->header('content-disposition') ?? ''), $m)) {
                $filename = trim($m[1]);
            }
            $raw = $request->body;
            if ($filename === '' || $raw === '') {
                throw new RestError('rest_upload_no_data', 'No data supplied.', 400);
            }
        }

        $filename = Uploads::sanitizeName($filename);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = Uploads::MIMES[$ext] ?? null;
        if ($mime === null) {
            throw new RestError('rest_upload_unknown_error', 'Sorry, you are not allowed to upload this file type.', 500);
        }
        $relative = $this->uploads->store($filename, $movedFrom, $raw);
        $path = $this->uploads->pathFor($relative);
        $stored = basename($relative);
        $isImage = str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';
        $title = preg_replace('/\.[^.]+$/', '', $stored);
        $now = $this->site->localNow();
        $nowGmt = gmdate('Y-m-d H:i:s');

        $id = $this->writer->insert([
            'post_author' => $userId,
            'post_date' => $now,
            'post_date_gmt' => $nowGmt,
            'post_content' => '',
            'post_title' => $title,
            'post_excerpt' => '',
            'post_status' => 'inherit',
            'comment_status' => 'open',
            'ping_status' => 'closed',
            'post_password' => '',
            'post_name' => Slug::sanitize($title),
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => $now,
            'post_modified_gmt' => $nowGmt,
            'post_content_filtered' => '',
            'post_parent' => $parent,
            'guid' => $this->uploads->urlFor($relative),
            'menu_order' => 0,
            'post_type' => 'attachment',
            'post_mime_type' => $mime,
            'comment_count' => 0,
        ]);
        $this->writer->setMeta($id, '_wp_attached_file', $relative);
        if ($isImage) {
            [$width, $height] = getimagesize($path) ?: [0, 0];
            $this->writer->setMeta($id, '_wp_attachment_metadata', Metadata::serialize([
                'width' => (int) $width,
                'height' => (int) $height,
                'file' => $relative,
                'filesize' => (int) filesize($path),
                'sizes' => $this->images->makeSubsizes($path, $mime),
                'image_meta' => Metadata::blankImageMeta(),
            ]));
        }
        return Reply::item($this->object->build($this->posts->find($id), true), Fields::fromQuery($request->query), 201)
            ->withHeader('Location', $this->object->url()->to('/wp/v2/media/' . $id));
    }

    /** The editable fields the app uses. */
    #[Route(Method::Post, '/wp/v2/media/{id:\d+}')]
    #[Route(Method::Put, '/wp/v2/media/{id:\d+}')]
    #[Route(Method::Patch, '/wp/v2/media/{id:\d+}')]
    public function update(Request $request, string $id): Response
    {
        $attachmentId = (int) $id;
        $this->attachment($attachmentId);
        if (!$this->caller->can('edit_post', $attachmentId)) {
            throw $this->caller->refuse('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.');
        }
        $body = $request->json();
        $columns = [];
        foreach (['title' => 'post_title', 'caption' => 'post_excerpt', 'description' => 'post_content'] as $field => $column) {
            if (isset($body[$field])) {
                $markup = PostsWriteController::field($body[$field]);
                $columns[$column] = $this->caller->can('unfiltered_html') ? $markup : Kses::filter($markup, Kses::POST);
            }
        }
        if (array_key_exists('post', $body)) {
            $parent = (int) $body['post'];
            if ($parent > 0 && !$this->caller->can('edit_post', $parent)) {
                throw new RestError('rest_cannot_edit', 'Sorry, you are not allowed to edit this post.', 403);
            }
            $columns['post_parent'] = $parent;
        }
        if (isset($body['alt_text'])) {
            $this->setMetaValue($attachmentId, '_wp_attachment_image_alt', (string) $body['alt_text']);
        }
        if ($columns !== []) {
            $columns['post_modified'] = $this->site->localNow();
            $columns['post_modified_gmt'] = gmdate('Y-m-d H:i:s');
            $this->writer->update($attachmentId, $columns);
        }
        return Reply::item($this->object->build($this->posts->find($attachmentId), true), Fields::fromQuery($request->query));
    }

    /** Attachments cannot be trashed; force removes the row, its meta, and its files. */
    #[Route(Method::Delete, '/wp/v2/media/{id:\d+}')]
    public function delete(Request $request, string $id): Response
    {
        $attachmentId = (int) $id;
        $attachment = $this->attachment($attachmentId);
        if (!$this->caller->can('delete_post', $attachmentId)) {
            throw $this->caller->refuse('rest_cannot_delete', 'Sorry, you are not allowed to delete this post.');
        }
        if (!filter_var($request->query('force', ''), FILTER_VALIDATE_BOOLEAN)) {
            throw new RestError('rest_trash_not_supported', "The post does not support trashing. Set 'force=true' to delete.", 501);
        }
        $previous = $this->object->build($attachment, true);
        $file = $this->posts->meta($attachmentId, '_wp_attached_file');
        if ($file !== null && $file !== '') {
            $this->uploads->remove($file, Metadata::parse($this->posts->meta($attachmentId, '_wp_attachment_metadata'))['sizes']);
        }
        $this->db->execute("DELETE FROM {$this->db->table('postmeta')} WHERE post_id = ?", [$attachmentId]);
        $this->db->execute("DELETE FROM {$this->db->table('posts')} WHERE ID = ?", [$attachmentId]);
        return Reply::item(['deleted' => true, 'previous' => $previous], Fields::fromQuery($request->query));
    }

    private function attachment(int $id): array
    {
        $row = $this->posts->find($id);
        if ($row === null || $row['post_type'] !== 'attachment') {
            throw new RestError('rest_post_invalid_id', 'Invalid post ID.', 404);
        }
        return $row;
    }

    private function setMetaValue(int $id, string $key, string $value): void
    {
        if ($this->posts->meta($id, $key) === null) {
            $this->writer->setMeta($id, $key, $value);
            return;
        }
        $this->db->execute(
            "UPDATE {$this->db->table('postmeta')} SET meta_value = ? WHERE post_id = ? AND meta_key = ?",
            [$value, $id, $key],
        );
    }
}
