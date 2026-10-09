<?php

use Minn\Runtime\Abilities;

/**
 * One registered ability category: its slug, label, description and meta
 * (probes abilities-registry, abilities-classes). Its properties are checked
 * as it is made, a refusal throwing InvalidArgumentException. It refuses to
 * be serialized or unserialized.
 */
final class WP_Ability_Category
{
    protected $slug;
    protected $label;
    protected $description;
    protected $meta = [];

    public function __construct(string $slug, array $args)
    {
        $this->slug = $slug;
        $properties = $this->prepare_properties($args);
        $this->label = $properties['label'];
        $this->description = $properties['description'];
        $this->meta = (array) ($properties['meta'] ?? []);
    }

    protected function prepare_properties(array $args): array
    {
        $refusal = Abilities::categoryRefusal($args);
        if ($refusal !== null) {
            throw new InvalidArgumentException($refusal);
        }
        return $args;
    }

    public function get_slug(): string
    {
        return $this->slug;
    }

    public function get_label(): string
    {
        return $this->label;
    }

    public function get_description(): string
    {
        return $this->description;
    }

    public function get_meta(): array
    {
        return $this->meta;
    }

    public function __wakeup(): void
    {
        throw new LogicException(__CLASS__ . ' should never be unserialized.');
    }

    public function __sleep(): array
    {
        throw new LogicException(__CLASS__ . ' should never be serialized.');
    }
}
