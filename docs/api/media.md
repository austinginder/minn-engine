# `Minn\Media`

uploads, image sizes and attachment metadata

| Class | Kind | Lines | Summary |
|---|---|---|---|
| [`AvifInfo`](#avifinfo) | final class | 142 | An AVIF file's primary image as wp_get_avif_info reports it (probe |
| [`Canvas`](#canvas) | final readonly class | 168 | One GD bitmap and the operations the media layer needs on it. Every |
| [`Gallery`](#gallery) | final class | 73 | The classic `[gallery]` shortcode's markup. Every gallery on a page is |
| [`Icons`](#icons) | final class | 68 | The icon a file type is shown with, as wp_mime_type_icon finds it (probe |
| [`Kind`](#kind) | final class | 79 | Whether an attachment is an image, audio, video, or a given extension, judged by its MIME type first and its file extension second. |
| [`Metadata`](#metadata) | final class | 112 | The _wp_attachment_metadata blob: parsed by scanning for the shapes it |
| [`PhotoMeta`](#photometa) | final class | 165 | A photo's own description, read as the reference's wp_read_image_metadata |
| [`Sizing`](#sizing) | final class | 249 | The image size arithmetic the media functions share: the crop or scale a |
| [`Upload`](#upload) | final readonly class | 89 | One file arriving for the library, on either transport: a multipart |
| [`Uploads`](#uploads) | final readonly class | 135 | The uploads directory: paths, URLs, the allowed types, and landing a file. |
| [`WebpInfo`](#webpinfo) | final class | 24 | A WebP file's size and kind from its first 40 bytes, as wp_get_webp_info |
| [`Writer`](#writer) | final readonly class | 24 | Where the media library's files live, as a REST upload names them: a |

## AvifInfo

`final class Minn\Media\AvifInfo` · `public/minn/src/Minn/Media/AvifInfo.php`

An AVIF file's primary image as wp_get_avif_info reports it (probe
media-tags): width and height from its spatial-extent property, bit depth
and channel count from its pixel-information property. The file is an
ISO base media file: an ftyp box naming the avif or avis brand, then a meta
box whose primary item (pitm) is tied to its properties (ipco) by the
association table (ipma). Anything else, or a file it cannot follow, is
unknown in every field.

- const `UNKNOWN` = `array (   'width' => false,   'height' => false,   'bit_depth' => false,   'num_channels' => false, )`

### static `read(string $bytes): array`

The primary image's size, bit depth and channels from a file's first bytes.

- `@return array{width: int|false, height: int|false, bit_depth: int|false, num_channels: int|false}`

Internals: `isAvif()` (private, line 63), `primaryItem()` (private, line 76), `associations()` (private, line 90), `boxes()` (private, line 121), `ordered()` (private, line 135)


## Canvas

`final readonly class Minn\Media\Canvas` · `public/minn/src/Minn/Media/Canvas.php`

One GD bitmap and the operations the media layer needs on it. Every
operation returns a new canvas; alpha is preserved throughout, which is
what makes PNG and WebP sub-sizes match the reference's.

- readonly `int $width`
- readonly `int $height`

### static `of(GdImage $image): self`

A canvas over a GD image already in hand (what WP_Image_Editor_GD::_save is given).

### `image(): GdImage`

The GD image behind the canvas (what WP_Image_Editor_GD::_resize hands back).

### static `open(string $path): ?self`

A canvas from an image file, or null when it cannot be decoded or
would not fit in memory. The header names the dimensions before a
pixel is decoded, so a small file that claims a huge canvas is
refused instead of taking the request down.

### `resample(array $box): self`

Resamples a source box onto a destination box.

- `@param array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int} $box dst x, dst y, src x, src y, dst w, dst h, src w, src h (the GD argument order)`

### `crop(int $x, int $y, int $width, int $height, ?int $targetWidth = NULL, ?int $targetHeight = NULL): self`

A canvas cut to a rectangle, resized to a target when given.

### `rotate(float $angle): ?self`

A canvas turned by an angle, or null when GD refuses.

### `flipVertical(): self`

A canvas mirrored on either axis.

### `flipHorizontal(): self`

A canvas mirrored left to right.

### `copy(): self`

An untouched copy of the canvas.

### `write(string $path, string $mime, int $quality): bool`

Writes the bitmap in the given format; the directory is created when missing.

### `progressive(): self`

The bitmap written progressively from now on (an interlaced JPEG).

### `baseline(): self`

The bitmap written line by line from now on, as it is by default.

### `stream(string $mime, int $quality): bool`

Writes the image to the output in a format, at a quality.

Internals: `affordable()` (private, line 60), `memoryLimit()` (private, line 72), `flipped()` (private, line 134), `encode()` (private, line 171)


## Gallery

`final class Minn\Media\Gallery` · `public/minn/src/Minn/Media/Gallery.php`

The classic `[gallery]` shortcode's markup. Every gallery on a page is
numbered from one shared per-request counter, and the wrapper names both
that number and the post the gallery sits in, so two galleries on one page
can be styled apart.

The single quotes around the wrapper attributes and the tab-indented item
body are the reference's, and themes have styled against them for long
enough that they are contract rather than formatting.

### static `render(array $items, array $args, int $instance, int $postId, ?string $opening = NULL): string`

The gallery shortcode's HTML for these attachments.

- `@param list<array{icon: string, orientation: string, caption: string, caption_id: string}> $items`
- `@param array{itemtag: string, icontag: string, captiontag: string, columns: int, size: string} $args`

### static `opening(array $args, int $instance, int $postId): string`

The gallery's opening element: its instance id, its post, columns and size as classes.

### static `defaultStyle(int $instance, int $columns): string`

The style block printed before a gallery when use_default_gallery_style
asks for it (themes without HTML5 galleries), as the reference prints
it (probe filter-inputs-3): the items floated in equal whole-percent
columns, a border on the images, the caption flush left.

Internals: `caption()` (private, line 68), `tag()` (private, line 78), `token()` (private, line 85)


## Icons

`final class Minn\Media\Icons` · `public/minn/src/Minn/Media/Icons.php`

The icon a file type is shown with, as wp_mime_type_icon finds it (probe
plugin-helpers): the icon folders (core's wp-includes/images/media, and
any icon_dirs adds) read once a request into a name => address map, an
icon of the asked kind (.png or .svg) preferred where both exist; then
the first name the type answers to: an attachment's file extension and
the kind of file it is, the type, its two halves, the type with an
underscore for the slash. Nothing answering (a post that is not there,
a type that is empty) is the default icon.

- const `CORE` = `array (   0 => 'archive',   1 => 'audio',   2 => 'code',   3 => 'default',   4 => 'document',   5 => 'interactive',   6 => 'spreadsheet',   7 => 'text',   8 => 'video', )` — Core's icons, each kept as .png and .svg.

### static `forType(string|int $mime, string $preferred): string|false`

The icon's address for a type or an attachment id.

Internals: `map()` (private, line 52), `files()` (private, line 71)


## Kind

`final class Minn\Media\Kind` · `public/minn/src/Minn/Media/Kind.php`

Whether an attachment is an image, audio, video, or a given extension, judged by its MIME type first and its file extension second.

- const `IMAGE_EXTENSIONS` = `array (   0 => 'jpg',   1 => 'jpeg',   2 => 'jpe',   3 => 'gif',   4 => 'png',   5 => 'webp',   6 => 'avif',   7 => 'heic', )`
- const `POST_MIME_TYPES` = `array (   'image' =>    array (     0 => 'Images',     1 => 'Manage Images',     2 => 'Image <span class="count">(%s)</span>',     3 => 'Images <span class="count">(%s)</span>',   ),   'audio' =>    array (     0 => 'Audio',     1 => 'Manage Audio',     2 => 'Audio <span class="count">(%s)</span>',     3 => 'Audio <span class="count">(%s)</span>',   ),   'video' =>    array (     0 => 'Video',     1 => 'Manage Video',     2 => 'Video <span class="count">(%s)</span>',     3 => 'Video <span class="count">(%s)</span>',   ),   'application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-word.document.macroEnabled.12,application/vnd.ms-word.template.macroEnabled.12,application/vnd.oasis.opendocument.text,application/vnd.apple.pages,application/pdf,application/vnd.ms-xpsdocument,application/oxps,application/rtf,application/wordperfect,application/octet-stream' =>    array (     0 => 'Documents',     1 => 'Manage Documents',     2 => 'Document <span class="count">(%s)</span>',     3 => 'Documents <span class="count">(%s)</span>',   ),   'application/vnd.apple.numbers,application/vnd.oasis.opendocument.spreadsheet,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel.sheet.macroEnabled.12,application/vnd.ms-excel.sheet.binary.macroEnabled.12' =>    array (     0 => 'Spreadsheets',     1 => 'Manage Spreadsheets',     2 => 'Spreadsheet <span class="count">(%s)</span>',     3 => 'Spreadsheets <span class="count">(%s)</span>',   ),   'application/x-gzip,application/rar,application/x-tar,application/zip,application/x-7z-compressed' =>    array (     0 => 'Archives',     1 => 'Manage Archives',     2 => 'Archive <span class="count">(%s)</span>',     3 => 'Archives <span class="count">(%s)</span>',   ), )` — The media library's filter groups: a MIME pattern (a top-level type, or
a comma list of full types) => its plural label, its manage label, and
the singular and plural count labels.

### static `matchWildcards(array $patterns, array $reals): array`

Real mime types grouped under the wildcard patterns that match them
(image, audio/*, image/jpeg, *), the last pattern first; a pattern with
no matches is omitted (probe post-remaining).

- `@param list<string> $patterns`
- `@param list<string> $reals`
- `@return array<string, list<string>>`

### static `matches(string $type, string $mime, string $extension, array $audioExtensions, array $videoExtensions): bool`

Whether a file is of a media type, by mime prefix or by extension list.


## Metadata

`final class Minn\Media\Metadata` · `public/minn/src/Minn/Media/Metadata.php`

The _wp_attachment_metadata blob: parsed by scanning for the shapes it
holds (top-level dims and file, the sizes map, the image_meta scalars)
and written back in the reference's stored form: a:6 at the top,
image_meta as a:13 with alt last.

- const `IMAGE_META_KEYS` = `array (   0 => 'aperture',   1 => 'credit',   2 => 'camera',   3 => 'caption',   4 => 'created_timestamp',   5 => 'copyright',   6 => 'focal_length',   7 => 'iso',   8 => 'shutter_speed',   9 => 'title',   10 => 'orientation', )`

Used by: `Minn\Admin\SiteController`, `Minn\Blocks\ImageTags`, `Minn\Content\SiteIcon`, `Minn\Media\PhotoMeta`

### static `namesFile(string $location, array $meta): bool`

Whether an image location names the attachment's file, one of its
sizes, or its original (probe media-tags): the location, its query
dropped, must end with the stored path, or with the stored folder and
a size's or the original's file name. A bare file name with no folder
matches nothing.

- `@param array<string, mixed> $meta the attachment metadata as stored`

### static `parse(?string $blob): array`

The attachment metadata blob as an array, tolerant of anything missing.

- `@return array{width: int, height: int, file: string, filesize: int, sizes: array<string, array>, image_meta: ?array}`

### static `blankImageMeta(): array`

The image_meta block a fresh upload carries.


## PhotoMeta

`final class Minn\Media\PhotoMeta` · `public/minn/src/Minn/Media/PhotoMeta.php`

A photo's own description, read as the reference's wp_read_image_metadata
reads it (probe image-meta): IPTC first (headline or object name for the
title, caption, credit or byline, copyright, keywords, the date and time
it was made), then EXIF for what IPTC left empty (a short description as
the title, the user comment or description as the caption, the artist,
copyright, model, the digitized time, aperture, focal length, ISO,
exposure, orientation). Text that is not UTF-8 is read as Latin-1, and
every value is cleaned (the reference runs wp_kses_post) and made a
string; a short caption doubles as the title.

- const `EXIF_TYPES` = `array (   0 => 2,   1 => 7,   2 => 8, )` — EXIF is read for these image types only: JPEG and TIFF, both byte orders.

### static `read(string $file, array $exifTypes, Closure $clean): ?array`

The metadata, the image type, the IPTC and EXIF it came from; null
when there is no such file.

- `@param list<int> $exifTypes`
- `@param Closure(string): string $clean`
- `@return array{meta: array<string, mixed>, type: int|null, iptc: array<string, mixed>, exif: array<string, mixed>}|null`

Internals: `fromIptc()` (private, line 62), `fromExif()` (private, line 91), `described()` (private, line 134), `fraction()` (private, line 151), `exifTime()` (private, line 162), `cleaned()` (private, line 176)


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
or null when every asked dimension is larger than the image (nothing to make).

- `@param Closure(int, int, int, int): array{0: int, 1: int} $constrain scales a box into a maximum`
- `@return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int}|null`

### static `sameSize(array $box, int $origW, int $origH): bool`

Whether a resize box makes the image's own size again, a pixel either
way counting as rounding.

- `@param array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int, 6: int, 7: int} $box`

### static `intermediate(array $meta, array|string $size, ?string $fileUrl, Closure $editorConstrain, Closure $matchesRatio): ?array`

The registered size that serves a request: by name, or for a
requested box the size of exactly that box, else the smallest larger
one in its proportion (the full size never counts), else the
thumbnail when it is wide enough; with its path and URL beside the
file's.

- `@param array<string, mixed> $meta attachment metadata`
- `@param string|array{0: int, 1: int} $size`
- `@param Closure(int, int, array): array{0: int, 1: int} $editorConstrain`
- `@param Closure(int, int, int, int): bool $matchesRatio`
- `@return array<string, mixed>|null`

### static `sources(array $sizeArray, string $src, array $meta, Closure $matchesRatio, string $uploadsUrl, Closure $maxWidth): array`

The srcset candidates by width: every size on the image's ratio (and
the same edit, when the file was edited) no wider than the cap, the
source itself winning a width clash and passing the cap.

- `@param array{0: int, 1: int} $sizeArray`
- `@param Closure(int, int, int, int): bool $matchesRatio`
- `@param Closure(array{0: int, 1: int}): int $maxWidth the widest candidate (max_srcset_image_width; 0 for no cap), asked before the folder is checked`
- `@return array<int, array{url: string, descriptor: string, value: int}>`

### static `srcset(array $sources): ?string`

The srcset attribute for at least two sources; null for fewer.

### static `editorSizes(array $meta, string $baseUrl, string $fullUrl, array $names, Closure $downsize): array`

The image sizes an attachment offers the editor, each with orientation. @param Closure(string): (array|false) $downsize

- `@param Closure(string): (array|false) $downsize`

### static `oldThumbnail(string $imagefile, string $thumb): ?array`

The old thumbnail an image's metadata names (its `thumb`), as a file
beside the image with its size, while it is still there (probe
image-downsize).

- `@return array{0: string, 1: int, 2: int}|null the file's name, width and height`

Internals: `join()` (private, line 241)


## Upload

`final readonly class Minn\Media\Upload` · `public/minn/src/Minn/Media/Upload.php`

One file arriving for the library, on either transport: a multipart
field named "file" (a temporary upload to move) or a raw body whose
Content-Disposition names the file. The parent is read from the same
request, wherever the client put it.

Used by: `Minn\Rest\MediaController`

```php
__construct(string $filename, ?string $movedFrom, ?string $raw, int $parent = 0)
```

- readonly `string $filename`
- readonly `?string $movedFrom`
- readonly `?string $raw`
- readonly `int $parent`

### static `fromRequest(Minn\Http\Request $request): ?self`

Null when the request carries no file.

### static `filenameFrom(array $headers): ?string`

The file a Content-Disposition names, as the reference reads one
(probe rest-attachments-controller): the parameters after the
disposition type, split on semicolons as written; the last non-empty
filename= of all the headers, unquoted when double
quotes wrap it. "filename" is matched as written, and filename* is
not read. Null when none names one.

- `@param list<string> $headers`

### static `refusal(string $data, array $headers): ?Minn\RestError`

Why a raw body cannot be taken, in the reference's order: no data, no
Content-Type, no Content-Disposition, one naming no file, a
Content-MD5 the data does not match. Null when it can. The headers are
the REST request's, keyed by lower-case names with underscores.

- `@param array<string, list<string>> $headers`

### `mime(): ?string`

The mime type the extension maps to, or null for one the library refuses.

### static `parentOf(Minn\Http\Request $request): int`

The post the client wants the file attached to: form field, query, or JSON body.


## Uploads

`final readonly class Minn\Media\Uploads` · `public/minn/src/Minn/Media/Uploads.php`

The uploads directory: paths, URLs, the allowed types, and landing a file.

- const `MIMES` = `array (   'png' => 'image/png',   'jpg' => 'image/jpeg',   'jpeg' => 'image/jpeg',   'gif' => 'image/gif',   'webp' => 'image/webp',   'pdf' => 'application/pdf',   'txt' => 'text/plain',   'mp4' => 'video/mp4',   'mp3' => 'audio/mpeg',   'zip' => 'application/zip', )` — extension => canonical mime

Used by: `Minn\Blocks\ImageTags`, `Minn\Blocks\Renderer`, `Minn\Media\Upload`, `Minn\Media\Writer`, `Minn\Rest\MediaObject`, `Minn\Rest\Services`, `Minn\Runtime\PostEvents`

```php
__construct(Minn\Front\Permalinks $permalinks, string $baseDir)
```


### `baseDir(): string`

The uploads directory on disk.

### `baseUrl(): string`

The uploads directory's URL.

### `urlFor(string $relativePath): string`

The URL of a file by its relative path.

### `pathFor(string $relativePath): string`

The path of a file by its relative path.

### static `sanitizeName(string $filename): string`

A safe file name: one extension. Every dot-separated part between the
base and the last extension that is not itself an allowed extension
is joined with an underscore, so "shell.php.png" lands as
"shell_php.png" and no handler can be talked into running it.

### `relativeOf(string $absolutePath): ?string`

A stored file's path relative to the uploads folder, or null for a file outside it.

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


## WebpInfo

`final class Minn\Media\WebpInfo` · `public/minn/src/Minn/Media/WebpInfo.php`

A WebP file's size and kind from its first 40 bytes, as wp_get_webp_info
reads them (probe plugin-queue8): a simple lossy frame (VP8), a lossless
one (VP8L), or the extended form (VP8X), which the reference names
animated-alpha whatever its flags say. Anything shorter or of another
chunk is unknown.

### static `read(string $head): array`

The width, height and kind a file's first bytes declare. @return array{width: int|false, height: int|false, type: string|false}

- `@return array{width: int|false, height: int|false, type: string|false}`


## Writer

`final readonly class Minn\Media\Writer` · `public/minn/src/Minn/Media/Writer.php`

Where the media library's files live, as a REST upload names them: a
stored file's address, its path on disk, and its path relative to the
uploads folder. The attachment itself is written through the runtime
(wp_handle_upload, wp_insert_attachment, wp_generate_attachment_metadata),
as the reference writes it.

Used by: `Minn\Rest\MediaController`, `Minn\Rest\Services`

```php
__construct(Minn\Media\Uploads $uploads)
```


### `urlOf(string $relative): string`

A stored file's address.

### `pathOf(string $relative): string`

A stored file's path on disk.

### `relativeOf(string $absolutePath): ?string`

A stored file's path relative to the uploads folder, or null for one outside it.

