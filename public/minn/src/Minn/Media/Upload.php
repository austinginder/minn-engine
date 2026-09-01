<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Http\Request;

/**
 * One file arriving for the library, on either transport: a multipart
 * field named "file" (a temporary upload to move) or a raw body whose
 * Content-Disposition names the file. The parent is read from the same
 * request, wherever the client put it.
 */
final readonly class Upload
{
    public function __construct(
        public string $filename,
        public ?string $movedFrom,
        public ?string $raw,
        public int $parent = 0,
    ) {
    }

    /** Null when the request carries no file. */
    public static function fromRequest(Request $request): ?self
    {
        $parent = self::parentOf($request);
        $file = $request->files['file'] ?? [];
        if (!empty($file['tmp_name'])) {
            return new self((string) $file['name'], (string) $file['tmp_name'], null, $parent);
        }
        $filename = preg_match('/filename\*?="?([^";]+)"?/', (string) ($request->header('content-disposition') ?? ''), $m) === 1
            ? trim($m[1])
            : '';
        if ($filename === '' || $request->body === '') {
            return null;
        }
        return new self($filename, null, $request->body, $parent);
    }

    /** The mime type the extension maps to, or null for one the library refuses. */
    public function mime(): ?string
    {
        return Uploads::MIMES[strtolower(pathinfo($this->filename, PATHINFO_EXTENSION))] ?? null;
    }

    public function isImage(): bool
    {
        $mime = $this->mime();
        return $mime !== null && str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';
    }

    private static function parentOf(Request $request): int
    {
        if (isset($request->form['post'])) {
            return (int) $request->form['post'];
        }
        if ($request->query('post') !== null) {
            return (int) $request->query('post');
        }
        return str_starts_with(trim($request->body), '{') ? (int) ($request->json()['post'] ?? 0) : 0;
    }
}
