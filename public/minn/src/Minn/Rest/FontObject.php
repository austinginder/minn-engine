<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Db;
use Minn\Json;

/**
 * A font family and a font face as wp/v2 shapes them (probe: the font
 * library routes on the reference): the id, the theme.json version (3,
 * which nothing stores), and the settings the post holds: a family's
 * name and slug from its title and slug, then its font family and preview
 * from its JSON content; a face's font-face properties from its JSON
 * content. A family lists its published and private faces by id, each a
 * link that embeds; a face links to its family. The
 * same fields show in every context, and the fields plugin code registers
 * join through rest_prepare_wp_font_family and rest_prepare_wp_font_face.
 * Only someone who may edit theme options sees either, and may use every
 * method on it.
 */
final readonly class FontObject
{
    private const FAMILY_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    private const FACE_METHODS = ['GET', 'DELETE'];
    private const FACE_PROPERTIES = ['fontFamily', 'fontStyle', 'fontWeight', 'fontDisplay', 'src', 'fontStretch', 'ascentOverride', 'descentOverride', 'fontVariant', 'fontFeatureSettings', 'fontVariationSettings', 'lineGapOverride', 'sizeAdjust', 'unicodeRange', 'preview'];

    public function __construct(private Db $db, private RestUrl $url)
    {
    }

    /** A font family as wp/v2 shapes it, through rest_prepare_wp_font_family. @return array<string, mixed> */
    public function family(PostRecord $post): array
    {
        $faces = $this->faceIds($post->id);
        $self = "/wp/v2/font-families/{$post->id}";
        $links = [
            'self' => [Links::item('self', $this->url->to($self), ['targetHints' => ['allow' => self::FAMILY_METHODS]])],
            'collection' => [Links::item('collection', $this->url->to('/wp/v2/font-families'), [])],
        ];
        if ($faces !== []) {
            $links['font_faces'] = array_map(fn (int $face): array => Links::item('font_faces', $this->url->to("{$self}/font-faces/{$face}"), ['embeddable' => true]), $faces);
        }
        $item = [
            'id' => $post->id,
            'theme_json_version' => 3,
            'font_faces' => $faces,
            'font_family_settings' => self::familySettings($post),
            '_links' => $links,
        ];
        return RuntimePrepare::postItem('rest_prepare_wp_font_family', $item, $post->id, 'wp_font_family');
    }

    /** A font face as wp/v2 shapes it, through rest_prepare_wp_font_face. @return array<string, mixed> */
    public function face(PostRecord $post): array
    {
        $family = "/wp/v2/font-families/{$post->parentId}";
        $item = [
            'id' => $post->id,
            'theme_json_version' => 3,
            'parent' => $post->parentId,
            'font_face_settings' => self::faceSettings($post->content),
            '_links' => [
                'self' => [Links::item('self', $this->url->to("{$family}/font-faces/{$post->id}"), ['targetHints' => ['allow' => self::FACE_METHODS]])],
                'collection' => [Links::item('collection', $this->url->to("{$family}/font-faces"), [])],
                'parent' => [Links::item('parent', $this->url->to($family), [])],
            ],
        ];
        return RuntimePrepare::postItem('rest_prepare_wp_font_face', $item, $post->id, 'wp_font_face');
    }

    /** A family's published and private faces, oldest first. @return list<int> */
    private function faceIds(int $familyId): array
    {
        $rows = $this->db->rows("SELECT ID FROM {$this->db->table('posts')} WHERE post_type = 'wp_font_face' AND post_parent = ? AND post_status IN ('publish', 'private') ORDER BY ID ASC", [$familyId]);
        return array_map(static fn (array $row): int => (int) $row['ID'], $rows);
    }

    /**
     * A family's settings: its name and slug from the post, then its font
     * family and preview from the JSON its content holds, each empty when
     * missing; nothing else the content holds shows.
     *
     * @return array<string, mixed>
     */
    private static function familySettings(PostRecord $post): array
    {
        $content = Json::decode($post->content, null);
        $content = is_array($content) ? $content : [];
        return ['name' => $post->title, 'slug' => $post->slug, 'fontFamily' => $content['fontFamily'] ?? '', 'preview' => $content['preview'] ?? ''];
    }

    /**
     * A face's settings: the font-face properties its content's JSON holds,
     * in its order, anything else left out; content that is no JSON at all
     * shows an empty font family and no sources.
     *
     * @return array<string, mixed>
     */
    private static function faceSettings(string $content): array
    {
        $decoded = Json::decode($content, null);
        if (!is_array($decoded)) {
            return ['fontFamily' => '', 'src' => []];
        }
        return array_intersect_key($decoded, array_flip(self::FACE_PROPERTIES));
    }
}
