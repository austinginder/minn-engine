<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The abilities registry behind WP_Abilities_Registry,
 * WP_Ability_Categories_Registry and the wp_*_ability facade, as the
 * reference keeps it (probes abilities-registry, abilities-classes). The
 * categories init and then the abilities init fire once each, lazily, on
 * the first lookup of their kind, each handed its registry. The registries
 * themselves take a registration at any time; only the wp_register_*
 * functions insist on the init action. A registration is checked as the
 * reference checks it (name shape, duplicates, a known category, then the
 * properties the ability and category classes check) and refused with its
 * notice. An ability's meta gains the default annotations, show_in_rest
 * (public's value when unset) and public.
 */
final class Abilities
{
    private const STATE = 'abilities';
    private const CATEGORIES = 'WP_Ability_Categories_Registry';
    private const ABILITIES = 'WP_Abilities_Registry';
    private const NAME = '/^[a-z0-9-]+\/[a-z0-9-]+$/';
    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';
    private const ANNOTATIONS = ['readonly' => null, 'destructive' => null, 'idempotent' => null];

    /** @return array{categoriesFired: bool, abilitiesFired: bool, categories: array<string, array>, abilities: array<string, array>} */
    private static function state(): array
    {
        $state = Runtime::current()->get(self::STATE);
        return is_array($state) ? $state : ['categoriesFired' => false, 'abilitiesFired' => false, 'categories' => [], 'abilities' => []];
    }

    private static function save(array $state): void
    {
        Runtime::current()->set(self::STATE, $state);
    }

    /** Fires the categories action once. */
    public static function initializeCategories(): void
    {
        $state = self::state();
        if ($state['categoriesFired']) {
            return;
        }
        $state['categoriesFired'] = true;
        self::save($state);
        Runtime::hooks()->action('wp_abilities_api_categories_init', [Handoff::abilityCategories()]);
    }

    /** Fires the categories action, then the abilities action, each once. */
    public static function initialize(): void
    {
        self::initializeCategories();
        $state = self::state();
        if ($state['abilitiesFired']) {
            return;
        }
        $state['abilitiesFired'] = true;
        self::save($state);
        Runtime::hooks()->action('wp_abilities_api_init', [Handoff::abilities()]);
    }

    /** Registers a category; its row, or null with the reference's notice. @return array<string, mixed>|null */
    public static function registerCategory(string $slug, array $args): ?array
    {
        $state = self::state();
        $refusal = match (true) {
            isset($state['categories'][$slug]) => sprintf('Ability category "%s" is already registered.', $slug),
            preg_match(self::SLUG, $slug) !== 1 => 'Ability category slug must contain only lowercase alphanumeric characters and dashes.',
            default => null,
        };
        // A well-formed new slug's arguments are the plugins' to change before they are judged (probe ability-hooks).
        if ($refusal === null) {
            $args = (array) Runtime::hooks()->filter('wp_register_ability_category_args', [$args, $slug]);
            $refusal = self::categoryRefusal($args);
        }
        if ($refusal !== null) {
            Notices::doingItWrong(self::CATEGORIES . '::register', $refusal, '6.9.0');
            return null;
        }
        $state['categories'][$slug] = ['slug' => $slug, 'label' => $args['label'], 'description' => $args['description'], 'meta' => (array) ($args['meta'] ?? [])];
        self::save($state);
        return $state['categories'][$slug];
    }

    /** Registers an ability; its row, or null with the reference's notice. @return array<string, mixed>|null */
    public static function register(string $name, array $args): ?array
    {
        $state = self::state();
        // A well-formed new name's arguments are the plugins' to change before they are judged (probe ability-hooks).
        if (preg_match(self::NAME, $name) === 1 && !isset($state['abilities'][$name])) {
            $args = (array) Runtime::hooks()->filter('wp_register_ability_args', [$args, $name]);
        }
        $category = $args['category'] ?? null;
        $refusal = match (true) {
            preg_match(self::NAME, $name) !== 1 => 'Ability name must be a string containing a namespace prefix, i.e. "my-plugin/my-ability". It can only contain lowercase alphanumeric characters, dashes and the forward slash.',
            isset($state['abilities'][$name]) => sprintf('Ability "%s" is already registered.', $name),
            // A category that is not registered is named before any other property (probe abilities-classes).
            is_string($category) && !isset($state['categories'][$category]) => sprintf('Ability category "%1$s" is not registered. Please register the ability category before assigning it to ability "%2$s".', $category, $name),
            default => self::abilityRefusal($args),
        };
        if ($refusal !== null) {
            Notices::doingItWrong(self::ABILITIES . '::register', $refusal, '6.9.0');
            return null;
        }
        $state['abilities'][$name] = [
            'name' => $name,
            'label' => $args['label'],
            'description' => $args['description'],
            'category' => $args['category'],
            'input_schema' => (array) ($args['input_schema'] ?? []),
            'output_schema' => (array) ($args['output_schema'] ?? []),
            'execute_callback' => $args['execute_callback'],
            'permission_callback' => $args['permission_callback'],
            'meta' => self::meta((array) ($args['meta'] ?? [])),
        ];
        self::save($state);
        return $state['abilities'][$name];
    }

    /** Removes an ability; its row, or null with the reference's notice. @return array<string, mixed>|null */
    public static function unregister(string $name): ?array
    {
        return self::forget('abilities', $name, self::ABILITIES . '::unregister', sprintf('Ability "%s" not found.', $name));
    }

    /** Removes a category; its row, or null with the reference's notice. @return array<string, mixed>|null */
    public static function unregisterCategory(string $slug): ?array
    {
        return self::forget('categories', $slug, self::CATEGORIES . '::unregister', sprintf('Ability category "%s" not found.', $slug));
    }

    /** One ability, or null with the reference's notice. @return array<string, mixed>|null */
    public static function find(string $name): ?array
    {
        $row = self::ability($name);
        if ($row === null) {
            Notices::doingItWrong(self::ABILITIES . '::get_registered', sprintf('Ability "%s" not found.', $name), '6.9.0');
        }
        return $row;
    }

    /** One ability, or null, asked after without a notice. @return array<string, mixed>|null */
    public static function ability(string $name): ?array
    {
        self::initialize();
        return self::state()['abilities'][$name] ?? null;
    }

    /** One category, or null with the reference's notice. @return array<string, mixed>|null */
    public static function findCategory(string $slug): ?array
    {
        $row = self::category($slug);
        if ($row === null) {
            Notices::doingItWrong(self::CATEGORIES . '::get_registered', sprintf('Ability category "%s" not found.', $slug), '6.9.0');
        }
        return $row;
    }

    /** One category, or null, asked after without a notice. @return array<string, mixed>|null */
    public static function category(string $slug): ?array
    {
        self::initializeCategories();
        return self::state()['categories'][$slug] ?? null;
    }

    /** Every ability, by name. @return array<string, array> */
    public static function all(): array
    {
        self::initialize();
        return self::state()['abilities'];
    }

    /** Every category, by slug. @return array<string, array> */
    public static function allCategories(): array
    {
        self::initializeCategories();
        return self::state()['categories'];
    }

    /** Why an ability's properties are refused, as WP_Ability::prepare_properties says it, or null. @param array<string, mixed> $args */
    public static function abilityRefusal(array $args): ?string
    {
        foreach (['label', 'description', 'category'] as $field) {
            if (!is_string($args[$field] ?? null) || $args[$field] === '') {
                return "The ability properties must contain a `{$field}` string.";
            }
        }
        $meta = $args['meta'] ?? [];
        return match (true) {
            !is_callable($args['execute_callback'] ?? null) => 'The ability properties must contain a valid `execute_callback` function.',
            !is_callable($args['permission_callback'] ?? null) => 'The ability properties must provide a valid `permission_callback` function.',
            isset($args['input_schema']) && !is_array($args['input_schema']) => 'The ability properties should provide a valid `input_schema` definition.',
            isset($args['output_schema']) && !is_array($args['output_schema']) => 'The ability properties should provide a valid `output_schema` definition.',
            !is_array($meta) => 'The ability properties should provide a valid `meta` array.',
            isset($meta['annotations']) && !is_array($meta['annotations']) => 'The ability meta should provide a valid `annotations` array.',
            isset($meta['show_in_rest']) && !is_bool($meta['show_in_rest']) => 'The ability meta should provide a valid `show_in_rest` boolean.',
            isset($meta['public']) && !is_bool($meta['public']) => 'The ability meta should provide a valid `public` boolean.',
            default => null,
        };
    }

    /** Why a category's properties are refused, as WP_Ability_Category::prepare_properties says it, or null. @param array<string, mixed> $args */
    public static function categoryRefusal(array $args): ?string
    {
        return match (true) {
            !is_string($args['label'] ?? null) || $args['label'] === '' => 'The ability category properties must contain a `label` string.',
            !is_string($args['description'] ?? null) || $args['description'] === '' => 'The ability category properties must contain a `description` string.',
            isset($args['meta']) && !is_array($args['meta']) => 'The ability category properties should provide a valid `meta` array.',
            default => null,
        };
    }

    /**
     * An ability's meta with the reference's defaults: the three annotations
     * first, show_in_rest (public's value when unset), then public.
     *
     * @return array<string, mixed>
     */
    public static function meta(array $meta): array
    {
        $meta = ['annotations' => array_merge(self::ANNOTATIONS, (array) ($meta['annotations'] ?? []))] + $meta;
        $meta['show_in_rest'] ??= $meta['public'] ?? false;
        $meta['public'] ??= false;
        return $meta;
    }

    /** @return array<string, mixed>|null */
    private static function forget(string $key, string $name, string $function, string $notice): ?array
    {
        $key === 'abilities' ? self::initialize() : self::initializeCategories();
        $state = self::state();
        $row = $state[$key][$name] ?? null;
        if ($row === null) {
            Notices::doingItWrong($function, $notice, '6.9.0');
            return null;
        }
        unset($state[$key][$name]);
        self::save($state);
        return $row;
    }
}
