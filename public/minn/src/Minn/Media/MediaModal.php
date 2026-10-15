<?php

declare(strict_types=1);

namespace Minn\Media;

use Minn\Http\Request;
use Minn\I18n\Gettext;
use Minn\Runtime\Assets;
use Minn\Runtime\Handoff;
use Minn\Runtime\RequestHeaders;
use Minn\Runtime\Runtime;
use Minn\Runtime\SiteUrls;
use Minn\Support\Locale;

/**
 * WordPress's media modal, set up for a page that opens it (wp_enqueue_media):
 * a page builder's editor picks and uploads images through it. Its scripts,
 * styles, words and templates are WordPress's and come with the Minn Legacy
 * Pack. The engine queues them and tells them about the site, as the
 * reference does and through the same filters: the post being edited, the
 * file types and the months the library holds, the nonces, the upload
 * settings, and the templates printed in the footer. Without the pack's
 * scripts there is no modal to set up and nothing happens.
 */
final class MediaModal
{
    /**
     * Queues the modal for the page, once a request.
     *
     * @param array<string, mixed> $args 'post': the post being edited, an ID or a post object
     */
    public static function enqueue(array $args, Assets $scripts, Assets $styles): void
    {
        $hooks = Runtime::hooks();
        if (!$scripts->registered('media-views') || $hooks->actionsDone('wp_enqueue_media') > 0) {
            return;
        }
        $postId = self::postId($args['post'] ?? null);
        $post = $postId > 0 ? Handoff::postInstance($postId) : null;
        $audio = $hooks->filter('media_library_show_audio_playlist', [true]);
        $video = $hooks->filter('media_library_show_video_playlist', [true]);
        $months = $hooks->filter('media_library_months_with_files', [null]);
        [$embedExts, $embedMimes] = self::embeds();
        $settings = [
            'tabs' => self::tabs(),
            'tabUrl' => SiteUrls::admin('media-upload.php?chromeless=1'),
            'mimeTypes' => self::mimeTypes(),
            'captions' => !$hooks->filter('disable_captions', ['']),
            'nonce' => [
                'sendToEditor' => Handoff::createNonce('media-send-to-editor'),
                'setAttachmentThumbnail' => Handoff::createNonce('set-attachment-thumbnail'),
            ],
            'post' => ['id' => 0],
            'defaultProps' => [
                'link' => (string) Runtime::options()->filtered('image_default_link_type'),
                'align' => (string) Runtime::options()->filtered('image_default_align'),
                'size' => (string) Runtime::options()->filtered('image_default_size'),
            ],
            'attachmentCounts' => [
                'audio' => self::shows($audio, 'audio') ? 1 : 0,
                'video' => self::shows($video, 'video') ? 1 : 0,
            ],
            'oEmbedProxyUrl' => (string) $hooks->filter('rest_url', [SiteUrls::home('wp-json/oembed/1.0/proxy'), '/oembed/1.0/proxy', null, 'rest']),
            'embedExts' => $embedExts,
            'embedMimes' => $embedMimes,
            'contentWidth' => $GLOBALS['content_width'] ?? null,
            'months' => is_array($months) ? $months : self::months(),
            'mediaTrash' => \defined('MEDIA_TRASH') && \constant('MEDIA_TRASH') ? 1 : 0,
            'infiniteScrolling' => $hooks->filter('media_library_infinite_scrolling', [true]) ? 1 : 0,
        ];
        if ($postId > 0) {
            $settings['post'] = ['id' => $postId, 'nonce' => Handoff::createNonce('update-post_' . $postId)];
            $featured = Runtime::current()->db->value('SELECT meta_value FROM ' . Runtime::current()->db->table('postmeta') . ' WHERE post_id = ? AND meta_key = ? LIMIT 1', [$postId, '_thumbnail_id']);
            $settings['post']['featuredImageId'] = $featured !== null && (int) $featured > 0 ? (int) $featured : -1;
        }
        $settings = $hooks->filter('media_view_settings', [$settings, $post]);
        $strings = (array) $hooks->filter('media_view_strings', [[], $post]);
        $strings['settings'] = $settings;
        $scripts->localize('media-views', '_wpMediaViewsL10n', $strings);
        $scripts->enqueue('media-editor');
        $scripts->enqueue('media-audiovideo');
        $styles->enqueue('media-views');
        $styles->enqueue('imgareaselect');
        self::uploadSettings($scripts);
        if (is_callable('wp_print_media_templates')) {
            foreach (['admin_footer', 'wp_footer', 'customize_controls_print_footer_scripts'] as $footer) {
                $hooks->add($footer, 'wp_print_media_templates');
            }
        }
        $hooks->action('wp_enqueue_media', []);
    }

    /**
     * The upload settings the modal's uploader reads (wp_plupload_default_settings):
     * where files go, how large, which types, with which nonce, added to the
     * data the uploader's script already carries.
     */
    public static function uploadSettings(Assets $scripts): void
    {
        $hooks = Runtime::hooks();
        $extensions = [];
        foreach (array_keys((array) FileTypes::allowed()) as $pattern) {
            array_push($extensions, ...explode('|', (string) $pattern));
        }
        $defaults = $hooks->filter('plupload_default_settings', [[
            'file_data_name' => 'async-upload',
            'url' => SiteUrls::admin('async-upload.php'),
            'filters' => [
                'max_file_size' => self::maxUploadSize() . 'b',
                'mime_types' => [['extensions' => implode(',', $extensions)]],
            ],
        ]]);
        $params = (array) $hooks->filter('plupload_default_params', [['action' => 'upload-attachment']]);
        $params['_wpnonce'] = Handoff::createNonce('media-form');
        $defaults['multipart_params'] = $params;
        $settings = [
            'defaults' => $defaults,
            'browser' => ['mobile' => RequestHeaders::isMobile(), 'supported' => self::canUpload()],
            'limitExceeded' => false,
        ];
        $script = 'var _wpPluploadSettings = ' . \Minn\Json::encode($settings) . ';';
        $data = $scripts->data('wp-plupload', 'data');
        $scripts->addData('wp-plupload', 'data', is_string($data) && $data !== '' ? $data . "\n" . $script : $script);
    }

    private static function postId(mixed $post): int
    {
        if (is_object($post) && isset($post->ID)) {
            return (int) $post->ID;
        }
        return is_numeric($post) ? (int) $post : 0;
    }

    /** The upload tabs plugins add; WordPress's own four are the modal's views, not tabs. @return array<string, string> */
    private static function tabs(): array
    {
        $tabs = (array) Runtime::hooks()->filter('media_upload_tabs', [[
            'type' => Gettext::text('From Computer'),
            'type_url' => Gettext::text('From URL'),
            'gallery' => Gettext::text('Gallery'),
            'library' => Gettext::text('Media Library'),
        ]]);
        unset($tabs['type'], $tabs['type_url'], $tabs['gallery'], $tabs['library']);
        return $tabs;
    }

    /** The library's file-type filter: each kind's label, by its MIME pattern (get_post_mime_types). @return array<string, string> */
    private static function mimeTypes(): array
    {
        $types = [];
        foreach (Kind::POST_MIME_TYPES as $pattern => [$label, $manage, $singular, $plural]) {
            $types[$pattern] = [Gettext::text($label), Gettext::text($manage), ['singular' => $singular, 'plural' => $plural, 'context' => null, 'domain' => null, 0 => $singular, 1 => $plural]];
        }
        $out = [];
        foreach ((array) Runtime::hooks()->filter('post_mime_types', [$types]) as $pattern => $row) {
            $out[$pattern] = is_array($row) ? $row[0] ?? '' : $row;
        }
        return $out;
    }

    /** Whether the library offers audio or video playlists: a plugin's answer, or whether it holds any such file. */
    private static function shows(mixed $answer, string $kind): bool
    {
        if ($answer !== null) {
            return (bool) $answer;
        }
        $db = Runtime::current()->db;
        return $db->value('SELECT ID FROM ' . $db->table('posts') . " WHERE post_type = 'attachment' AND post_mime_type LIKE ? LIMIT 1", [$kind . '%']) !== null;
    }

    /** The months the library has files from, newest first, as the modal's date filter lists them. @return list<array{year: string, month: string, text: string}> */
    private static function months(): array
    {
        $db = Runtime::current()->db;
        $rows = $db->rows('SELECT DISTINCT YEAR(post_date) AS year, MONTH(post_date) AS month FROM ' . $db->table('posts') . " WHERE post_type = 'attachment' ORDER BY post_date DESC");
        $out = [];
        foreach ($rows as $row) {
            $month = (int) $row['month'];
            $out[] = [
                'year' => (string) $row['year'],
                'month' => (string) $month,
                'text' => sprintf(Gettext::text('%1$s %2$d'), Gettext::text(Locale::MONTHS[$month - 1] ?? ''), (int) $row['year']),
            ];
        }
        return $out;
    }

    /** The audio and video extensions the modal embeds, and the MIME type of each. @return array{list<string>, array<string, string>} */
    private static function embeds(): array
    {
        $exts = array_values(array_merge((array) \Minn\Runtime\Attachments::audioExtensions(), (array) \Minn\Runtime\Attachments::videoExtensions()));
        $mimes = [];
        foreach ((array) FileTypes::all() as $pattern => $mime) {
            foreach (explode('|', (string) $pattern) as $ext) {
                if (in_array($ext, $exts, true) && !isset($mimes[$ext])) {
                    $mimes[$ext] = (string) $mime;
                }
            }
        }
        $ordered = [];
        foreach ($exts as $ext) {
            if (isset($mimes[$ext])) {
                $ordered[$ext] = $mimes[$ext];
            }
        }
        return [$exts, $ordered];
    }

    /** The largest upload the server takes, the smaller of PHP's two limits, as plugins leave it (upload_size_limit). */
    private static function maxUploadSize(): int
    {
        $upload = self::bytes((string) ini_get('upload_max_filesize'));
        $post = self::bytes((string) ini_get('post_max_size'));
        return (int) Runtime::hooks()->filter('upload_size_limit', [min($upload, $post), $upload, $post]);
    }

    private static function bytes(string $value): int
    {
        $value = strtolower(trim($value));
        $bytes = (int) $value;
        if (str_contains($value, 'g')) {
            $bytes *= 1024 ** 3;
        } elseif (str_contains($value, 'm')) {
            $bytes *= 1024 ** 2;
        } elseif (str_contains($value, 'k')) {
            $bytes *= 1024;
        }
        return min($bytes, PHP_INT_MAX);
    }

    /** Whether the browser can upload: every desktop, and iOS from 6. */
    private static function canUpload(): bool
    {
        if (!RequestHeaders::isMobile()) {
            return true;
        }
        $agent = (string) (Request::serverVariable('HTTP_USER_AGENT') ?? '');
        if (str_contains($agent, 'iPhone') || str_contains($agent, 'iPad') || str_contains($agent, 'iPod')) {
            return preg_match('#OS ([\d_]+) like Mac OS X#', $agent, $version) === 1 && version_compare($version[1], '6', '>=');
        }
        return true;
    }
}
