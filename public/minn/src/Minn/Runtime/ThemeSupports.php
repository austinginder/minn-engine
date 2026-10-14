<?php

declare(strict_types=1);

namespace Minn\Runtime;

use Minn\Json;
use Minn\Objects\Errors;
use Minn\Objects\RestRequest;
use Minn\Rest\SiteSchema;

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
            Notices::doingItWrong('add_theme_support( \'title-tag\' )', 'Theme support for <code>title-tag</code> should be registered before the <code>wp_loaded</code> hook.', '4.1.0');
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
        if (!(bool) Runtime::current()->get('block_theme', true)) {
            return;
        }
        foreach (['post-thumbnails' => [], 'responsive-embeds' => [], 'editor-styles' => [], 'html5' => [['comment-form', 'comment-list', 'search-form', 'gallery', 'caption', 'style', 'script']], 'automatic-feed-links' => []] as $feature => $args) {
            self::add($feature, $args);
        }
    }

    /** wp_enable_block_templates and wp_setup_widgets_block_editor: a block theme's templates, and the block widget editor for any theme. */
    public static function editorDefaults(string $which): void
    {
        if ($which === 'block-templates' && (bool) Runtime::current()->get('block_theme', true)) {
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
        // Their wp_head callbacks print the header's and the background's styles (the background's is _custom_background_cb).
        foreach (['custom-header', 'custom-background'] as $feature) {
            $callback = $supports[$feature][0]['wp-head-callback'] ?? '';
            if ($callback !== '' && $callback !== null && is_callable($callback)) {
                Runtime::hooks()->add('wp_head', $callback);
            }
        }
    }

    /** register_theme_feature: true, or why the feature cannot be registered. */
    public static function register(string $feature, array $args): true|Errors
    {
        $args = array_merge(['type' => 'boolean', 'variadic' => false, 'description' => '', 'show_in_rest' => false], $args);
        if (!in_array($args['type'], self::TYPES, true)) {
            return Handoff::newError('invalid_type', 'The feature "type" is not valid JSON Schema type.');
        }
        if ($args['variadic'] && $args['type'] !== 'array') {
            return Handoff::newError('variadic_must_be_array', 'When registering a "variadic" theme feature, the "type" must be an "array".');
        }
        $rest = $args['show_in_rest'] === true ? [] : $args['show_in_rest'];
        if (is_array($rest)) {
            $schema = (array) ($rest['schema'] ?? []);
            if (in_array($args['type'], ['array', 'object'], true) && !isset($schema['items']) && !isset($schema['properties'])) {
                return Handoff::newError('missing_schema', 'When registering an "array" or "object" feature to show in the REST API, the feature\'s schema must also be defined.');
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
        $core ??= (array) Json::file(MINN_ENGINE_DIR . '/data/theme-features.json');
        return array_merge($core, (array) Runtime::current()->get(self::FEATURES, []));
    }

    /** The active theme's supports as wp/v2/themes shows them: each feature shown in REST, shaped by its schema. @return array<string, mixed> */
    public static function forRest(RestRequest $request): array
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
            $out[$name] = $feature === 'post-formats' ? ['standard', ...(array) self::unwrapped($support, $config)] : self::prepared($support, $config, $feature, $request);
        }
        return $out;
    }

    /**
     * One feature's support as the REST API shows it (the runtime's
     * WP_REST_Themes_Controller::prepare_theme_support): a feature that is
     * not variadic shown by its first value, through its prepare_callback
     * or else cut to its schema.
     *
     * @param array<string, mixed> $config the feature as it was registered
     */
    public static function prepared(mixed $support, array $config, string $feature, RestRequest $request): mixed
    {
        $rest = (array) ($config['show_in_rest'] ?? []);
        $support = self::unwrapped($support, $config);
        if (is_callable($rest['prepare_callback'] ?? null)) {
            return call_user_func($rest['prepare_callback'], $support, $config, $feature, $request);
        }
        return SiteSchema::sanitize($support, (array) ($rest['schema'] ?? []), (string) ($rest['name'] ?? $feature));
    }

    /** @param array<string, mixed> $config */
    private static function unwrapped(mixed $support, array $config): mixed
    {
        return is_array($support) && !($config['variadic'] ?? false) ? ($support[0] ?? []) : $support;
    }
}
