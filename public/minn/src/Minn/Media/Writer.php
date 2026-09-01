<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Content\PostWriter;
use Minn\Content\Site;
use Minn\Content\Slug;

/**
 * Turns an Upload into an attachment: the file lands in the dated uploads
 * directory under a unique name, the row is inserted with the stored
 * name as its title and slug, and an image gets its sub-sizes and the
 * serialized metadata blob.
 */
final readonly class Writer
{
    public function __construct(
        private PostWriter $posts,
        private Site $site,
        private Uploads $uploads,
        private Images $images,
    ) {
    }

    /** The new attachment's id. The mime is the caller's to check first. */
    public function attach(Upload $upload, int $authorId): int
    {
        $filename = Uploads::sanitizeName($upload->filename);
        $mime = (string) $upload->mime();
        $relative = $this->uploads->store($filename, $upload->movedFrom, $upload->raw);
        $title = preg_replace('/\.[^.]+$/', '', basename($relative));
        $now = $this->site->localNow();
        $nowGmt = gmdate('Y-m-d H:i:s');

        $id = $this->posts->insert([
            'post_author' => $authorId,
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
            'post_parent' => $upload->parent,
            'guid' => $this->uploads->urlFor($relative),
            'menu_order' => 0,
            'post_type' => 'attachment',
            'post_mime_type' => $mime,
            'comment_count' => 0,
        ]);
        $this->posts->setMeta($id, '_wp_attached_file', $relative);
        if ($upload->isImage()) {
            $this->posts->setMeta($id, '_wp_attachment_metadata', Metadata::serialize($this->imageMetadata($relative, $mime)));
        }
        return $id;
    }

    /** @return array<string, mixed> */
    private function imageMetadata(string $relative, string $mime): array
    {
        $path = $this->uploads->pathFor($relative);
        [$width, $height] = getimagesize($path) ?: [0, 0];
        return [
            'width' => (int) $width,
            'height' => (int) $height,
            'file' => $relative,
            'filesize' => (int) filesize($path),
            'sizes' => $this->images->makeSubsizes($path, $mime),
            'image_meta' => Metadata::blankImageMeta(),
        ];
    }
}
