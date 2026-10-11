<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Content\Texturize;
use Minn\Front\Permalinks;
use Minn\Media\Uploads;
use Minn\Support\Serialized;

/** The wp/v2 media object, view and edit context. */
final readonly class MediaObject
{
    public function __construct(
        private Posts $posts,
        private Uploads $uploads,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Caller $caller,
        private Types $types,
    ) {
    }


    /** An attachment in the context asked for, through rest_prepare_attachment when a plugin hooks it. */
    public function build(PostRecord $p, Context $context): array
    {
        return RuntimePrepare::item('rest_prepare_attachment', $this->buildFields($p, $context), static fn () => \get_post($p->id));
    }

    /** The REST URL builder. */
    public function url(): RestUrl
    {
        return $this->url;
    }

    /** The wp/v2 media shape, with the edit-context fields when asked. */
    private function buildFields(PostRecord $p, Context $context): array
    {
        $edit = $context->isEdit();
        $id = $p->id;
        $meta = Serialized::decode((string) ($this->posts->meta($id, '_wp_attachment_metadata') ?? ''));
        $meta = is_array($meta) ? $meta : null;
        $file = $this->posts->meta($id, '_wp_attached_file') ?? '';
        $alt = $this->posts->meta($id, '_wp_attachment_image_alt') ?? '';
        $fullUrl = $file === '' ? '' : $this->uploads->urlFor($file);
        $isImage = str_starts_with($p->mimeType, 'image/');
        $dual = static fn (string $raw, string $rendered) => $edit ? ['raw' => $raw, 'rendered' => $rendered] : ['rendered' => $rendered];

        $object = [
            'id' => $id,
            'date' => PostObject::date($p->date),
            'date_gmt' => PostObject::date($p->dateGmt),
            'guid' => $edit ? ['rendered' => $p->guid, 'raw' => $p->guid] : ['rendered' => $p->guid],
            'modified' => PostObject::date($p->modified),
            'modified_gmt' => PostObject::date($p->modifiedGmt),
            'slug' => $p->slug,
            'status' => $p->status,
            'type' => 'attachment',
            'link' => $this->permalinks->forAttachment($p),
            'title' => $dual($p->title, Texturize::html($p->title)),
            'author' => $p->authorId,
            'featured_media' => (int) ($this->posts->meta($id, '_thumbnail_id') ?? 0),
            'comment_status' => $p->commentStatus,
            'ping_status' => $p->pingStatus,
            'template' => '',
            'meta' => RestMeta::read('post', $id, 'attachment', $context->value),
        ];
        if ($edit) {
            [$object['permalink_template'], $object['generated_slug']] = \get_sample_permalink($id, $p->title, '');
        }
        $object['class_list'] = RenderedFields::classes(['post-' . $id, 'attachment', 'type-attachment', 'status-' . $p->status, 'hentry'], $id);
        $object['minn_attached_to'] = null;
        // Rendered as the reference renders them, the attachment as the global post: the_content (where prepend_attachment shows the image or the player) and the excerpt.
        $object['description'] = $dual($p->content, RenderedFields::content($p));
        $object['caption'] = $dual($p->excerpt, RenderedFields::excerpt($p));
        $object['alt_text'] = $alt;
        $object['media_type'] = $isImage ? 'image' : 'file';
        $object['mime_type'] = $p->mimeType;
        $object['media_details'] = $meta === null ? new \stdClass() : $this->details($meta, $p->mimeType, $file);
        $object['post'] = $p->parentId > 0 ? $p->parentId : null;
        $object['source_url'] = $fullUrl;
        if ($edit) {
            // The registered sizes the image lacks, as the runtime counts them (wp_get_missing_image_subsizes).
            $object['missing_image_sizes'] = $isImage && \function_exists('_minn_missing_image_size_names') ? \_minn_missing_image_size_names($id) : [];
        }
        // The original image's name when the image keeps one beside it.
        $object['filename'] = (string) ($meta['original_image'] ?? '') !== '' ? basename((string) $meta['original_image']) : basename($file);
        $path = $this->uploads->pathFor($file);
        $object['filesize'] = (int) ($meta['filesize'] ?? 0) ?: ($file !== '' && is_file($path) ? (int) filesize($path) : 0);
        if ($edit && $isImage) {
            // The image editor's facts ride an image's edit context.
            $object['exif_orientation'] = 1;
            $object['image_output_format'] = null;
            $object['image_save_progressive'] = false;
            $object['image_quality'] = ['default' => 82, 'sizes' => []];
        }

        $object['_links'] = $this->links($p, $context);
        return $object;
    }

    /**
     * The attachment's links in a context: itself (with the methods the
     * caller may use), its collection and type, its author, replies, the
     * post it is attached to, and in the edit context the actions the
     * caller may take; curies when any rel is the API's own.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function links(PostRecord $p, Context $context): array
    {
        $id = $p->id;
        $edit = $context->isEdit();
        $canEdit = $this->caller->can('edit_post', $id);
        $links = [
            'self' => [[
                'href' => $this->url->to('/wp/v2/media/' . $id),
                'targetHints' => ['allow' => $canEdit ? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] : ['GET']],
            ]],
            'collection' => [['href' => $this->url->to('/wp/v2/media')]],
            'about' => [['href' => $this->url->to('/wp/v2/types/attachment')]],
        ];
        if ($p->authorId > 0) {
            $links['author'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/users/' . $p->authorId)]];
        }
        $links['replies'] = [['embeddable' => true, 'href' => $this->url->to('/wp/v2/comments', ['post' => $id])]];
        $parent = $p->parentId > 0 ? $this->posts->find($p->parentId) : null;
        if ($parent !== null) {
            $links['wp:attached-to'] = [['embeddable' => true, 'post_type' => $parent->type, 'id' => $parent->id, 'href' => $this->url->to('/wp/v2/' . $this->types->restBase($parent->type) . '/' . $parent->id)]];
        }
        if ($edit && $canEdit) {
            $links['wp:action-unfiltered-html'] = [['href' => $this->url->to('/wp/v2/media/' . $id)]];
            if ($this->caller->can('edit_others_posts')) {
                $links['wp:action-assign-author'] = [['href' => $this->url->to('/wp/v2/media/' . $id)]];
            }
        }
        if (preg_grep('/^wp:/', array_keys($links)) !== []) {
            $links['curies'] = RestUrl::curies();
        }
        return $links;
    }

    /**
     * media_details: the stored metadata as it is, its sizes each with its
     * MIME type and URL, and the full size after them when there are any
     * (an attachment without sizes, audio among them, has an empty list).
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function details(array $meta, string $mime, string $attached): array
    {
        $file = (string) ($meta['file'] ?? '');
        $baseUrl = rtrim($this->uploads->baseUrl() . '/' . dirname($file), '/.');
        $sizes = [];
        foreach ((array) ($meta['sizes'] ?? []) as $name => $size) {
            $type = $size['mime-type'] ?? '';
            unset($size['mime-type']);
            $sizes[$name] = $size + ['mime_type' => $type, 'source_url' => $baseUrl . '/' . ($size['file'] ?? '')];
        }
        // The full size is what the image is shown at in full: a size stored under that name, else the attached file at the metadata's dimensions.
        if ($sizes !== [] && !empty($meta['width'])) {
            $full = $meta['sizes']['full'] ?? null;
            $sizes['full'] = is_array($full)
                ? ['file' => $full['file'] ?? '', 'width' => $full['width'] ?? 0, 'height' => $full['height'] ?? 0, 'mime_type' => $mime, 'source_url' => $baseUrl . '/' . ($full['file'] ?? '')]
                : ['file' => basename($attached), 'width' => $meta['width'], 'height' => $meta['height'] ?? 0, 'mime_type' => $mime, 'source_url' => $this->uploads->urlFor($attached)];
        }
        $meta['sizes'] = $sizes;
        return $meta;
    }
}
