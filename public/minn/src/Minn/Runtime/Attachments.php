<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Content\Comments;
use Minn\Content\PostWriter;
use Minn\Content\Posts;
use Minn\Media\FileTypes;
use Minn\Media\Kind;
use Minn\Media\Metadata as StoredMetadata;
use Minn\Media\PhotoMeta;
use Minn\Media\Uploads;
use Minn\Support\Lists;

/**
 * Attachments as WordPress's media functions keep them: the uploads folder
 * and its address, an attachment's file (stored relative to the folder),
 * its address and metadata, and what kind of file it is; each as its
 * filter leaves it.
 */
final class Attachments
{
    /**
     * The uploads folder and its address for a time, the month's folder too
     * when uploads go by month (_wp_upload_dir, wp_get_upload_dir), as
     * upload_dir leaves them.
     *
     * @return array<string, mixed>
     */
    public static function uploadDir(mixed $time = null): mixed
    {
        $options = Runtime::options();
        $layout = Uploads::layout((string) $options->filtered('upload_path'), (string) $options->filtered('upload_url_path'), (string) $options->filtered('siteurl'), WP_CONTENT_DIR, WP_CONTENT_URL, ABSPATH, (bool) $options->filtered('uploads_use_yearmonth_folders'), (string) ($time ?? SiteTime::now('mysql')));
        return Runtime::hooks()->filter('upload_dir', [$layout]);
    }

    /** The uploads folder made on disk when asked (wp_upload_dir), and returned. */
    public static function uploadDirMade(mixed $time = null, mixed $createDir = true, mixed $refreshCache = false): mixed
    {
        $dir = self::uploadDir();
        if ($createDir && $dir['error'] === false) {
            self::makeDir($dir['path']);
        }
        return $dir;
    }

    /** A folder made with its parents (wp_mkdir_p): whether it is there and may be written to. */
    public static function makeDir(mixed $target): bool
    {
        $target = rtrim(str_replace('//', '/', (string) $target), '/');
        if ($target === '') {
            $target = '/';
        }
        if (is_dir($target)) {
            return @is_writable($target);
        }
        if (@mkdir($target, 0777, true)) {
            return true;
        }
        return is_dir($target);
    }

    /** A path under the uploads folder made relative to it (_wp_relative_upload_path), as _wp_relative_upload_path leaves it. */
    public static function relativePath(mixed $path): mixed
    {
        $relative = (string) $path;
        $base = self::uploadDir()['basedir'];
        if (str_starts_with($relative, $base)) {
            $relative = ltrim(str_replace($base, '', $relative), '/');
        }
        return Runtime::hooks()->filter('_wp_relative_upload_path', [$relative, $path]);
    }

    /** An attachment's file on disk (get_attached_file), the uploads folder before a relative one; through get_attached_file unless asked not to be. */
    public static function file(mixed $attachmentId, mixed $unfiltered = false): mixed
    {
        $file = (string) MetaData::get('post', (int) $attachmentId, '_wp_attached_file', true);
        if ($file !== '' && !str_starts_with($file, '/') && !preg_match('|^.:\\\\|', $file)) {
            $file = self::uploadDir()['basedir'] . '/' . $file;
        }
        return $unfiltered ? $file : Runtime::hooks()->filter('get_attached_file', [$file, (int) $attachmentId]);
    }

    /** An attachment's file recorded (update_attached_file), relative to the uploads folder; an empty one removes the record. */
    public static function setFile(mixed $attachmentId, mixed $file): mixed
    {
        $post = Records::post((int) $attachmentId);
        if ($post === null) {
            return false;
        }
        $file = Runtime::hooks()->filter('update_attached_file', [(string) $file, $post->ID]);
        $file = self::relativePath($file);
        if ($file !== '') {
            return MetaData::updatePost($post->ID, '_wp_attached_file', $file);
        }
        return MetaData::delete('post', $post->ID, '_wp_attached_file');
    }

    /**
     * An attachment's address (wp_get_attachment_url): its file under the
     * uploads address, else its guid; false for no attachment or no address.
     */
    public static function url(mixed $attachmentId = 0): mixed
    {
        $post = Records::post((int) $attachmentId);
        if ($post === null || $post->post_type !== 'attachment') {
            return false;
        }
        $file = (string) MetaData::get('post', $post->ID, '_wp_attached_file', true);
        $url = '';
        if ($file !== '') {
            $uploads = self::uploadDir();
            if (str_starts_with($file, $uploads['basedir'])) {
                $url = str_replace($uploads['basedir'], $uploads['baseurl'], $file);
            } elseif (str_contains($file, 'wp-content/uploads')) {
                $url = rtrim($uploads['baseurl'] . '/' . (int) CurrentUser::id(), '/\\') . '/../' . ltrim(substr($file, strpos($file, 'wp-content/uploads') + 18), '/');
            } else {
                $url = $uploads['baseurl'] . '/' . ltrim($file, '/');
            }
        }
        if ($url === '') {
            $url = $post->guid;
        }
        $url = Runtime::hooks()->filter('wp_get_attachment_url', [$url, $post->ID]);
        return $url === '' ? false : $url;
    }

    /**
     * An attachment's metadata (wp_get_attachment_metadata), the post being
     * read's for none; one stored in a shape the meta reader refuses is
     * parsed from its row; false for none; through
     * wp_get_attachment_metadata unless asked not to be.
     */
    public static function metadata(mixed $attachmentId = 0, mixed $unfiltered = false): mixed
    {
        $attachmentId = (int) $attachmentId ?: PostTags::id();
        $post = Records::post($attachmentId);
        if ($post === null) {
            return false;
        }
        $data = MetaData::get('post', $post->ID, '_wp_attachment_metadata', true);
        if (!is_array($data)) {
            $blob = (new Posts(Runtime::current()->db))->meta($post->ID, '_wp_attachment_metadata');
            $data = $blob === null ? false : StoredMetadata::parse($blob);
        }
        if ($data === false || $data === '') {
            return false;
        }
        return $unfiltered ? $data : Runtime::hooks()->filter('wp_get_attachment_metadata', [$data, $post->ID]);
    }

    /** An attachment's metadata stored (wp_update_attachment_metadata) as wp_update_attachment_metadata leaves it; an empty one removed. */
    public static function setMetadata(mixed $attachmentId, mixed $data): mixed
    {
        $post = Records::post((int) $attachmentId);
        if ($post === null) {
            return false;
        }
        $data = Runtime::hooks()->filter('wp_update_attachment_metadata', [$data, $post->ID]);
        if ($data) {
            return MetaData::updatePost($post->ID, '_wp_attachment_metadata', $data);
        }
        return MetaData::delete('post', $post->ID, '_wp_attachment_metadata');
    }

    /** Whether an attachment is a kind of file (wp_attachment_is): image, audio, video, or a MIME type or extension. */
    public static function is(mixed $type, mixed $post = null): bool
    {
        $post = Records::post($post);
        $file = $post === null ? '' : (string) self::file($post->ID);
        if ($file === '') {
            return false;
        }
        $check = FileTypes::check($file);
        return Kind::matches((string) $type, (string) $post->post_mime_type, (string) ($check['ext'] ?: ''), self::audioExtensions(), self::videoExtensions());
    }

    /** Whether an attachment is an image (wp_attachment_is_image). */
    public static function isImage(mixed $post = null): bool
    {
        return self::is('image', $post);
    }

    /** The audio file extensions the player takes (wp_get_audio_extensions), as wp_audio_extensions leaves them. */
    public static function audioExtensions(): mixed
    {
        return Runtime::hooks()->filter('wp_audio_extensions', [['mp3', 'ogg', 'flac', 'm4a', 'wav']]);
    }

    /** The video file extensions the player takes (wp_get_video_extensions), as wp_video_extensions leaves them. */
    public static function videoExtensions(): mixed
    {
        return Runtime::hooks()->filter('wp_video_extensions', [['mp4', 'm4v', 'webm', 'ogv', 'flv']]);
    }

    /**
     * An attachment stored (wp_insert_attachment): the arguments over a file
     * and no parent, the parent given, saved as a post of type attachment.
     */
    public static function insert(mixed $args, mixed $file = false, mixed $parentPostId = 0, mixed $wpError = false, mixed $fireAfterHooks = true): mixed
    {
        $data = Lists::args($args, ['file' => $file, 'post_parent' => 0]);
        if (!empty($parentPostId)) {
            $data = array_replace($data, ['post_parent' => (int) $parentPostId]);
        }
        $data = array_replace($data, ['post_type' => 'attachment']);
        return PostWrites::insert($data, $wpError, $fireAfterHooks);
    }

    /**
     * An attachment deleted (wp_delete_attachment): to the trash first
     * unless forced or the media trash is off; unless pre_delete_attachment
     * answers, its terms, comments and meta removed, the row between
     * delete_post and deleted_post, then its files. The post, or null.
     */
    public static function delete(mixed $postId, mixed $force = false): mixed
    {
        $post = Records::post((int) $postId);
        if ($post === null || $post->post_type !== 'attachment') {
            return $post;
        }
        if (!$force && EMPTY_TRASH_DAYS && MEDIA_TRASH && $post->post_status !== 'trash') {
            return PostWrites::trash($post->ID);
        }
        $hooks = Runtime::hooks();
        $check = $hooks->filter('pre_delete_attachment', [null, $post, $force]);
        if ($check !== null) {
            return $check;
        }
        $meta = self::metadata($post->ID);
        $backupSizes = MetaData::get('post', $post->ID, '_wp_attachment_backup_sizes', true);
        $file = self::file($post->ID);
        $hooks->action('delete_attachment', [$post->ID, $post]);
        TermWrites::deleteObjectRelationships($post->ID, Registered::objectTaxonomies($post->post_type));
        foreach ((new Comments(Runtime::current()->db))->idsOf($post->ID) as $commentId) {
            CommentWrites::delete($commentId, true);
        }
        PostWrites::deleteMeta($post->ID);
        $hooks->action('delete_post', [$post->ID, $post]);
        $db = Runtime::current()->db;
        (new PostWriter($db, new Posts($db), Runtime::current()->site))->destroy($post->ID);
        $hooks->action('deleted_post', [$post->ID, $post]);
        PostCaches::clean($post);
        self::deleteFiles($post->ID, $meta, $backupSizes, $file);
        return $post;
    }

    /** An attachment's files deleted, its sizes and backups too (wp_delete_attachment_files); false when any was missing or outside the uploads folder. */
    public static function deleteFiles(mixed $postId, mixed $meta, mixed $backupSizes, mixed $file): bool
    {
        $deleted = true;
        $base = self::uploadDir()['basedir'];
        foreach (Uploads::attachmentFiles((string) $file, is_array($meta) ? $meta : [], is_array($backupSizes) ? $backupSizes : null) as $path) {
            $deleted = FileNames::deleteFromDirectory($path, $base) && $deleted;
        }
        return $deleted;
    }

    /**
     * A photo's own description from its IPTC and EXIF (wp_read_image_metadata),
     * through wp_read_image_metadata_types and wp_read_image_metadata; false
     * when there is no such file.
     */
    public static function readImageMetadata(mixed $file): mixed
    {
        if (!file_exists((string) $file)) {
            return false;
        }
        $hooks = Runtime::hooks();
        $types = (array) $hooks->filter('wp_read_image_metadata_types', [PhotoMeta::EXIF_TYPES]);
        $read = PhotoMeta::read((string) $file, $types, static fn (string $text): string => KsesRules::post($text));
        if ($read === null) {
            return false;
        }
        return $hooks->filter('wp_read_image_metadata', [$read['meta'], $file, $read['type'], $read['iptc'], $read['exif']]);
    }
}
