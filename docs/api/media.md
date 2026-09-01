# `Minn\Media`

uploads, image sizes and attachment metadata

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`Canvas`](#canvas) | final readonly class | 81 | One GD bitmap and the operations the media layer needs on it. Every |
| [`Gallery`](#gallery) | final class | 52 | The classic `[gallery]` shortcode's markup. Every gallery on a page is |
| [`Images`](#images) | final readonly class | 63 | GD sub-size generation from the size options the site stores. |
| [`Kind`](#kind) | final class | 52 | Whether an attachment is an image, audio, video, or a given extension, judged by its MIME type first and its file extension second. |
| [`Metadata`](#metadata) | final class | 106 | The _wp_attachment_metadata blob: parsed by scanning for the shapes it |
| [`Sizing`](#sizing) | final class | 207 | The image size arithmetic the media functions share: the crop or scale a |
| [`Upload`](#upload) | final readonly class | 50 | One file arriving for the library, on either transport: a multipart |
| [`Uploads`](#uploads) | final readonly class | 165 | The uploads directory: paths, URLs, the allowed types, and landing a file. |
| [`Writer`](#writer) | final readonly class | 66 | Turns an Upload into an attachment: the file lands in the dated uploads |

## Canvas

`final readonly class Minn\Media\Canvas` · `public/minn/src/Minn/Media/Canvas.php`

One GD bitmap and the operations the media layer needs on it. Every
operation returns a new canvas; alpha is preserved throughout, which is
what makes PNG and WebP sub-sizes match the reference's.

- readonly `int $width`
- readonly `int $height`

### static `open(string $path): ?self`

### `resample(array $box): self`

Resamples a source box onto a destination box.

- `@param array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int} $box dst x, dst y, src x, src y, dst w, dst h, src w, src h (the GD argument order)`

### `crop(int $x, int $y, int $width, int $height, ?int $targetWidth = NULL, ?int $targetHeight = NULL): self`

### `rotate(float $angle): ?self`

### `flip(bool $vertical, bool $horizontal): self`

### `write(string $path, string $mime, int $quality): bool`

Writes the bitmap in the given format; the directory is created when missing.

### `stream(string $mime, int $quality): bool`


## Gallery

`final class Minn\Media\Gallery` · `public/minn/src/Minn/Media/Gallery.php`

The classic `[gallery]` shortcode's markup. Every gallery on a page is
numbered from one shared per-request counter, and the wrapper names both
that number and the post the gallery sits in, so two galleries on one page
can be styled apart.

The single quotes around the wrapper attributes and the tab-indented item
body are the reference's, and themes have styled against them for long
enough that they are contract rather than formatting.

### static `render(array $items, array $args, int $instance, int $postId): string`

- `@param list<array{icon: string, orientation: string, caption: string, caption_id: string}> $items`
- `@param array{itemtag: string, icontag: string, captiontag: string, columns: int, size: string} $args`


## Images

`final readonly class Minn\Media\Images` · `public/minn/src/Minn/Media/Images.php`

GD sub-size generation from the size options the site stores.

```php
__construct(Minn\Content\Site $site)
```

### `ladder(): array`

- `@return array<string, array{0: int, 1: int, 2: bool}> name => [max width, max height, crop]`

### static `constrain(int $width, int $height, int $maxWidth, int $maxHeight): array`

Fits (w, h) inside (maxW, maxH); 0 means unconstrained. One rule with the facade's wp_constrain_dimensions.

### `makeSubsizes(string $path, string $mime): array`

Generates the sub-sizes for one image; returns the sizes metadata map.


## Kind

`final class Minn\Media\Kind` · `public/minn/src/Minn/Media/Kind.php`

Whether an attachment is an image, audio, video, or a given extension, judged by its MIME type first and its file extension second.

### static `matchWildcards(array $patterns, array $reals): array`

Real mime types grouped under the wildcard patterns that match them
(image, audio/*, image/jpeg, *); a pattern with no matches is omitted.

- `@param list<string> $patterns`
- `@param list<string> $reals`
- `@return array<string, list<string>>`

### static `matches(string $type, string $mime, string $extension, array $audioExtensions, array $videoExtensions): bool`


## Metadata

`final class Minn\Media\Metadata` · `public/minn/src/Minn/Media/Metadata.php`

The _wp_attachment_metadata blob: parsed by scanning for the shapes it
holds (top-level dims and file, the sizes map, the image_meta scalars)
and written back in the reference's stored form: a:6 at the top,
image_meta as a:13 with alt last.

### static `parse(?string $blob): array`

- `@return array{width: int, height: int, file: string, filesize: int, sizes: array<string, array>, image_meta: ?array}`

### static `serialize(array $meta): string`

### static `blankImageMeta(): array`

The image_meta block a fresh upload carries.


## Sizing

`final class Minn\Media\Sizing` · `public/minn/src/Minn/Media/Sizing.php`

The image size arithmetic the media functions share: the crop or scale a
resize needs, the registered size a requested box picks, and the srcset
candidates an image's sizes yield. Behaviour pinned by contracts/fixtures/api/media.json.

### static `constrain(int $width, int $height, int $maxWidth, int $maxHeight): array`

The largest box inside the limits that keeps the ratio: only sides over
their limit shrink, the smaller ratio wins, and a result one pixel short
of a limit it was constrained by snaps to that limit.

- `@return array{0: int, 1: int}`

### static `resize(int $origW, int $origH, int $destW, int $destH, bool $crop, Closure $constrain): ?array`

The GD-style resize box (dst x, dst y, src x, src y, dst w, dst h, src w, src h),
or null when the image would only grow or nothing changes.

- `@param Closure(int, int, int, int): array{0: int, 1: int} $constrain scales a box into a maximum`
- `@return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int}|null`

### static `intermediate(array $meta, array|string $size, ?string $fileUrl, Closure $editorConstrain): ?array`

The registered size that serves a request: by name, or the smallest
size that covers a requested box (the full size never counts), with
its path and URL beside the file's.

- `@param array<string, mixed> $meta attachment metadata`
- `@param string|array{0: int, 1: int} $size`
- `@param Closure(int, int, array): array{0: int, 1: int} $editorConstrain`
- `@return array<string, mixed>|null`

### static `sources(array $sizeArray, string $src, array $meta, Closure $matchesRatio): array`

The srcset candidates by width: every size on the image's ratio (and
the same edit, when the file was edited), the source itself winning a
width clash.

- `@param array{0: int, 1: int} $sizeArray`
- `@param Closure(int, int, int, int): bool $matchesRatio`
- `@return array<int, array{url: string, descriptor: string, value: int}>`

### static `srcset(array $sources): ?string`

The srcset attribute for at least two sources; null for fewer.

### static `editorSizes(array $meta, string $baseUrl, string $fullUrl, array $names, Closure $downsize): array`

The image sizes an attachment offers the editor, each with orientation. @param Closure(string): (array|false) $downsize

- `@param Closure(string): (array|false) $downsize`


## Upload

`final readonly class Minn\Media\Upload` · `public/minn/src/Minn/Media/Upload.php`

One file arriving for the library, on either transport: a multipart
field named "file" (a temporary upload to move) or a raw body whose
Content-Disposition names the file. The parent is read from the same
request, wherever the client put it.

```php
__construct(string $filename, ?string $movedFrom, ?string $raw, int $parent = 0)
```

- readonly `string $filename`
- readonly `?string $movedFrom`
- readonly `?string $raw`
- readonly `int $parent`

### static `fromRequest(Minn\Http\Request $request): ?self`

Null when the request carries no file.

### `mime(): ?string`

The mime type the extension maps to, or null for one the library refuses.

### `isImage(): bool`


## Uploads

`final readonly class Minn\Media\Uploads` · `public/minn/src/Minn/Media/Uploads.php`

The uploads directory: paths, URLs, the allowed types, and landing a file.

- const `MIMES` = `array (   'png' => 'image/png',   'jpg' => 'image/jpeg',   'jpeg' => 'image/jpeg',   'gif' => 'image/gif',   'webp' => 'image/webp',   'pdf' => 'application/pdf',   'txt' => 'text/plain',   'mp4' => 'video/mp4',   'mp3' => 'audio/mpeg',   'zip' => 'application/zip', )` — extension => canonical mime

```php
__construct(Minn\Content\Site $site, Minn\Front\Permalinks $permalinks, string $baseDir)
```

### `baseDir(): string`

### `baseUrl(): string`

### `urlFor(string $relativePath): string`

### `pathFor(string $relativePath): string`

### static `sanitizeName(string $filename): string`

A safe file name: one extension. Every dot-separated part between the
base and the last extension that is not itself an allowed extension
is joined with an underscore, so "shell.php.png" lands as
"shell_php.png" and no handler can be talked into running it.

### `store(string $filename, ?string $movedFrom, ?string $raw): string`

Lands content in the dated directory under a unique name and returns
the relative path ("2026/08/name.png").

### `remove(string $relativePath, array $sizes): void`

Removes the original and every generated size.

### static `attachmentFiles(string $file, array $meta, ?array $backupSizes): array`

Every file an attachment owns, in deletion order: the legacy thumb, each
sub-size, the original image, the backup sizes, then the file itself.

- `@param array<string, mixed> $meta the attachment metadata`
- `@param array<string, array<string, mixed>>|null $backupSizes`
- `@return list<string> absolute paths, unique, only the main file checked for existence`

### static `layout(string $uploadPath, string $uploadUrlPath, string $siteUrl, string $contentDir, string $contentUrl, string $abspath, bool $yearMonth, string $time): array`

Where uploads live, from the site's options: the default folder under
wp-content, a relative upload_path under ABSPATH, or an absolute one;
the URL likewise, with year/month subfolders when the site asks.

- `@return array{path: string, url: string, subdir: string, basedir: string, baseurl: string, error: false}`


## Writer

`final readonly class Minn\Media\Writer` · `public/minn/src/Minn/Media/Writer.php`

Turns an Upload into an attachment: the file lands in the dated uploads
directory under a unique name, the row is inserted with the stored
name as its title and slug, and an image gets its sub-sizes and the
serialized metadata blob.

```php
__construct(Minn\Content\PostWriter $posts, Minn\Content\Site $site, Minn\Media\Uploads $uploads, Minn\Media\Images $images)
```

### `attach(Minn\Media\Upload $upload, int $authorId): int`

The new attachment's id. The mime is the caller's to check first.

