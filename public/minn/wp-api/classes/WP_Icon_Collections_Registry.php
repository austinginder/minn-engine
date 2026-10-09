<?php

/**
 * The icon collections (probe icons): one instance, which registers the
 * default collection when it is made. A collection needs a slug of
 * lowercase letters, digits, hyphens and underscores that starts and ends
 * with a letter or digit, and a string label; its description defaults to
 * ''. Unregistering a collection takes its icons with it.
 */
class WP_Icon_Collections_Registry
{
    protected $registered_collections = [];
    protected static $instance = null;

    public function register($collection_slug, $collection_properties)
    {
        $refusal = self::refusal((string) $collection_slug, (array) $collection_properties, isset($this->registered_collections[$collection_slug]));
        if ($refusal !== null) {
            _doing_it_wrong(__METHOD__, $refusal, '7.1.0');
            return false;
        }
        $this->registered_collections[$collection_slug] = ['description' => (string) ($collection_properties['description'] ?? ''), 'label' => $collection_properties['label'], 'slug' => (string) $collection_slug];
        return true;
    }

    public function unregister($collection_slug)
    {
        if (!$this->is_registered($collection_slug)) {
            _doing_it_wrong(__METHOD__, sprintf('Icon collection "%s" not found.', $collection_slug), '7.1.0');
            return false;
        }
        unset($this->registered_collections[$collection_slug]);
        WP_Icons_Registry::get_instance()->forget_collection((string) $collection_slug);
        return true;
    }

    public function get_registered($collection_slug)
    {
        return $this->registered_collections[$collection_slug] ?? null;
    }

    public function get_all_registered()
    {
        return array_values($this->registered_collections);
    }

    public function is_registered($collection_slug)
    {
        return is_string($collection_slug) && isset($this->registered_collections[$collection_slug]);
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            _wp_register_default_icon_collections();
        }
        return self::$instance;
    }

    /** Why a collection cannot register, or null. */
    private static function refusal(string $slug, array $properties, bool $taken): ?string
    {
        $unknown = array_diff(array_keys($properties), ['label', 'description']);
        return match (true) {
            $taken => 'Icon collection is already registered.',
            preg_match('/^[a-z0-9]([a-z0-9_-]*[a-z0-9])?$/', $slug) !== 1 => 'Icon collection slug must start and end with a lowercase letter or digit and contain only lowercase letters, digits, hyphens, and underscores.',
            $unknown !== [] => sprintf('Invalid icon collection property: "%s".', reset($unknown)),
            !is_string($properties['label'] ?? null) => 'Icon collection label must be a string.',
            isset($properties['description']) && !is_string($properties['description']) => 'Icon collection description must be a string.',
            default => null,
        };
    }
}
