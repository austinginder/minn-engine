<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Http\Args;
use Minn\Http\Access;
use Minn\Http\Method;
use Minn\Http\Policy;
use Minn\Http\Request;
use Minn\Http\Response;
use Minn\Http\Route;
use Minn\Runtime\Abilities;
use Minn\Runtime\Runtime;
use Minn\RestError;

/**
 * wp-abilities/v1: what this site can be asked to do, and the doing of it.
 *
 * An ability is a named unit of work with an input schema, an output
 * schema, and a permission callback, registered by core or by a plugin.
 * The catalogue is what an agent reads first, so the shapes here are the
 * reference's, captured; the engine's own registry answers them.
 *
 * Reading the catalogue needs only a session. Running one is the ability's
 * own decision, through its permission callback, and a read-only ability
 * runs on GET, since running it changes nothing.
 */
final readonly class AbilitiesController
{
    private const SIGNED_IN = ['rest_forbidden', 'Sorry, you are not allowed to do that.'];

    public function __construct(private RestUrl $url, private Caller $caller)
    {
    }

    /** Every registered ability, narrowed to one category when asked. */
    #[Route(Method::Get, '/wp-abilities/v1/abilities', policy: new Policy(Access::SignedIn, signIn: self::SIGNED_IN[0], signInMessage: self::SIGNED_IN[1]), args: [Args::CONTEXT])]
    public function abilities(Request $request): Response
    {
        $this->boot();
        $category = (string) $request->query('category', '');
        $items = [];
        foreach (Abilities::all() as $ability) {
            if ($category !== '' && (string) ($ability['category'] ?? '') !== $category) {
                continue;
            }
            $items[] = $this->object($ability);
        }
        return Reply::answer($request, $items);
    }

    /**
     * Runs an ability. A read-only one takes GET and its input from the
     * query; anything else takes POST and its input from the body. The
     * ability's own permission callback decides who may.
     */
    #[Route(Method::Get, '/wp-abilities/v1/abilities/{name:[a-zA-Z0-9\-\/]+?}/run', policy: new Policy(Access::SignedIn, signIn: self::SIGNED_IN[0], signInMessage: self::SIGNED_IN[1]))]
    #[Route(Method::Post, '/wp-abilities/v1/abilities/{name:[a-zA-Z0-9\-\/]+?}/run', policy: new Policy(Access::SignedIn, signIn: self::SIGNED_IN[0], signInMessage: self::SIGNED_IN[1]))]
    public function run(Request $request, string $name): Response
    {
        $this->boot();
        $this->find($name);
        $readOnly = Abilities::isReadOnly($name);
        if ($readOnly && $request->method !== Method::Get) {
            throw new RestError('rest_ability_invalid_method', 'Read-only abilities require GET method.', 405);
        }
        if (!$readOnly && $request->method !== Method::Post) {
            throw new RestError('rest_ability_invalid_method', 'Abilities that are not read-only require POST method.', 405);
        }
        if (!Abilities::permits($name)) {
            throw new RestError('rest_ability_cannot_execute', 'Sorry, you are not allowed to execute this ability.', 403);
        }
        $input = $request->method === Method::Get ? ($request->query['input'] ?? []) : ($request->json()['input'] ?? []);
        return Reply::answer($request, Abilities::execute($name, $input));
    }

    /** One ability by name. */
    #[Route(Method::Get, '/wp-abilities/v1/abilities/{name:[a-zA-Z0-9\-\/]+}', policy: new Policy(Access::SignedIn, signIn: self::SIGNED_IN[0], signInMessage: self::SIGNED_IN[1]), args: [Args::CONTEXT])]
    public function ability(Request $request, string $name): Response
    {
        $this->boot();
        return Reply::answer($request, $this->object($this->find($name)));
    }

    /** Every ability category. */
    #[Route(Method::Get, '/wp-abilities/v1/categories', policy: new Policy(Access::SignedIn, signIn: self::SIGNED_IN[0], signInMessage: self::SIGNED_IN[1]), args: [Args::CONTEXT])]
    public function categories(Request $request): Response
    {
        $this->boot();
        return Reply::answer($request, array_map($this->category(...), array_values(Abilities::allCategories())));
    }

    /** One category by slug. */
    #[Route(Method::Get, '/wp-abilities/v1/categories/{slug:[a-z0-9]+(?:-[a-z0-9]+)*}', policy: new Policy(Access::SignedIn, signIn: self::SIGNED_IN[0], signInMessage: self::SIGNED_IN[1]), args: [Args::CONTEXT])]
    public function category_(Request $request, string $slug): Response
    {
        $this->boot();
        $category = Abilities::findCategory($slug);
        if ($category === null) {
            throw new RestError('rest_ability_category_not_found', 'Ability category not found.', 404);
        }
        return Reply::answer($request, $this->category($category));
    }

    /** Registrations land at the abilities init, which the registry fires once. */
    private function boot(): void
    {
        if (Runtime::booted()) {
            Abilities::initialize();
        }
    }

    /** @return array<string, mixed> */
    private function find(string $name): array
    {
        $ability = Abilities::find($name);
        if ($ability === null) {
            throw new RestError('rest_ability_not_found', 'Ability not found.', 404);
        }
        return $ability;
    }

    /**
     * One ability as the catalogue lists it.
     *
     * @param array<string, mixed> $ability
     * @return array<string, mixed>
     */
    private function object(array $ability): array
    {
        $name = (string) $ability['name'];
        return [
            'name' => $name,
            'label' => (string) ($ability['label'] ?? ''),
            'description' => (string) ($ability['description'] ?? ''),
            'category' => (string) ($ability['category'] ?? ''),
            'input_schema' => $ability['input_schema'] ?? null,
            'output_schema' => $ability['output_schema'] ?? null,
            'meta' => $ability['meta'] ?? [],
            '_links' => [
                'self' => [['href' => $this->url->to('/wp-abilities/v1/abilities/' . $name), 'targetHints' => ['allow' => ['GET']]]],
                'collection' => [['href' => $this->url->to('/wp-abilities/v1/abilities')]],
                'wp:action-run' => [['href' => $this->url->to('/wp-abilities/v1/abilities/' . $name . '/run')]],
            ],
        ];
    }

    /**
     * One category as the catalogue lists it.
     *
     * @param array<string, mixed> $category
     * @return array<string, mixed>
     */
    private function category(array $category): array
    {
        $slug = (string) $category['slug'];
        return [
            'slug' => $slug,
            'label' => (string) ($category['label'] ?? ''),
            'description' => (string) ($category['description'] ?? ''),
            'meta' => $category['meta'] ?? [],
            '_links' => [
                'self' => [['href' => $this->url->to('/wp-abilities/v1/categories/' . $slug), 'targetHints' => ['allow' => ['GET']]]],
                'collection' => [['href' => $this->url->to('/wp-abilities/v1/categories')]],
                'abilities' => [['href' => $this->url->to('/wp-abilities/v1/abilities') . '?category=' . $slug]],
            ],
        ];
    }
}
