<?php

declare(strict_types=1);

namespace Minn\Media;

/**
 * Where the media library's files live, as a REST upload names them: a
 * stored file's address, its path on disk, and its path relative to the
 * uploads folder. The attachment itself is written through the runtime
 * (wp_handle_upload, wp_insert_attachment, wp_generate_attachment_metadata),
 * as the reference writes it.
 */
final readonly class Writer
{
    public function __construct(private Uploads $uploads)
    {
    }

    /** A stored file's address. */
    public function urlOf(string $relative): string
    {
        return $this->uploads->urlFor($relative);
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
}
