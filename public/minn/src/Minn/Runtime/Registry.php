<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\I18n\Gettext;
use Minn\Json;

/**
 * Post types, taxonomies, and statuses as plugin code registers and reads
 * them. The built-in set is data/registry.json, captured from the
 * reference; registrations derive their defaults the way the content
 * probe observed (contracts/fixtures/api/content.json).
 */
final class Registry
{
    /** @var array<string, array<string, mixed>> */
    private array $postTypes;
    /** @var array<string, array<string, mixed>> */
    private array $taxonomies;
    /** @var array<string, array<string, mixed>> */
    private array $statuses;
    /** @var array<string, mixed> */
    public readonly array $queryVars;
    /** @var list<string> */
    public readonly array $publicQueryVars;

    /** @var array<string, array<string, mixed>> each type's features, registered or not, as $_wp_post_type_features holds them */
    private array $features = [];

    /** @var array<string, \WP_Post_Type> the one object per registered type, as $wp_post_types holds it */
    private array $postTypeObjects = [];
    /** @var array<string, \WP_Taxonomy> the one object per registered taxonomy, as $wp_taxonomies holds it */
    private array $taxonomyObjects = [];

    public function __construct(string $engineDir)
    {
        $data = Json::file($engineDir . '/data/registry.json') ?: [];
        $this->postTypes = $data['post_types'] ?? [];
        foreach ($this->postTypes as $name => &$type) {
            if (isset($type['supports'])) {
                $this->features[$name] = array_is_list($type['supports']) ? array_fill_keys($type['supports'], true) : $type['supports'];
            }
            unset($type['supports']);
        }
        unset($type);
        $this->taxonomies = $data['taxonomies'] ?? [];
        $this->statuses = $data['statuses'] ?? [];
        $this->queryVars = $data['query_vars'] ?? [];
        $this->publicQueryVars = $data['public_query_vars'] ?? [];
    }

    /**
     * Every post type.
     *
     * @return array<string, array<string, mixed>>
     */
    public function postTypes(): array
    {
        return $this->postTypes;
    }

    /** One post type, or null. */
    public function postType(string $name): ?array
    {
        return $this->postTypes[$name] ?? null;
    }

    /**
     * Every taxonomy.
     *
     * @return array<string, array<string, mixed>>
     */
    public function taxonomies(): array
    {
        return $this->taxonomies;
    }

    /** One taxonomy, or null. */
    public function taxonomy(string $name): ?array
    {
        return $this->taxonomies[$name] ?? null;
    }

    /**
     * Makes the one object for each registered type and taxonomy and binds
     * $wp_post_types and $wp_taxonomies to them, as the reference shares
     * them: a plugin changing one (relabelling Posts) is heard by every
     * reader (probe type-object-sharing). Called once the facade classes
     * exist; later registrations make their own.
     */
    public function shareObjects(): void
    {
        foreach (array_keys($this->postTypes) as $name) {
            $this->postTypeObject((string) $name);
        }
        foreach (array_keys($this->taxonomies) as $name) {
            $this->taxonomyObject((string) $name);
        }
        $GLOBALS['wp_post_types'] = &$this->postTypeObjects;
        $GLOBALS['wp_taxonomies'] = &$this->taxonomyObjects;
        $GLOBALS['_wp_post_type_features'] = &$this->features;
    }

    /** The one object for a registered post type, or null. */
    public function postTypeObject(string $name): ?\WP_Post_Type
    {
        if (!isset($this->postTypes[$name])) {
            return null;
        }
        return $this->postTypeObjects[$name] ??= self::objectFrom(\WP_Post_Type::class, $this->postTypes[$name]);
    }

    /**
     * The objects of every registered post type, in registration order.
     *
     * @return array<string, \WP_Post_Type>
     */
    public function postTypeObjects(): array
    {
        $objects = [];
        foreach (array_keys($this->postTypes) as $name) {
            $objects[$name] = $this->postTypeObject((string) $name);
        }
        return $objects;
    }

    /** The one object for a registered taxonomy, or null. */
    public function taxonomyObject(string $name): ?\WP_Taxonomy
    {
        if (!isset($this->taxonomies[$name])) {
            return null;
        }
        return $this->taxonomyObjects[$name] ??= self::objectFrom(\WP_Taxonomy::class, $this->taxonomies[$name]);
    }

    /**
     * The objects of every registered taxonomy, in registration order.
     *
     * @return array<string, \WP_Taxonomy>
     */
    public function taxonomyObjects(): array
    {
        $objects = [];
        foreach (array_keys($this->taxonomies) as $name) {
            $objects[$name] = $this->taxonomyObject((string) $name);
        }
        return $objects;
    }

    /**
     * Every post status.
     *
     * @return array<string, array<string, mixed>>
     */
    public function statuses(): array
    {
        return $this->statuses;
    }

    /** One post status, or null. */
    public function status(string $name): ?array
    {
        return $this->statuses[$name] ?? null;
    }

    /**
     * Takes a built post type as the registered one: the object
     * register_post_type made is the one every reader shares, and its
     * properties are the type's row. Registering again puts a new object in
     * the old one's place, as on the reference.
     *
     * @return array<string, mixed>
     */
    public function adoptPostType(\WP_Post_Type $object): array
    {
        $name = (string) $object->name;
        $this->postTypes[$name] = self::rowOf($object, ['supports', 'rest_controller', 'revisions_rest_controller', 'autosave_rest_controller']);
        $this->postTypeObjects[$name] = $object;
        return $this->postTypes[$name];
    }

    /**
     * An object's properties as a row, its labels and capabilities as arrays.
     *
     * @param list<string> $without
     * @return array<string, mixed>
     */
    private static function rowOf(object $object, array $without): array
    {
        $row = array_diff_key(get_object_vars($object), array_flip($without));
        foreach (['labels', 'cap'] as $key) {
            if (is_object($row[$key] ?? null)) {
                $row[$key] = get_object_vars($row[$key]);
            }
        }
        return $row;
    }

    /**
     * An object of the class holding a row as registration left it, without
     * building it again (no filters, no defaults): the built-in types and
     * taxonomies. A built-in type's supports were taken in, so its object
     * has none of its own.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $row
     * @return T
     */
    private static function objectFrom(string $class, array $row): object
    {
        $object = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        foreach ($row as $key => $value) {
            $object->{$key} = in_array($key, ['labels', 'cap'], true) ? (object) $value : $value;
        }
        if ($object instanceof \WP_Post_Type) {
            unset($object->supports);
        }
        return $object;
    }

    /** @param array<string, string> $overrides */
    /**
     * A type's labels from its own (probe post-remaining): the labels of its
     * base type (page for a hierarchical type, post for any other) under the
     * type's own. A missing
     * name comes from the type's label, a missing singular name from the name,
     * the menu name from the name, all items from the menu name, archives from
     * all items, and the admin bar name from the singular name or else the
     * type's name; a type with a singular name also names its single-item
     * template.
     *
     * @param array<string, mixed> $labels
     * @return array<string, mixed>
     */
    public function labels(array $labels, ?string $label, string $base, string $name): array
    {
        $labels = self::derivedLabels($labels, $label, $name);
        $defaults = array_diff_key((array) ($this->postTypes[$base]['labels'] ?? []), ['name_admin_bar' => true, 'template_name' => true]);
        $merged = array_merge($defaults, $labels);
        if (!isset($merged['template_name']) && isset($labels['singular_name'])) {
            $merged['template_name'] = sprintf(\function_exists('__') ? Gettext::text('Single item: %s') : 'Single item: %s', (string) $labels['singular_name']);
        }
        return $merged;
    }

    /**
     * A taxonomy's labels from its own (probe taxonomy-remaining): derived as a
     * type's are, under the category labels for a hierarchical taxonomy or the
     * tag labels for any other; one that names no template takes "%s
     * Archives" with the singular name it ends up with.
     *
     * @param array<string, mixed> $labels
     * @return array<string, mixed>
     */
    public function taxonomyLabels(array $labels, ?string $label, string $base, string $name): array
    {
        $labels = self::derivedLabels($labels, $label, $name);
        $defaults = array_diff_key((array) ($this->taxonomies[$base]['labels'] ?? []), ['name_admin_bar' => true, 'template_name' => true, 'archives' => true]);
        $merged = array_merge($defaults, $labels);
        $merged['template_name'] ??= sprintf(\function_exists('__') ? Gettext::text('%s Archives') : '%s Archives', (string) ($merged['singular_name'] ?? ''));
        return $merged;
    }

    /**
     * The labels a type or taxonomy gets from its own: a missing name from
     * its label, a missing singular name from the name, the menu name from
     * the name, all items from the menu name, archives from all items, and
     * the admin bar name from the singular name or else its own name.
     *
     * @param array<string, mixed> $labels
     * @return array<string, mixed>
     */
    private static function derivedLabels(array $labels, ?string $label, string $name): array
    {
        if (!isset($labels['name']) && $label !== null) {
            $labels['name'] = $label;
        }
        if (!isset($labels['singular_name']) && isset($labels['name'])) {
            $labels['singular_name'] = $labels['name'];
        }
        $labels['name_admin_bar'] ??= $labels['singular_name'] ?? $name;
        foreach (['menu_name' => 'name', 'all_items' => 'menu_name', 'archives' => 'all_items'] as $key => $from) {
            if (!isset($labels[$key]) && isset($labels[$from])) {
                $labels[$key] = $labels[$from];
            }
        }
        return $labels;
    }

    /**
     * A type's capability names (probe post-remaining): the eight primitive
     * ones, the six more a mapped type uses, the type's own names merged over
     * them, and create_posts last, the edit capability unless named.
     *
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    public static function capabilities(string|array $capabilityType, array $overrides, bool $mapMetaCap): array
    {
        // A one-name list has no plural, so its plural capabilities end in "_" (probe post-remaining).
        [$singular, $plural] = is_array($capabilityType) ? [$capabilityType[0], $capabilityType[1] ?? ''] : [$capabilityType, $capabilityType . 's'];
        $caps = [
            'edit_post' => "edit_{$singular}",
            'read_post' => "read_{$singular}",
            'delete_post' => "delete_{$singular}",
            'edit_posts' => "edit_{$plural}",
            'edit_others_posts' => "edit_others_{$plural}",
            'delete_posts' => "delete_{$plural}",
            'publish_posts' => "publish_{$plural}",
            'read_private_posts' => "read_private_{$plural}",
        ];
        if ($mapMetaCap) {
            $caps += [
                'read' => 'read',
                'delete_private_posts' => "delete_private_{$plural}",
                'delete_published_posts' => "delete_published_{$plural}",
                'delete_others_posts' => "delete_others_{$plural}",
                'edit_private_posts' => "edit_private_{$plural}",
                'edit_published_posts' => "edit_published_{$plural}",
            ];
        }
        foreach ($overrides as $key => $value) {
            $caps[$key] = $value;
        }
        $caps['create_posts'] ??= $caps['edit_posts'];
        return $caps;
    }

    /** Forgets a non-builtin post type. */
    public function unregisterPostType(string $name): bool
    {
        if (!isset($this->postTypes[$name]) || !empty($this->postTypes[$name]['_builtin'])) {
            return false;
        }
        unset($this->postTypes[$name], $this->postTypeObjects[$name]);
        return true;
    }

    /**
     * Adds a feature to a type, registered or not: the reference keeps
     * features apart from the type objects, so declaring one does not make
     * the type exist. A feature with arguments keeps all of them.
     *
     * @param list<mixed> $args
     */
    public function addSupport(string $type, string $feature, array $args): void
    {
        $this->features[$type][$feature] = $args === [] ? true : $args;
    }

    /** Removes a feature from a post type. */
    public function removeSupport(string $type, string $feature): void
    {
        unset($this->features[$type][$feature]);
    }

    /** Removes every feature of a post type. */
    public function removeSupports(string $type): void
    {
        unset($this->features[$type]);
    }

    /**
     * The features a post type supports.
     *
     * @return array<string, mixed> feature => true or the arguments given
     */
    public function supports(string $type): array
    {
        return $this->features[$type] ?? [];
    }

    /**
     * The default labels of a family as label => [the plain one's, the
     * hierarchical one's]: post and page for types, tag and category for
     * taxonomies, from the built-in data (WP_Post_Type::get_default_labels).
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function defaultLabels(string $plain, string $hierarchical): array
    {
        $rows = isset($this->postTypes[$plain]) ? $this->postTypes : $this->taxonomies;
        $labels = [];
        foreach (array_diff_key((array) ($rows[$plain]['labels'] ?? []), ['menu_name' => true, 'name_admin_bar' => true, 'template_name' => true]) as $key => $value) {
            $labels[$key] = [$value, $rows[$hierarchical]['labels'][$key] ?? null];
        }
        return $labels;
    }

    /**
     * Takes a built taxonomy as the registered one, the object every reader
     * shares, its properties the taxonomy's row.
     *
     * @return array<string, mixed>
     */
    public function adoptTaxonomy(\WP_Taxonomy $object): array
    {
        $name = (string) $object->name;
        $this->taxonomies[$name] = self::rowOf($object, ['rest_controller']);
        $this->taxonomyObjects[$name] = $object;
        return $this->taxonomies[$name];
    }

    /** Whether a registered rewrite settles into its full form: with pretty permalinks or in the admin; otherwise it stays as given. */
    public static function settlesRewrites(): bool
    {
        return !Runtime::booted() || !\function_exists('get_option') || Runtime::current()->isAdmin || (string) Runtime::options()->filtered('permalink_structure') !== '';
    }

    /** Forgets a non-builtin taxonomy. */
    public function unregisterTaxonomy(string $name): bool
    {
        if (!isset($this->taxonomies[$name]) || !empty($this->taxonomies[$name]['_builtin'])) {
            return false;
        }
        unset($this->taxonomies[$name], $this->taxonomyObjects[$name]);
        return true;
    }

    /** Attaches a taxonomy to a post type. */
    public function addObjectType(string $taxonomy, string $type): bool
    {
        if (!isset($this->taxonomies[$taxonomy])) {
            return false;
        }
        if (!in_array($type, $this->taxonomies[$taxonomy]['object_type'], true)) {
            $this->taxonomies[$taxonomy]['object_type'][] = $type;
            // The shared object hears it too, whatever else a plugin changed on it.
            if (isset($this->taxonomyObjects[$taxonomy])) {
                $this->taxonomyObjects[$taxonomy]->object_type = $this->taxonomies[$taxonomy]['object_type'];
            }
        }
        return true;
    }

    /** Detaches a taxonomy from a post type. */
    public function removeObjectType(string $taxonomy, string $type): bool
    {
        if (!isset($this->taxonomies[$taxonomy]) || !in_array($type, $this->taxonomies[$taxonomy]['object_type'], true)) {
            return false;
        }
        $this->taxonomies[$taxonomy]['object_type'] = array_values(array_diff($this->taxonomies[$taxonomy]['object_type'], [$type]));
        if (isset($this->taxonomyObjects[$taxonomy])) {
            $this->taxonomyObjects[$taxonomy]->object_type = $this->taxonomies[$taxonomy]['object_type'];
        }
        return true;
    }

    /**
     * Registers a post status.
     *
     * @param array<string, mixed> $args
     */
    public function registerStatus(string $name, array $args): array
    {
        $label = (string) ($args['label'] ?? $name);
        $public = (bool) ($args['public'] ?? false);
        $internal = (bool) ($args['internal'] ?? false);
        $status = [
            'label' => $label,
            'label_count' => $args['label_count'] ?? ['0' => $label . ' <span class="count">(%s)</span>', '1' => $label . ' <span class="count">(%s)</span>', 'singular' => $label . ' <span class="count">(%s)</span>', 'plural' => $label . ' <span class="count">(%s)</span>', 'context' => null, 'domain' => null],
            'exclude_from_search' => (bool) ($args['exclude_from_search'] ?? $internal),
            '_builtin' => false,
            'public' => $public,
            'internal' => $internal,
            'protected' => (bool) ($args['protected'] ?? false),
            'private' => (bool) ($args['private'] ?? false),
            'publicly_queryable' => (bool) ($args['publicly_queryable'] ?? $public),
            'show_in_admin_status_list' => (bool) ($args['show_in_admin_status_list'] ?? !$internal),
            'show_in_admin_all_list' => (bool) ($args['show_in_admin_all_list'] ?? !$internal),
            'date_floating' => (bool) ($args['date_floating'] ?? false),
            'name' => $name,
        ];
        $this->statuses[$name] = $status;
        return $status;
    }
}
