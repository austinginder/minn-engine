<?php

declare(strict_types=1);

namespace Minn\Rest;

use Minn\Runtime\Runtime;

/**
 * A post type plugin code registered, as its REST object follows it
 * (probe rest-plugin-types): what it supports, whether it is hierarchical,
 * and the taxonomies it shows in REST under their REST bases. Only once the
 * runtime has loaded the plugins that register it.
 */
final readonly class RegisteredType
{
    private function __construct(public string $name, private \WP_Post_Type $object)
    {
    }

    /** The type when plugin code registered it (not a built-in), or null. */
    public static function of(string $type): ?self
    {
        if (!Runtime::booted()) {
            return null;
        }
        $object = \get_post_type_object($type);
        return $object instanceof \WP_Post_Type && empty($object->_builtin) ? new self($type, $object) : null;
    }

    /** Whether the type supports the feature (title, editor, author...). */
    public function supports(string $feature): bool
    {
        return \post_type_supports($this->name, $feature);
    }

    /** Whether the type's posts have parents. */
    public function hierarchical(): bool
    {
        return (bool) $this->object->hierarchical;
    }

    /** The REST base the type's routes live under, with its namespace. */
    public function base(): string
    {
        return '/' . ($this->object->rest_namespace ?: 'wp/v2') . '/' . ($this->object->rest_base ?: $this->name);
    }

    /** The taxonomies the type shows in REST, REST base => taxonomy, in registration order. @return array<string, string> */
    public function taxonomies(): array
    {
        $out = [];
        foreach (\get_object_taxonomies($this->name, 'objects') as $taxonomy) {
            if (!empty($taxonomy->show_in_rest)) {
                $out[(string) ($taxonomy->rest_base ?: $taxonomy->name)] = (string) $taxonomy->name;
            }
        }
        return $out;
    }
}
