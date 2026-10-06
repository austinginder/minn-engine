<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Content\Slug;
use Minn\Support\Kses;

/**
 * The writes the media library makes. An Upload becomes an attachment: the
 * file lands in the dated uploads directory under a unique name, the row is
 * inserted with the stored name as its title and slug, and an image gets
 * its sub-sizes and the serialized metadata blob. Edits stamp the row
 * modified; removal takes the files with the row.
 */
final readonly class Writer
{
    public function __construct(
        private PostWriter $posts,
        private Posts $reads,
        private Site $site,
        private Uploads $uploads,
        private Images $images,
    ) {
    }

    /** The new attachment's id. The mime is the caller's to check first. */
    public function attach(Upload $upload, int $authorId): int
    {
        $prepared = $this->prepare($upload, $authorId);
        $id = $this->insert($prepared);
        $this->setAttachedFile($id, $prepared->relative);
        if ($prepared->metadata !== null) {
            $this->setMetadata($id, $prepared->metadata);
        }
        return $id;
    }

    /**
     * Stores the file and cuts its sizes, before the row exists: a decode
     * that fails leaves files to sweep, never a headless attachment.
     */
    public function prepare(Upload $upload, int $authorId): PreparedUpload
    {
        $filename = Uploads::sanitizeName($upload->filename);
        $relative = $this->uploads->store($filename, $upload->movedFrom, $upload->raw);
        return $this->prepareStored($relative, (string) $upload->mime(), $upload->parent, $authorId);
    }

    /**
     * The attachment for a file already in the uploads folder (one the
     * runtime's wp_handle_upload stored, plugins' filters and all): its row
     * and, for an image the engine sizes, its metadata.
     */
    public function prepareStored(string $relative, string $mime, int $parent, int $authorId): PreparedUpload
    {
        $row = $this->prepareRow($relative, $mime, $parent, $authorId);
        $sized = str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml' && in_array($mime, Uploads::MIMES, true);
        if (!$sized) {
            return new PreparedUpload($row->columns, $relative, null);
        }
        $metadata = $this->imageMetadata($relative, $mime);
        return new PreparedUpload($row->columns, (string) $metadata['file'], $metadata);
    }

    /**
     * The attachment's row alone, its sizes left to be cut once it exists:
     * with plugins loaded, the runtime's wp_generate_attachment_metadata
     * cuts them after the insert, as the reference does.
     */
    public function prepareRow(string $relative, string $mime, int $parent, int $authorId): PreparedUpload
    {
        $title = (string) preg_replace('/\.[^.]+$/', '', basename($relative));
        $now = $this->site->localNow();
        $nowGmt = gmdate('Y-m-d H:i:s');
        $columns = [
            'post_author' => $authorId,
            'post_date' => $now,
            'post_date_gmt' => $nowGmt,
            'post_content' => '',
            'post_title' => $title,
            'post_excerpt' => '',
            'post_status' => 'inherit',
            'comment_status' => $this->site->defaultDiscussion('attachment', 'comment'),
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
        ];
        return new PreparedUpload($columns, $relative, null);
    }

    /** A stored file's path on disk. */
    public function pathOf(string $relative): string
    {
        return $this->uploads->pathFor($relative);
    }

    /** A stored file's path relative to the uploads folder, or null for one outside it. */
    public function relativeOf(string $absolutePath): ?string
    {
        return $this->uploads->relativeOf($absolutePath);
    }

    /** Writes a prepared attachment's row; returns its id. */
    public function insert(PreparedUpload $prepared): int
    {
        return $this->posts->insert($prepared->columns);
    }

    /** Records the file an attachment holds, relative to the uploads folder. */
    public function setAttachedFile(int $id, string $relative): void
    {
        $this->posts->setMeta($id, '_wp_attached_file', $relative);
    }

    /** Stores an attachment's metadata in the reference's serialized shape. @param array<string, mixed> $metadata */
    public function setMetadata(int $id, array $metadata): void
    {
        $this->posts->setMeta($id, '_wp_attachment_metadata', Metadata::serialize($metadata));
    }

    /** Sets the given columns on an attachment and stamps it modified; nothing happens for none. */
    public function edit(int $id, array $columns): void
    {
        if ($columns !== []) {
            $this->stamp($id, $columns);
        }
    }

    /** Sets the given columns, none included, and stamps the attachment modified, as the reference's update always does. @param array<string, mixed> $columns */
    public function stamp(int $id, array $columns): void
    {
        $columns['post_modified'] = $this->site->localNow();
        $columns['post_modified_gmt'] = gmdate('Y-m-d H:i:s');
        $this->posts->update($id, $columns);
    }

    /** Sets an attachment's alt text. */
    public function setAlt(int $id, string $alt): void
    {
        $this->posts->setMeta($id, '_wp_attachment_image_alt', $alt);
    }

    /** Removes an attachment: its files, every generated size, its meta, and its row. */
    public function remove(PostRecord $attachment): void
    {
        $file = $this->reads->meta($attachment->id, '_wp_attached_file');
        if ($file !== null && $file !== '') {
            $this->uploads->remove($file, Metadata::parse($this->reads->meta($attachment->id, '_wp_attachment_metadata'))['sizes']);
        }
        $this->posts->destroy($attachment->id);
    }

    /**
     * An image's metadata as the reference makes it without plugins: its
     * own description (Media\PhotoMeta), a stand-in when it is big or taken
     * turned (which becomes the attached file, the upload kept as
     * original_image), and the sizes cut from the upload.
     *
     * @return array<string, mixed>
     */
    private function imageMetadata(string $relative, string $mime): array
    {
        $path = $this->uploads->pathFor($relative);
        [$width, $height] = getimagesize($path) ?: [0, 0];
        $photo = PhotoMeta::read($path, PhotoMeta::EXIF_TYPES, static fn (string $text): string => Kses::post($text))['meta'] ?? Metadata::blankImageMeta();
        $meta = ['width' => (int) $width, 'height' => (int) $height, 'file' => $relative, 'filesize' => (int) filesize($path), 'sizes' => [], 'image_meta' => $photo];
        $orientation = (int) $photo['orientation'];
        $standIn = $this->images->standIn($path, $mime, $orientation);
        if ($standIn !== null) {
            $meta = ['width' => $standIn['width'], 'height' => $standIn['height'], 'file' => (string) $this->uploads->relativeOf($standIn['path']), 'filesize' => $standIn['filesize']] + $meta + ['original_image' => basename($path)];
            if ($standIn['rotated']) {
                $meta['image_meta']['orientation'] = 1;
            }
        }
        $meta['sizes'] = $this->images->makeSubsizes($path, $mime, $meta, $orientation);
        return $meta;
    }
}
