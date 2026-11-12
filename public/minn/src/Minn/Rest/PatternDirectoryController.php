<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Engine;
use Minn\Http;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Json;
use Minn\Ops\Directory;
use Minn\RestError;
use Minn\Runtime\KsesRules;
use Minn\Runtime\Transients;
use Minn\Runtime\Translations;
use Minn\Support\Sanitize;
use Minn\Support\Whole;

/**
 * wp/v2/pattern-directory/patterns (Austin, 2026-10-21, decision 1B): the
 * block editor's pattern directory, asked of wordpress.org through the Minn
 * update service, with the query the reference sends upstream (its paging
 * and order, then the search, slugs and offset asked for, the reader's
 * locale, the WordPress version, then the category and keyword by their
 * upstream names). An answer is kept an hour in the site transient the
 * reference keeps it in, named for that query, so either stack reads the
 * other's. Anyone who may edit posts browses it. Each pattern shows its
 * title as the directory renders it, its markup, its category and keyword
 * slugs, its description, viewport width and block types.
 */
final readonly class PatternDirectoryController
{
    private const REFUSAL = 'Sorry, you are not allowed to browse the local block pattern directory.';

    private const ARGS = [
        'context' => ['description' => 'Scope under which the request is made; determines fields present in response.', 'type' => 'string', 'enum' => ['view', 'embed', 'edit'], 'default' => 'view', 'required' => false],
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 100, 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'search' => ['description' => 'Limit results to those matching a string.', 'type' => 'string', 'minLength' => 1, 'required' => false],
        'category' => ['description' => 'Limit results to those matching a category ID.', 'type' => 'integer', 'minimum' => 1, 'required' => false],
        'keyword' => ['description' => 'Limit results to those matching a keyword ID.', 'type' => 'integer', 'minimum' => 1, 'required' => false],
        'slug' => ['description' => 'Limit results to those matching a pattern (slug).', 'type' => 'array', 'required' => false],
        'offset' => ['description' => 'Offset the result set by a specific number of items.', 'type' => 'integer', 'required' => false],
        'order' => ['description' => 'Order sort attribute ascending or descending.', 'type' => 'string', 'default' => 'desc', 'enum' => ['asc', 'desc'], 'required' => false],
        'orderby' => ['description' => 'Sort collection by post attribute.', 'type' => 'string', 'default' => 'date', 'enum' => ['author', 'date', 'id', 'include', 'modified', 'parent', 'relevance', 'slug', 'include_slugs', 'title', 'favorite_count'], 'required' => false],
    ];

    private const FAILED = 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="https://wordpress.org/support/forums/">support forums</a>.';

    /** How long an answer is kept, as the reference keeps it. */
    private const KEEP = 3600;

    public function __construct(private Http $http = new Http())
    {
    }

    /** The directory's patterns for a query, a page at a time. */
    #[Route(Method::Get, '/wp/v2/pattern-directory/patterns', policy: new Policy(Access::Cap, 'edit_posts', signIn: 'rest_pattern_directory_cannot_view', signInMessage: self::REFUSAL, refuse: 'rest_pattern_directory_cannot_view', message: self::REFUSAL), args: [self::ARGS])]
    public function patterns(Request $request): Response
    {
        $query = self::upstreamQuery($request->query);
        $key = 'wp_remote_block_patterns_' . md5(serialize($query));
        $patterns = Transients::getSite($key);
        if (!is_array($patterns)) {
            $patterns = $this->fetch($query);
            Transients::setSite($key, $patterns, self::KEEP);
        }
        $items = array_map(self::item(...), array_values(array_filter($patterns, is_object(...))));
        return Reply::rows($items, Fields::fromQuery($request->query));
    }

    /**
     * The upstream query in the reference's order: paging and order first,
     * then the search, slugs and offset the request names, the reader's
     * locale and the WordPress version, then category and keyword under
     * their upstream names.
     *
     * @param array<string, mixed> $asked
     * @return array<string, mixed>
     */
    private static function upstreamQuery(array $asked): array
    {
        $query = [
            'page' => (int) ($asked['page'] ?? 1),
            'per_page' => (int) ($asked['per_page'] ?? 100),
            'order' => (string) ($asked['order'] ?? 'desc'),
            'orderby' => (string) ($asked['orderby'] ?? 'date'),
        ];
        foreach (['search', 'slug', 'offset'] as $name) {
            if (isset($asked[$name])) {
                $query[$name] = match ($name) {
                    'slug' => array_values((array) $asked[$name]),
                    'offset' => (int) $asked[$name],
                    default => (string) $asked[$name],
                };
            }
        }
        $query['locale'] = Translations::userLocale();
        $query['wp-version'] = Engine::WP_VERSION;
        foreach (['category' => 'pattern-categories', 'keyword' => 'pattern-keywords'] as $name => $upstream) {
            if (isset($asked[$name])) {
                $query[$upstream] = (int) $asked[$name];
            }
        }
        return $query;
    }

    /**
     * The directory's answer, its patterns as objects. A request that never
     * got an answer is refused with the transport's own error; an answer
     * that is not a list of patterns as pattern_api_failed, the body beside
     * it, as the reference refuses both.
     *
     * @param array<string, mixed> $query
     * @return list<object>
     */
    private function fetch(array $query): array
    {
        $reply = $this->http->get(Directory::url('patterns/1.0/') . '?' . http_build_query($query), timeout: 30, hosts: [Directory::ORIGIN], maxBytes: 16 * 1048576, userAgent: Directory::userAgent());
        if ($reply->failed()) {
            throw new RestError('http_request_failed', (string) $reply->error, 500);
        }
        // Objects, as the reference keeps them.
        $answer = Json::objects($reply->body);
        if (!is_array($answer)) {
            throw new RestError('pattern_api_failed', self::FAILED, 500, topLevel: ['additional_data' => [['response' => $reply->body]]]);
        }
        return $answer;
    }

    /**
     * One pattern as the directory route shows it, each field sanitized as
     * the reference sanitizes what the directory sent (probe 2026-10-24):
     * the title, description, keywords and block types as text, the
     * categories as slugs, the markup as a post's, the numbers as whole
     * numbers.
     *
     * @return array<string, mixed>
     */
    private static function item(object $raw): array
    {
        $meta = (array) ($raw->meta ?? []);
        $text = static fn (mixed $value): string => Sanitize::textField((string) $value);
        return [
            'id' => Whole::abs(($raw->id ?? 0)),
            'title' => $text(((array) ($raw->title ?? []))['rendered'] ?? ''),
            'content' => KsesRules::post((string) ($raw->pattern_content ?? '')),
            'categories' => array_map(static fn (mixed $slug): string => Sanitize::title((string) $slug), array_values((array) ($raw->category_slugs ?? []))),
            'keywords' => array_map($text, explode(',', (string) ($meta['wpop_keywords'] ?? ''))),
            'description' => $text($meta['wpop_description'] ?? ''),
            'viewport_width' => Whole::abs(($meta['wpop_viewport_width'] ?? 0)),
            'block_types' => array_map($text, array_values((array) ($meta['wpop_block_types'] ?? []))),
        ];
    }
}
