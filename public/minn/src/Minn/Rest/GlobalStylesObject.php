<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\PostRecord;
use Minn\Content\Revisions;
use Minn\Content\Texturize;
use Minn\Theme\StyleSettings;
use Minn\Theme\UserStyles;
use stdClass;

/** The wp/v2/global-styles item, theme, and revision shapes. */
final readonly class GlobalStylesObject
{
    public function __construct(
        private Revisions $revisions,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /**
     * The saved styles: the title both ways, settings by origin, styles
     * with their presets resolved, the links; in editing context the
     * app's lock fields too, which a global-styles post never carries.
     */
    public function item(PostRecord $post, Context $context): array
    {
        $content = UserStyles::decode($post->content);
        $object = [
            'id' => $post->id,
            'title' => ['raw' => $post->title, 'rendered' => Texturize::html($post->title)],
            'settings' => self::node(StyleSettings::normalize($content['settings'], 'custom')),
            'styles' => self::node(StyleSettings::resolved($content['styles'])),
        ];
        if ($context->isEdit()) {
            $object['minn_modified'] = false;
            $object['minn_lock'] = null;
        }
        $object['_links'] = $this->links($post);
        return $object;
    }

    /** The active theme's own styles as the themes/{stylesheet} route answers them. */
    public function theme(array $settings, array $styles, string $stylesheet): array
    {
        return [
            'settings' => $settings,
            'styles' => $styles,
            '_links' => ['self' => [['href' => $this->url->to('/wp/v2/global-styles/themes/' . $stylesheet), 'targetHints' => ['allow' => ['GET']]]]],
        ];
    }

    /**
     * One revision row: both nodes when the body holds anything at all,
     * neither when it is blank, then the row's own facts; no links.
     */
    public function revision(PostRecord $revision): array
    {
        $content = UserStyles::decode($revision->content);
        $object = UserStyles::isBlank($revision->content) ? [] : [
            'settings' => self::node(StyleSettings::normalize($content['settings'], 'custom')),
            'styles' => self::node(StyleSettings::resolved($content['styles'])),
        ];
        return $object + [
            'author' => $revision->authorId,
            'date' => PostObject::date($revision->date),
            'date_gmt' => PostObject::date($revision->dateGmt),
            'id' => $revision->id,
            'modified' => PostObject::date($revision->modified),
            'modified_gmt' => PostObject::date($revision->modifiedGmt),
            'parent' => $revision->parentId,
        ];
    }

    /** Writing methods appear for a theme editor; the CSS action for whoever may write CSS. */
    private function links(PostRecord $post): array
    {
        $self = $this->url->to('/wp/v2/global-styles/' . $post->id);
        $allow = $this->caller->can('edit_theme_options') ? ['GET', 'POST', 'PUT', 'PATCH'] : ['GET'];
        $links = [
            'self' => [['href' => $self, 'targetHints' => ['allow' => $allow]]],
            'about' => [['href' => $this->url->to('/wp/v2/types/wp_global_styles')]],
            'version-history' => [['count' => count($this->revisions->revisionsOf($post->id)), 'href' => $self . '/revisions']],
            'wp:action-publish' => [['href' => $self]],
        ];
        if ($this->caller->can('edit_css')) {
            $links['wp:action-edit-css'] = [['href' => $self]];
        }
        $links['curies'] = RestUrl::curies();
        return $links;
    }

    /** An empty node is an object on the wire, never a list. */
    private static function node(array $node): array|stdClass
    {
        return $node === [] ? new stdClass() : $node;
    }
}
