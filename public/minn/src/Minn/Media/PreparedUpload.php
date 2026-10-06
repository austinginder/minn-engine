<?php

declare(strict_types=1);

namespace Minn\Media;

/**
 * An upload made ready for its attachment: the file stored, its sizes cut,
 * the row it will be. The row is written apart, so what plugins hear
 * before it (pre_post_insert) comes between.
 */
final readonly class PreparedUpload
{
    /**
     * @param array<string, mixed> $columns the attachment's row
     * @param array<string, mixed>|null $metadata the image metadata, null for a file that is not an image
     */
    public function __construct(
        public array $columns,
        public string $relative,
        public ?array $metadata,
    ) {
    }
}
