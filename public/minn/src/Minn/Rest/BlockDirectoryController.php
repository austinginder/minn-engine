<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Engine;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\I18n\Gettext;
use Minn\Objects\Errors;
use Minn\Ops\DirectoryApi;
use Minn\Ops\Packages;
use Minn\RestError;
use Minn\Runtime\Runtime;
use Minn\Runtime\TimeSince;
use Minn\Runtime\Translations;

/**
 * wp/v2/block-directory/search (Austin, 2026-10-21, decision 1B): the
 * directory's block plugins for a term, asked as plugins_api asks (its
 * filters run) of wordpress.org through the Minn update service. The term
 * is required before the caller is judged; then only someone who may
 * install and activate plugins searches. A plugin with blocks is one item,
 * named for its first block: its short description as the directory words
 * it, ratings out of five, the author's name without its link, the last
 * update as a date and as how long ago, and a link to install it.
 */
final readonly class BlockDirectoryController
{
    private const REFUSAL = 'Sorry, you are not allowed to browse the block directory.';

    private const ARGS = [
        'context' => ['description' => 'Scope under which the request is made; determines fields present in response.', 'type' => 'string', 'enum' => ['view'], 'default' => 'view', 'required' => false],
        'page' => ['description' => 'Current page of the collection.', 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'required' => false],
        'per_page' => ['description' => 'Maximum number of items to be returned in result set.', 'type' => 'integer', 'default' => 10, 'minimum' => 1, 'maximum' => 100, 'required' => false],
        'term' => ['description' => 'Limit result set to blocks matching the search term.', 'type' => 'string', 'minLength' => 1, 'required' => true],
    ];

    public function __construct(private RestUrl $url)
    {
    }

    /** The block plugins matching a term, a page at a time. */
    #[Route(Method::Get, '/wp/v2/block-directory/search', policy: new Policy(Access::Cap, 'install_plugins', caps: ['activate_plugins'], signIn: 'rest_block_directory_cannot_view', signInMessage: self::REFUSAL, refuse: 'rest_block_directory_cannot_view', message: self::REFUSAL), args: [self::ARGS])]
    public function search(Request $request): Response
    {
        $runtime = Runtime::current();
        $api = new DirectoryApi('plugins', static fn (string $hook, mixed ...$args): mixed => Runtime::hooks()->filter($hook, $args), new Packages($runtime->site, $runtime->contentDir()), Translations::userLocale(), Engine::WP_VERSION);
        $answer = $api->ask('query_plugins', [
            'block' => (string) $request->query['term'],
            'per_page' => (int) ($request->query['per_page'] ?? 10),
            'page' => (int) ($request->query['page'] ?? 1),
        ]);
        if ($answer instanceof Errors) {
            throw new RestError((string) $answer->get_error_code(), (string) $answer->get_error_message(), 500);
        }
        $items = [];
        foreach ((array) (((array) $answer)['plugins'] ?? []) as $plugin) {
            $plugin = (array) $plugin;
            $blocks = array_values((array) ($plugin['blocks'] ?? []));
            if ($blocks !== []) {
                $items[] = $this->item($plugin, (array) $blocks[0]);
            }
        }
        return Reply::rows($items, Fields::fromQuery($request->query));
    }

    /**
     * One plugin as the search shows it.
     *
     * @param array<string, mixed> $plugin
     * @param array<string, mixed> $block
     * @return array<string, mixed>
     */
    private function item(array $plugin, array $block): array
    {
        $updated = (int) strtotime((string) ($plugin['last_updated'] ?? ''));
        $slug = (string) ($plugin['slug'] ?? '');
        return [
            'name' => (string) ($block['name'] ?? ''),
            'title' => (string) ($block['title'] ?? ''),
            'description' => (string) ($plugin['short_description'] ?? ''),
            'id' => $slug,
            'rating' => ($plugin['rating'] ?? 0) / 20,
            'rating_count' => (int) ($plugin['num_ratings'] ?? 0),
            'active_installs' => (int) ($plugin['active_installs'] ?? 0),
            'author_block_rating' => ($plugin['author_block_rating'] ?? 0) / 20,
            'author_block_count' => (int) ($plugin['author_block_count'] ?? 0),
            'author' => strip_tags((string) ($plugin['author'] ?? '')),
            'icon' => (string) (((array) ($plugin['icons'] ?? []))['1x'] ?? ''),
            'last_updated' => gmdate('Y-m-d\TH:i:s', $updated),
            'humanized_updated' => sprintf(Gettext::text('%s ago'), TimeSince::between($updated)),
            '_links' => [
                'wp:install-plugin' => [Links::item('wp:install-plugin', $this->url->to('/wp/v2/plugins', ['slug' => $slug]), [])],
                'curies' => RestUrl::curies(),
            ],
        ];
    }
}
