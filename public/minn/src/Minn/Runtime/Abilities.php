<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * The abilities registry behind the wp_*_ability facade, as the reference
 * keeps it (probe abilities-registry). Categories register only on
 * wp_abilities_api_categories_init and abilities only on
 * wp_abilities_api_init; each action fires once, lazily, on the first
 * lookup of its kind (abilities after categories). A registration is
 * checked as the reference checks it (slug or name shape, duplicates,
 * required fields, a known category) and refused with its notice. An
 * ability's meta gains the default annotations, show_in_rest and public.
 */
final class Abilities
{
    private const STATE = 'abilities';
    private const CATEGORIES = 'WP_Ability_Categories_Registry';
    private const ABILITIES = 'WP_Abilities_Registry';
    private const NAME = '/^[a-z0-9-]+\/[a-z0-9-]+$/';
    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

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
        \do_action('wp_abilities_api_categories_init');
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
        \do_action('wp_abilities_api_init');
    }

    /** Registers a category; its row, or null with the reference's notice. @return array<string, mixed>|null */
    public static function registerCategory(string $slug, array $args): ?array
    {
        if (!\doing_action('wp_abilities_api_categories_init')) {
            \_doing_it_wrong('wp_register_ability_category', sprintf('Ability categories must be registered on the %1$s action. The ability category %2$s was not registered.', 'wp_abilities_api_categories_init', $slug), '6.9.0');
            return null;
        }
        $state = self::state();
        $refusal = match (true) {
            isset($state['categories'][$slug]) => sprintf('Ability category "%s" is already registered.', $slug),
            preg_match(self::SLUG, $slug) !== 1 => 'Ability category slug must contain only lowercase alphanumeric characters and dashes.',
            !is_string($args['label'] ?? null) || $args['label'] === '' => 'The ability category properties must contain a `label` string.',
            !is_string($args['description'] ?? null) || $args['description'] === '' => 'The ability category properties must contain a `description` string.',
            default => null,
        };
        if ($refusal !== null) {
            \_doing_it_wrong(self::CATEGORIES . '::register', $refusal, '6.9.0');
            return null;
        }
        $state['categories'][$slug] = ['slug' => $slug, 'label' => $args['label'], 'description' => $args['description'], 'meta' => (array) ($args['meta'] ?? [])];
        self::save($state);
        return $state['categories'][$slug];
    }

    /** Registers an ability; its row, or null with the reference's notice. @return array<string, mixed>|null */
    public static function register(string $name, array $args): ?array
    {
        if (!\doing_action('wp_abilities_api_init')) {
            \_doing_it_wrong('wp_register_ability', sprintf('Abilities must be registered on the %1$s action. The ability %2$s was not registered.', 'wp_abilities_api_init', $name), '6.9.0');
            return null;
        }
        $state = self::state();
        $refusal = match (true) {
            preg_match(self::NAME, $name) !== 1 => 'Ability name must be a string containing a namespace prefix, i.e. "my-plugin/my-ability". It can only contain lowercase alphanumeric characters, dashes and the forward slash.',
            isset($state['abilities'][$name]) => sprintf('Ability "%s" is already registered.', $name),
            default => self::refusal($name, $args, $state['categories']),
        };
        if ($refusal !== null) {
            \_doing_it_wrong(self::ABILITIES . '::register', $refusal, '6.9.0');
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
            \_doing_it_wrong(self::ABILITIES . '::get_registered', sprintf('Ability "%s" not found.', $name), '6.9.0');
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
            \_doing_it_wrong(self::CATEGORIES . '::get_registered', sprintf('Ability category "%s" not found.', $slug), '6.9.0');
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

    /** Whether an ability is marked read-only, which decides the method its run endpoint takes. */
    public static function isReadOnly(string $name): bool
    {
        return (bool) (self::ability($name)['meta']['annotations']['readonly'] ?? false);
    }

    /** @param array<string, mixed> $args @param array<string, array> $categories */
    private static function refusal(string $name, array $args, array $categories): ?string
    {
        foreach (['label', 'description', 'category'] as $field) {
            if (!is_string($args[$field] ?? null) || $args[$field] === '') {
                return "The ability properties must contain a `{$field}` string.";
            }
        }
        return match (true) {
            !isset($categories[$args['category']]) => sprintf('Ability category "%1$s" is not registered. Please register the ability category before assigning it to ability "%2$s".', $args['category'], $name),
            !is_callable($args['execute_callback'] ?? null) => 'The ability properties must contain a valid `execute_callback` function.',
            !is_callable($args['permission_callback'] ?? null) => 'The ability properties must provide a valid `permission_callback` function.',
            default => null,
        };
    }

    /** An ability's meta with the reference's defaults: the three annotations, show_in_rest and public. @return array<string, mixed> */
    private static function meta(array $meta): array
    {
        $meta['annotations'] = array_merge(['readonly' => null, 'destructive' => null, 'idempotent' => null], (array) ($meta['annotations'] ?? []));
        $meta['show_in_rest'] ??= false;
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
            \_doing_it_wrong($function, $notice, '6.9.0');
            return null;
        }
        unset($state[$key][$name]);
        self::save($state);
        return $row;
    }
}
