<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Http\Request;
use Minn\RestError;

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
        $filename = (string) self::filenameFrom([(string) ($request->header('content-disposition') ?? '')]);
        if ($filename === '' || $request->body === '') {
            return null;
        }
        return new self($filename, null, $request->body, $parent);
    }

    /**
     * The file a Content-Disposition names, as the reference reads one
     * (probe rest-attachments-controller): the parameters after the
     * disposition type, split on semicolons as written; the last non-empty
     * filename= of all the headers, unquoted when double
     * quotes wrap it. "filename" is matched as written, and filename* is
     * not read. Null when none names one.
     *
     * @param list<string> $headers
     */
    public static function filenameFrom(array $headers): ?string
    {
        $found = null;
        foreach ($headers as $header) {
            foreach (array_slice(explode(';', (string) $header), 1) as $part) {
                [$key, $value] = array_pad(explode('=', $part, 2), 2, null);
                $value = trim((string) $value);
                if (trim($key) === 'filename' && $value !== '') {
                    $found = strlen($value) > 1 && $value[0] === '"' && str_ends_with($value, '"') ? substr($value, 1, -1) : $value;
                }
            }
        }
        return $found;
    }

    /**
     * Why a raw body cannot be taken, in the reference's order: no data, no
     * Content-Type, no Content-Disposition, one naming no file, a
     * Content-MD5 the data does not match. Null when it can. The headers are
     * the REST request's, keyed by lower-case names with underscores.
     *
     * @param array<string, list<string>> $headers
     */
    public static function refusal(string $data, array $headers): ?RestError
    {
        $disposition = self::filenameFrom((array) ($headers['content_disposition'] ?? []));
        return match (true) {
            $data === '' => new RestError('rest_upload_no_data', 'No data supplied.', 400),
            empty($headers['content_type']) => new RestError('rest_upload_no_content_type', 'No Content-Type supplied.', 400),
            empty($headers['content_disposition']) => new RestError('rest_upload_no_content_disposition', 'No Content-Disposition supplied.', 400),
            $disposition === null || $disposition === '' => new RestError('rest_upload_invalid_disposition', 'Invalid Content-Disposition supplied. Content-Disposition needs to be formatted as `attachment; filename="image.png"` or similar.', 400),
            !empty($headers['content_md5']) && md5($data) !== trim((string) $headers['content_md5'][0]) => new RestError('rest_upload_hash_mismatch', 'Content hash did not match expected.', 412),
            default => null,
        };
    }

    /** The mime type the extension maps to, or null for one the library refuses. */
    public function mime(): ?string
    {
        return Uploads::MIMES[strtolower(pathinfo($this->filename, PATHINFO_EXTENSION))] ?? null;
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
