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

    /**
     * The image type the bytes themselves say they are, or null when they
     * are not an image the library knows. What the name claims is checked
     * against this: the reference refuses non-image bytes under an image
     * name and renames a file whose bytes are a different image type.
     */
    public function sniffedMime(): ?string
    {
        $info = $this->movedFrom !== null ? @getimagesize($this->movedFrom) : @getimagesizefromstring((string) $this->raw);
        $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
        return in_array($mime, Uploads::MIMES, true) ? $mime : null;
    }

    /** The same upload under a name whose extension matches a mime type. */
    public function renamedFor(string $mime): self
    {
        $extension = array_search($mime, Uploads::MIMES, true);
        if (!is_string($extension)) {
            return $this;
        }
        $stem = (string) preg_replace('/\.[^.]+$/', '', $this->filename);
        return new self("{$stem}.{$extension}", $this->movedFrom, $this->raw, $this->parent);
    }

    /** Whether the file is an image the engine will make sizes for; SVG is not. */
    public function isImage(): bool
    {
        $mime = $this->mime();
        return $mime !== null && str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml';
    }

    /** The post the client wants the file attached to: form field, query, or JSON body. */
    public static function parentOf(Request $request): int
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
