<?php

declare(strict_types=1);

namespace Minn\Runtime;

/**
 * What a theme supports, as add_theme_support keeps it (probe rest-themes):
 * a bare feature is true, anything else its arguments. html5 lists add up
 * (repeats and all), post thumbnails stay on for every type once on, post
 * formats keep only real formats, the custom logo fills in its defaults at
 * once (flexible both ways when asked for bare), and the custom header and
 * background get theirs as WordPress finishes loading, a header with no
 * width or height made flexible that way. title-tag declared after loading
 * is refused. A block theme supports thumbnails, responsive embeds, editor
 * styles, HTML5 markup and feed links before its own setup runs.
 *
 * The features themselves (data/theme-features.json, and any a theme
 * registers) say how each is shown in REST: its schema, or the default
 * when the theme does not support it.
 */
final class ThemeSupports
{
    private const KEY = 'theme_supports';

    private const FEATURES = 'theme_features';

    private const LOGO = ['width' => null, 'height' => null, 'flex-width' => false, 'flex-height' => false, 'header-text' => '', 'unlink-homepage-logo' => false];

    private const HEADER = ['default-image' => '', 'random-default' => false, 'width' => 0, 'height' => 0, 'flex-height' => false, 'flex-width' => false, 'default-text-color' => '', 'header-text' => true, 'uploads' => true, 'wp-head-callback' => '', 'admin-head-callback' => '', 'admin-preview-callback' => '', 'video' => false, 'video-active-callback' => 'is_front_page'];

    private const BACKGROUND = ['default-image' => '', 'default-preset' => 'default', 'default-position-x' => 'left', 'default-position-y' => 'top', 'default-size' => 'auto', 'default-repeat' => 'repeat', 'default-attachment' => 'scroll', 'default-color' => '', 'wp-head-callback' => '_custom_background_cb', 'admin-head-callback' => '', 'admin-preview-callback' => ''];

    private const FORMATS = ['aside', 'chat', 'gallery', 'link', 'image', 'quote', 'status', 'video', 'audio'];

    private const TYPES = ['boolean', 'integer', 'number', 'string', 'array', 'object'];

    /** Every feature the theme has declared, as stored. @return array<string, mixed> */
    public static function all(): array
    {
        return (array) Runtime::current()->get(self::KEY, []);
    }

    /** add_theme_support: false when refused. @param list<mixed> $args */
    public static function add(string $feature, array $args): bool
    {
        $supports = self::all();
        $had = $supports[$feature] ?? null;
        if ($feature === 'title-tag' && Runtime::hooks()->actionsDone('wp_loaded')) {
            \_doing_it_wrong('add_theme_support( \'title-tag\' )', 'Theme support for <code>title-tag</code> should be registered before the <code>wp_loaded</code> hook.', '4.1.0');
            return false;
        }
        $supports[$feature] = match ($feature) {
            'html5' => [array_merge(is_array($had) ? (array) ($had[0] ?? []) : [], (array) ($args[0] ?? ['comment-list', 'comment-form', 'search-form']))],
            'post-thumbnails' => $had === true || $args === [] ? true : [array_values(array_unique(array_merge(is_array($had) ? (array) $had[0] : [], (array) $args[0])))],
            'post-formats' => [array_values(array_intersect((array) ($args[0] ?? []), self::FORMATS))],
            'custom-logo' => [array_merge(self::LOGO, $args === [] ? ['flex-width' => true, 'flex-height' => true] : (array) $args[0])],
            'custom-header', 'custom-background' => [(array) ($args[0] ?? [])],
            default => $args === [] ? true : $args,
        };
        Runtime::current()->set(self::KEY, $supports);
        return true;
    }

    /** remove_theme_support: false when the theme did not support it. */
    public static function remove(string $feature): bool
    {
        $supports = self::all();
        if (!array_key_exists($feature, $supports)) {
            return false;
        }
        unset($supports[$feature]);
        Runtime::current()->set(self::KEY, $supports);
        return true;
    }

    /** _add_default_theme_supports: what every block theme supports before its own setup. */
    public static function blockThemeDefaults(): void
    {
        if (!\wp_is_block_theme()) {
            return;
        }
        foreach (['post-thumbnails' => [], 'responsive-embeds' => [], 'editor-styles' => [], 'html5' => [['comment-form', 'comment-list', 'search-form', 'gallery', 'caption', 'style', 'script']], 'automatic-feed-links' => []] as $feature => $args) {
            self::add($feature, $args);
        }
    }

    /** wp_enable_block_templates and wp_setup_widgets_block_editor: a block theme's templates, and the block widget editor for any theme. */
    public static function editorDefaults(string $which): void
    {
        if ($which === 'block-templates' && \wp_is_block_theme()) {
            self::add('block-templates', []);
        } elseif ($which === 'widgets-block-editor') {
            self::add('widgets-block-editor', []);
        }
    }

    /** _custom_header_background_just_in_time: the header's and background's defaults filled in once loaded. */
    public static function justInTime(): void
    {
        $supports = self::all();
        if (isset($supports['custom-header'][0])) {
            $header = array_merge(self::HEADER, (array) $supports['custom-header'][0]);
            $header['width'] = (int) $header['width'];
            $header['height'] = (int) $header['height'];
            $header['flex-width'] = $header['flex-width'] || $header['width'] === 0;
            $header['flex-height'] = $header['flex-height'] || $header['height'] === 0;
            $header['random-default'] = $header['default-image'] === '' && (bool) $header['random-default'];
            $supports['custom-header'] = [$header];
        }
        if (isset($supports['custom-background'][0])) {
            $supports['custom-background'] = [array_merge(self::BACKGROUND, (array) $supports['custom-background'][0])];
        }
        Runtime::current()->set(self::KEY, $supports);
    }

    /** register_theme_feature: true, or why the feature cannot be registered. */
    public static function register(string $feature, array $args): true|\WP_Error
    {
        $args = array_merge(['type' => 'boolean', 'variadic' => false, 'description' => '', 'show_in_rest' => false], $args);
        if (!in_array($args['type'], self::TYPES, true)) {
            return new \WP_Error('invalid_type', 'The feature "type" is not valid JSON Schema type.');
        }
        if ($args['variadic'] && $args['type'] !== 'array') {
            return new \WP_Error('variadic_must_be_array', 'When registering a "variadic" theme feature, the "type" must be an "array".');
        }
        $rest = $args['show_in_rest'] === true ? [] : $args['show_in_rest'];
        if (is_array($rest)) {
            $schema = (array) ($rest['schema'] ?? []);
            if (in_array($args['type'], ['array', 'object'], true) && !isset($schema['items']) && !isset($schema['properties'])) {
                return new \WP_Error('missing_schema', 'When registering an "array" or "object" feature to show in the REST API, the feature\'s schema must also be defined.');
            }
            $type = in_array($args['type'], ['array', 'object'], true) ? ['boolean', $args['type']] : $args['type'];
            $schema = array_merge(['description' => $args['description'], 'type' => $type, 'default' => false], $schema);
            if ($args['type'] === 'object' && !isset($schema['additionalProperties'])) {
                $schema['additionalProperties'] = false;
            }
            $args['show_in_rest'] = ['schema' => $schema, 'name' => $rest['name'] ?? $feature, 'prepare_callback' => $rest['prepare_callback'] ?? null];
        }
        $features = (array) Runtime::current()->get(self::FEATURES, []);
        $features[$feature] = $args;
        Runtime::current()->set(self::FEATURES, $features);
        return true;
    }

    /** Core's features, then those registered since. @return array<string, array<string, mixed>> */
    public static function features(): array
    {
        static $core = null;
        $core ??= (array) json_decode((string) file_get_contents(MINN_ENGINE_DIR . '/data/theme-features.json'), true);
        return array_merge($core, (array) Runtime::current()->get(self::FEATURES, []));
    }

    /** The active theme's supports as wp/v2/themes shows them: each feature shown in REST, shaped by its schema. @return array<string, mixed> */
    public static function forRest(\WP_REST_Request $request): array
    {
        $out = [];
        foreach (self::features() as $feature => $config) {
            $rest = $config['show_in_rest'] ?? false;
            if (!is_array($rest)) {
                continue;
            }
            $name = (string) ($rest['name'] ?? $feature);
            if (!\current_theme_supports($feature)) {
                $out[$name] = $rest['schema']['default'] ?? false;
                continue;
            }
            $support = \get_theme_support($feature);
            if (is_array($support) && !($config['variadic'] ?? false)) {
                $support = $support[0] ?? [];
            }
            $out[$name] = match (true) {
                is_callable($rest['prepare_callback'] ?? null) => call_user_func($rest['prepare_callback'], $support, $config, $feature, $request),
                $feature === 'post-formats' => ['standard', ...(array) $support],
                default => \rest_sanitize_value_from_schema($support, (array) $rest['schema'], $name),
            };
        }
        return $out;
    }
}
