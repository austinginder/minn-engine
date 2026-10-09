<?php

/**
 * The icons (probe icons): one instance, which registers the default icons
 * when it is made. An icon is named collection/icon, both parts lowercase
 * letters, digits, hyphens and underscores that start and end with a
 * letter or digit, in a registered collection, with a string label and
 * exactly one of content or file_path. SVG content keeps only svg, path
 * and polygon with their drawing attributes; a file is read (and its
 * content kept) the first time it is needed. Search matches the name or
 * the label, ignoring case.
 */
class WP_Icons_Registry
{
    private const ALLOWED = [
        'svg' => ['xmlns' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'role' => true, 'class' => true, 'focusable' => true, 'aria-hidden' => true],
        'path' => ['d' => true, 'fill' => true, 'fill-rule' => true, 'transform' => true],
        'polygon' => ['points' => true, 'fill' => true, 'fill-rule' => true, 'transform' => true],
    ];

    protected $registered_icons = [];
    protected static $instance = null;

    protected function __construct()
    {
    }

    public function register($icon_name, $icon_properties)
    {
        $refusal = self::refusal((string) $icon_name, (array) $icon_properties, isset($this->registered_icons[$icon_name]));
        if ($refusal !== null) {
            _doing_it_wrong(__METHOD__, $refusal[0], $refusal[1]);
            return false;
        }
        $icon = (array) $icon_properties;
        if (isset($icon['content'])) {
            $icon['content'] = $this->sanitize_icon_content($icon['content']);
        }
        $this->registered_icons[$icon_name] = $icon + ['name' => (string) $icon_name, 'collection' => strstr((string) $icon_name, '/', true)];
        return true;
    }

    public function unregister($icon_name)
    {
        if (!$this->is_registered($icon_name)) {
            _doing_it_wrong(__METHOD__, sprintf('Icon "%s" is not registered.', $icon_name), '7.1.0');
            return false;
        }
        unset($this->registered_icons[$icon_name]);
        return true;
    }

    protected function sanitize_icon_content($icon_content)
    {
        return wp_kses((string) $icon_content, self::ALLOWED);
    }

    /** The icon's SVG: its content, or its file read once and kept; null when there is neither. */
    protected function get_content($icon_name)
    {
        $icon = $this->registered_icons[$icon_name] ?? null;
        if ($icon === null || isset($icon['content'])) {
            return $icon['content'] ?? null;
        }
        $path = (string) ($icon['file_path'] ?? '');
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            wp_trigger_error(__METHOD__, 'Icon file is missing or unreadable.');
            return null;
        }
        return $this->registered_icons[$icon_name]['content'] = $this->sanitize_icon_content((string) file_get_contents($path));
    }

    public function get_registered_icon($icon_name)
    {
        if (!$this->is_registered($icon_name)) {
            return null;
        }
        $this->get_content($icon_name);
        return $this->registered_icons[$icon_name];
    }

    public function get_registered_icons($search = '')
    {
        $out = [];
        foreach (array_keys($this->registered_icons) as $name) {
            $icon = $this->get_registered_icon($name);
            if ($search === '' || stripos($icon['name'], (string) $search) !== false || stripos((string) $icon['label'], (string) $search) !== false) {
                $out[] = $icon;
            }
        }
        return $out;
    }

    public function is_registered($icon_name)
    {
        return is_string($icon_name) && isset($this->registered_icons[$icon_name]);
    }

    public static function get_instance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            _wp_register_default_icons();
        }
        return self::$instance;
    }

    /** @internal drops the icons of a collection being unregistered */
    public function forget_collection(string $collection): void
    {
        $this->registered_icons = array_filter($this->registered_icons, static fn (array $icon): bool => $icon['collection'] !== $collection);
    }

    /** @return array{0: string, 1: string}|null why an icon cannot register, and the version, or null */
    private static function refusal(string $name, array $properties, bool $taken): ?array
    {
        $part = '[a-z0-9]([a-z0-9_-]*[a-z0-9])?';
        $collection = strstr($name, '/', true);
        $unknown = array_diff(array_keys($properties), ['label', 'content', 'file_path']);
        $name_refusal = match (true) {
            $taken => 'Icon is already registered.',
            $collection === false => 'Icon name must be namespaced in the form "collection/icon-name".',
            strtolower($name) !== $name => 'Icon names must not contain uppercase characters.',
            preg_match("#^{$part}/{$part}$#", $name) !== 1 => 'Icon names must start and end with a lowercase letter or digit and contain only lowercase letters, digits, hyphens, and underscores.',
            !WP_Icon_Collections_Registry::get_instance()->is_registered($collection) => sprintf('Icon collection "%s" is not registered.', $collection),
            default => null,
        };
        if ($name_refusal !== null) {
            return [$name_refusal, '7.1.0'];
        }
        $property_refusal = match (true) {
            $unknown !== [] => sprintf('Invalid icon property: "%s".', reset($unknown)),
            !is_string($properties['label'] ?? null) => 'Icon label must be a string.',
            isset($properties['content']) === isset($properties['file_path']) => 'Icons must provide either `content` or `file_path`.',
            isset($properties['content']) && !is_string($properties['content']) => 'Icon content must be a string.',
            default => null,
        };
        return $property_refusal === null ? null : [$property_refusal, '7.0.0'];
    }
}
