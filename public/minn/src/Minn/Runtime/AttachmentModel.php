<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Auth\Can;
use Minn\I18n\Gettext;
use Minn\Media\Icons;
use Minn\Media\Sizing;
use Minn\Support\ByteSize;
use Minn\Support\Text;

/**
 * An attachment as the media modal's scripts read it
 * (wp_prepare_attachment_for_js): its fields, its file and its sizes, and
 * what the visitor may do with it. The nonces the modal saves, deletes and
 * edits with, the links to edit the attachment and its author, and the post
 * it was uploaded to are there only for a visitor allowed each, as the
 * reference gives them. The model passes through wp_prepare_attachment_for_js.
 */
final class AttachmentModel
{
    /** The model's first fields, in the order the reference sends them. */
    private const FIELDS = ['id', 'title', 'filename', 'url', 'link', 'alt', 'author', 'description', 'caption', 'name', 'status', 'uploadedTo', 'date', 'modified', 'menuOrder', 'mime', 'type', 'subtype', 'icon', 'dateFormatted', 'nonces', 'editLink', 'meta'];

    /** The model, or null for anything that is not an attachment. */
    public static function forJs(mixed $attachment): mixed
    {
        $post = Records::post($attachment);
        if (!is_object($post) || $post->post_type !== 'attachment') {
            return null;
        }
        $id = (int) $post->ID;
        $meta = Attachments::metadata($id);
        $mime = (string) $post->post_mime_type;
        [$type, $subtype] = str_contains($mime, '/') ? explode('/', $mime, 2) : [$mime, ''];
        $url = (string) Attachments::url($id);
        $file = (string) Attachments::file($id);
        $canEdit = (bool) Can::current('edit_post', $id);
        $stamp = static fn (mixed $gmt): int => (int) strtotime((string) $gmt) * 1000;
        $values = [
            // The post's own columns.
            'name' => $post->post_name,
            'title' => $post->post_title,
            'caption' => $post->post_excerpt,
            'description' => $post->post_content,
            'status' => $post->post_status,
            'author' => $post->post_author,
            'uploadedTo' => $post->post_parent,
            'menuOrder' => $post->menu_order,
            'modified' => $stamp($post->post_modified_gmt),
            'date' => $stamp($post->post_date_gmt),
            'dateFormatted' => SiteTime::fromStored(Runtime::options()->filtered('date_format'), $post->post_date),
            // Its file and where it is seen.
            'id' => $id,
            'mime' => $mime,
            'type' => $type,
            'subtype' => $subtype,
            'url' => $url,
            'filename' => Text::basename($file),
            'icon' => Icons::forType($id, '.svg'),
            'link' => PostLinks::attachmentLink($id),
            'alt' => MetaData::get('post', $id, '_wp_attachment_image_alt', true),
            // What the visitor may do with it.
            'editLink' => $canEdit ? EditLinks::post($id, 'raw') : false,
            'nonces' => [
                'update' => $canEdit ? Handoff::createNonce('update-post_' . $id) : false,
                'delete' => Can::current('delete_post', $id) ? Handoff::createNonce('delete-post_' . $id) : false,
                'edit' => $canEdit ? Handoff::createNonce('image_editor-' . $id) : false,
            ],
            'meta' => false,
        ];
        $response = array_replace(array_fill_keys(self::FIELDS, null), $values);
        $response += self::people($post);
        $response['filesizeInBytes'] = is_array($meta) && isset($meta['filesize']) ? $meta['filesize'] : (is_file($file) ? filesize($file) : 0);
        $response['filesizeHumanReadable'] = ByteSize::format($response['filesizeInBytes']);
        $response['context'] = MetaData::get('post', $id, '_wp_attachment_context', true);
        if (is_array($meta) && isset($meta['width'], $meta['height'])) {
            $response['height'] = $meta['height'];
            $response['width'] = $meta['width'];
            $response['orientation'] = $meta['height'] > $meta['width'] ? 'portrait' : 'landscape';
        }
        if ($type === 'image') {
            $names = Runtime::hooks()->filter('image_size_names_choose', [[
                'thumbnail' => Gettext::text('Thumbnail'),
                'medium' => Gettext::text('Medium'),
                'large' => Gettext::text('Large'),
                'full' => Gettext::text('Full Size'),
            ]]);
            $response['sizes'] = Sizing::editorSizes(is_array($meta) ? $meta : [], str_replace(Text::basename($url), '', $url), $url, (array) $names, static fn (string $size): mixed => AttachmentImages::downsize($id, $size));
        }
        $response['compat'] = ['item' => '', 'meta' => ''];
        return Runtime::hooks()->filter('wp_prepare_attachment_for_js', [$response, $post, $meta]);
    }

    /**
     * Who uploaded the attachment and to which post: the author's name, and
     * a link to edit them where one may (none for an author no longer a
     * user); the post's title and edit link for a visitor who may read it.
     *
     * @return array<string, mixed>
     */
    private static function people(object $post): array
    {
        $author = Handoff::user((int) $post->post_author);
        $people = is_object($author)
            ? ['authorName' => $author->display_name, 'authorLink' => EditLinks::user($author->ID)]
            : ['authorName' => Gettext::text('(no author)')];
        $parent = (int) $post->post_parent > 0 ? Records::post((int) $post->post_parent) : null;
        if (is_object($parent) && Can::current('read_post', $parent->ID)) {
            $people['uploadedToTitle'] = $parent->post_title !== '' ? $parent->post_title : Gettext::text('(no title)');
            $people['uploadedToLink'] = EditLinks::post($parent->ID, 'raw');
        }
        return $people;
    }
}
