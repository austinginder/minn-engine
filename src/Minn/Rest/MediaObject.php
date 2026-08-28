<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\Blocks;
use Minn\Content\Posts;
use Minn\Content\Slug;
use Minn\Content\Texturize;
use Minn\Front\Permalinks;
use Minn\Media\Metadata;
use Minn\Media\Uploads;
use Minn\Support\Html;

/** The wp/v2 media object, view and edit context. */
final readonly class MediaObject
{
    public function __construct(
        private Posts $posts,
        private Uploads $uploads,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    public function url(): RestUrl
    {
        return $this->url;
    }

    public function build(array $p, bool $edit): array
    {
        $id = (int) $p['ID'];
        $meta = Metadata::parse($this->posts->meta($id, '_wp_attachment_metadata'));
        $file = $this->posts->meta($id, '_wp_attached_file') ?? '';
        $alt = $this->posts->meta($id, '_wp_attachment_image_alt') ?? '';
        $fullUrl = $file === '' ? '' : $this->uploads->urlFor($file);
        $isImage = str_starts_with((string) $p['post_mime_type'], 'image/');
        $dual = static fn (string $raw, string $rendered) => $edit ? ['raw' => $raw, 'rendered' => $rendered] : ['rendered' => $rendered];
        $caption = $p['post_excerpt'] === '' ? '' : Blocks::paragraphs((string) $p['post_excerpt']);

        $object = [
            'id' => $id,
            'date' => PostObject::date((string) $p['post_date']),
            'date_gmt' => PostObject::date((string) $p['post_date_gmt']),
            'guid' => $edit ? ['rendered' => $p['guid'], 'raw' => $p['guid']] : ['rendered' => $p['guid']],
            'modified' => PostObject::date((string) $p['post_modified']),
            'modified_gmt' => PostObject::date((string) $p['post_modified_gmt']),
            'slug' => $p['post_name'],
            'status' => $p['post_status'],
            'type' => 'attachment',
            'link' => $this->permalinks->forAttachment($p),
            'title' => $dual((string) $p['post_title'], Texturize::html((string) $p['post_title'])),
            'author' => (int) $p['post_author'],
            'featured_media' => 0,
            'comment_status' => $p['comment_status'],
            'ping_status' => $p['ping_status'],
            'template' => '',
            'meta' => [],
        ];
        if ($edit) {
            $object['permalink_template'] = $this->url->home('/?attachment_id=' . $id);
            $object['generated_slug'] = Slug::sanitize((string) $p['post_title']);
        }
        $object['class_list'] = ['post-' . $id, 'attachment', 'type-attachment', 'status-' . $p['post_status'], 'hentry'];
        $object['minn_attached_to'] = null;
        $object['description'] = $dual((string) $p['post_content'], $isImage ? $this->descriptionHtml($meta, $fullUrl, $alt) : '');
        $object['caption'] = $dual((string) $p['post_excerpt'], $caption);
        $object['alt_text'] = $alt;
        $object['media_type'] = $isImage ? 'image' : 'file';
        $object['mime_type'] = $p['post_mime_type'];
        $object['media_details'] = $this->details($meta, (string) $p['post_mime_type']);
        $object['post'] = (int) $p['post_parent'] > 0 ? (int) $p['post_parent'] : null;
        $object['source_url'] = $fullUrl;
        $object['filename'] = basename($file);
        $path = $this->uploads->pathFor($file);
        $object['filesize'] = $meta['filesize'] ?: ($file !== '' && is_file($path) ? (int) filesize($path) : 0);
        if ($edit) {
            $object['missing_image_sizes'] = [];
            // The plugin's image-editor facts ride edit context.
            $object['image_quality'] = ['default' => 82, 'sizes' => []];
            $object['exif_orientation'] = 1;
            $object['image_save_progressive'] = false;
            $object['image_output_format'] = null;
        }

        $canEdit = $this->caller->can('edit_post', $id);
        $links = [
            'self' => [[
                'href' => $this->url->to('/wp/v2/media/' . $id),
                'targetHints' => ['allow' => $canEdit ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET']],
            ]],
            'collection' => [['href' => $this->url->to('/wp/v2/media')]],
            'about' => [['href' => $this->url->to('/wp/v2/types/attachment')]],
        ];
        if ((int) $p['post_author'] > 0) {
            $links['author'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/users/' . (int) $p['post_author'])]];
        }
        $links['replies'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/comments', ['post' => $id])]];
        if ($edit && $canEdit) {
            $links['wp:action-unfiltered-html'] = [['href' => $this->url->to('/wp/v2/media/' . $id)]];
            if ($this->caller->can('edit_others_posts')) {
                $links['wp:action-assign-author'] = [['href' => $this->url->to('/wp/v2/media/' . $id)]];
            }
            $links['curies'] = RestUrl::curies();
        }
        $object['_links'] = $links;
        return $object;
    }

    /** media_details: dims, file, filesize, and the sizes with URLs. */
    private function details(array $meta, string $mime): array
    {
        if ($meta['file'] === '') {
            // Non-image attachments carry no parsed metadata yet (recorded gap).
            return [];
        }
        $baseUrl = $this->uploads->baseUrl() . '/' . dirname($meta['file']);
        $sizes = [];
        foreach ($meta['sizes'] as $name => $size) {
            $sizes[$name] = ['file' => $size['file'], 'width' => $size['width'], 'height' => $size['height']]
                + (isset($size['filesize']) ? ['filesize' => $size['filesize']] : [])
                + ['mime_type' => $size['mime-type'], 'source_url' => $baseUrl . '/' . $size['file']];
        }
        if ($meta['width']) {
            $sizes['full'] = [
                'file' => basename($meta['file']),
                'width' => $meta['width'],
                'height' => $meta['height'],
                'mime_type' => $mime,
                'source_url' => $this->uploads->urlFor($meta['file']),
            ];
        }
        $details = ['width' => $meta['width'], 'height' => $meta['height'], 'file' => $meta['file']];
        if ($meta['filesize']) {
            $details['filesize'] = $meta['filesize'];
        }
        $details['sizes'] = $sizes;
        if ($meta['image_meta'] !== null) {
            $details['image_meta'] = $meta['image_meta'];
        }
        return $details;
    }

    /** The attachment-page image HTML that description.rendered carries. */
    private function descriptionHtml(array $meta, string $fullUrl, string $alt): string
    {
        if (empty($meta['sizes']['medium'])) {
            return '';
        }
        $base = dirname($fullUrl);
        $medium = $meta['sizes']['medium'];
        $ratio = $meta['width'] > 0 ? $meta['height'] / $meta['width'] : 0;
        // srcset: same-ratio sizes in stored order, then the full image.
        $srcset = [];
        foreach ($meta['sizes'] as $size) {
            if ($size['width'] < 1 || abs($size['height'] - $size['width'] * $ratio) > 1) {
                continue;
            }
            $srcset[$size['width']] = $base . '/' . $size['file'] . ' ' . $size['width'] . 'w';
        }
        $srcset[$meta['width']] = $fullUrl . ' ' . $meta['width'] . 'w';
        $img = '<img loading="lazy" decoding="async" width="' . $medium['width'] . '" height="' . $medium['height'] . '"'
            . ' src="' . $base . '/' . $medium['file'] . '" class="attachment-medium size-medium" alt="' . Html::esc($alt) . '"'
            . ' srcset="' . implode(', ', $srcset) . '"'
            . ' sizes="auto, (max-width: ' . $medium['width'] . 'px) 100vw, ' . $medium['width'] . 'px" />';
        return "<p class=\"attachment\"><a href='" . $fullUrl . "'>" . $img . '</a></p>' . "\n";
    }
}
