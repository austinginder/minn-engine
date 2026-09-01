<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Content\TermRecord;
use Minn\Db;
use Minn\Front\Permalinks;

/** The wp/v2 category and tag objects. */
final readonly class TermObject
{
    private const TAXONOMIES = [
        'categories' => ['taxonomy' => 'category', 'has_parent' => true, 'post_arg' => 'categories'],
        'tags' => ['taxonomy' => 'post_tag', 'has_parent' => false, 'post_arg' => 'tags'],
    ];

    public function __construct(
        private Db $db,
        private Permalinks $permalinks,
        private RestUrl $url,
        private Caller $caller,
    ) {
    }

    /** The REST URL builder. */
    public function url(): RestUrl
    {
        return $this->url;
    }

    /**
     * The taxonomy behind a rest_base.
     *
     * @return array{taxonomy: string, has_parent: bool, post_arg: string}
     */
    public static function config(string $restBase): array
    {
        return self::TAXONOMIES[$restBase];
    }

    /** The wp/v2 term shape. */
    public function view(TermRecord $term, string $restBase): array
    {
        $config = self::config($restBase);
        $id = (int) $term['term_id'];
        $object = [
            'id' => $id,
            'count' => (int) $term['count'],
            'description' => $term['description'],
            'link' => $this->permalinks->forTerm(TermRecord::fromRow($term->row() + ['taxonomy' => $config['taxonomy']])),
            'name' => $term['name'],
            'slug' => $term['slug'],
            'taxonomy' => $config['taxonomy'],
        ];
        if ($config['has_parent']) {
            $object['parent'] = (int) $term['parent'];
        }
        $object['meta'] = [];

        $links = [
            'self' => [['href' => $this->url->to("/wp/v2/{$restBase}/{$id}"), 'targetHints' => ['allow' => $this->allowedVerbs($term, $config)]]],
            'collection' => [['href' => $this->url->to("/wp/v2/{$restBase}")]],
            'about' => [['href' => $this->url->to('/wp/v2/taxonomies/' . $config['taxonomy'])]],
        ];
        if ($config['has_parent'] && (int) $term['parent'] > 0) {
            $links['up'] = [['embeddable' => true, 'href' => $this->url->to("/wp/v2/{$restBase}/" . (int) $term['parent'])]];
        }
        $links['wp:post_type'] = [['href' => $this->url->to('/wp/v2/posts', [$config['post_arg'] => $id])]];
        $links['curies'] = RestUrl::curies();
        $object['_links'] = $links;
        return $object;
    }

    /** Managers may write; nobody may delete the default category. */
    private function allowedVerbs(TermRecord $term, array $config): array
    {
        if (!$this->caller->can('manage_categories')) {
            return ['GET'];
        }
        $isDefault = $config['taxonomy'] === 'category'
            && (int) $term['term_id'] === (int) ($this->db->option('default_category') ?? 0);
        return $isDefault ? ['GET', 'POST', 'PUT', 'PATCH'] : ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
    }
}
