<?php

declare(strict_types=1);

namespace Minn\Rest;

use Closure;
use Minn\Content\PostRecord;
use Minn\Content\Posts;
use Minn\Front\Permalinks;

/** The REST object of a post whose type plugin code registered (probe rest-plugin-types), built by what the type supports. */
final readonly class RegisteredPostFields
{
    public function __construct(private Posts $posts, private Permalinks $permalinks, private RestUrl $url)
    {
    }

    /**
     * A plugin's type in the view context (probe rest-plugin-types): the
     * shared fields, then only what the type supports (title, editor,
     * excerpt, author, thumbnail, page attributes, comments, formats, custom
     * fields), its parent when hierarchical, each REST taxonomy's term ids
     * under its REST base, the class list and links.
     *
     * @param Closure(int): list<string> $allow the methods the caller may use on a post
     * @param Closure(string, string): string $gmt a GMT date as the object writes it
     * @return array<string, mixed>
     */
    public function view(PostRecord $p, RegisteredType $type, Closure $allow, Closure $gmt): array
    {
        $protected = $p->isProtected();
        $featured = (int) ($this->posts->meta($p->id, '_thumbnail_id') ?? 0);
        $terms = [];
        foreach ($type->taxonomies() as $restBase => $taxonomy) {
            $terms[$taxonomy] = [$restBase, $this->posts->terms($p->id, $taxonomy)];
        }
        $object = [
            'id' => $p->id,
            'date' => PostObject::date($p->date),
            'date_gmt' => $gmt($p->date, $p->dateGmt),
            'guid' => ['rendered' => $p->guid],
            'modified' => PostObject::date($p->modified),
            'modified_gmt' => $gmt($p->modified, $p->modifiedGmt),
            'slug' => $p->slug,
            'status' => $p->status,
            'type' => $p->type,
            'link' => $this->permalinks->forPost($p),
            'title' => $type->supports('title') ? ['rendered' => RenderedFields::title($p)] : null,
            'content' => $type->supports('editor') ? ['rendered' => $protected ? '' : RenderedFields::content($p), 'protected' => $protected] : null,
            'excerpt' => $type->supports('excerpt') ? ['rendered' => $protected ? '' : RenderedFields::excerpt($p), 'protected' => $protected] : null,
            'author' => $type->supports('author') ? $p->authorId : null,
            'featured_media' => $type->supports('thumbnail') ? $featured : null,
            'parent' => $type->hierarchical() ? $p->parentId : null,
            'menu_order' => $type->supports('page-attributes') ? $p->menuOrder : null,
            'comment_status' => $type->supports('comments') ? $p->commentStatus : null,
            'ping_status' => $type->supports('comments') ? $p->pingStatus : null,
            'template' => (string) ($this->posts->meta($p->id, '_wp_page_template') ?? ''),
            'format' => $type->supports('post-formats') ? (\get_post_format($p->id) ?: 'standard') : null,
            'meta' => $type->supports('custom-fields') ? RestMeta::read('post', $p->id, $p->type, 'view') : null,
        ];
        $object = array_filter($object, static fn ($value) => $value !== null);
        foreach ($terms as [$restBase, $pairs]) {
            $object[$restBase] = array_map(static fn (array $t) => $t[0], $pairs);
        }
        $classes = ['post-' . $p->id, $p->type, 'type-' . $p->type, 'status-' . $p->status];
        $classes = [...$classes, ...($p->isProtected() ? ['post-password-required'] : []), ...($featured > 0 && $type->supports('thumbnail') ? ['has-post-thumbnail'] : []), 'hentry'];
        foreach ($terms as $taxonomy => [, $pairs]) {
            foreach ($pairs as $term) {
                $classes[] = $taxonomy . '-' . $term[1];
            }
        }
        $object['class_list'] = RenderedFields::classes($classes, $p->id);
        $object['_links'] = $this->links($p, $type, $featured, array_keys($terms), $allow);
        return $object;
    }

    /** @param list<string> $taxonomies @param Closure(int): list<string> $allow @return array<string, mixed> */
    private function links(PostRecord $p, RegisteredType $type, int $featured, array $taxonomies, Closure $allow): array
    {
        $id = $p->id;
        $base = $type->base();
        $predecessor = $this->posts->latestRevisionId($id);
        $restBases = array_flip($type->taxonomies());
        $links = [
            'self' => [['href' => $this->url->to("{$base}/{$id}"), 'targetHints' => ['allow' => $allow($id)]]],
            'collection' => [['href' => $this->url->to($base)]],
            'about' => [['href' => $this->url->to('/wp/v2/types/' . $p->type)]],
            'author' => $type->supports('author') && $p->authorId > 0 ? [['embeddable' => true, 'href' => $this->url->to('/wp/v2/users/' . $p->authorId)]] : null,
            'replies' => $type->supports('comments') ? [['embeddable' => true, 'href' => $this->url->to('/wp/v2/comments', ['post' => $id])]] : null,
            'version-history' => $type->supports('revisions') ? [['count' => $this->posts->revisionCount($id), 'href' => $this->url->to("{$base}/{$id}/revisions")]] : null,
            'predecessor-version' => $type->supports('revisions') && $predecessor > 0 ? [['id' => $predecessor, 'href' => $this->url->to("{$base}/{$id}/revisions/{$predecessor}")]] : null,
            'up' => $type->hierarchical() && $p->parentId > 0 ? [['embeddable' => true, 'href' => $this->url->to("{$base}/" . $p->parentId)]] : null,
            'wp:featuredmedia' => $type->supports('thumbnail') && $featured > 0 ? [['embeddable' => true, 'href' => $this->url->to('/wp/v2/media/' . $featured)]] : null,
            'wp:attachment' => [['href' => $this->url->to('/wp/v2/media', ['parent' => $id])]],
            'wp:term' => $taxonomies === [] ? null : array_map(fn (string $taxonomy) => ['taxonomy' => $taxonomy, 'embeddable' => true, 'href' => $this->url->to('/wp/v2/' . $restBases[$taxonomy], ['post' => $id])], $taxonomies),
            'curies' => RestUrl::curies(),
        ];
        return array_filter($links, static fn ($value) => $value !== null);
    }
}
